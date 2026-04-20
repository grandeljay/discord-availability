<?php

namespace Grandeljay\Availability;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Discord\Repository\Interaction\GlobalCommandRepository;
use Discord\WebSockets\Event;
use Discord\WebSockets\Intents;
use Grandeljay\Availability\Commands;
use Grandeljay\Availability\Commands\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Sabre\VObject;

use function React\Promise\all;

class Bot
{
    private Commands $commands;

    protected Discord $discord;
    protected Config $config;
    protected UserAvailabilities $userAvailabilities;

    /**
     * Returns the unix timestamp from a user specified date/time.
     *
     * @deprecated
     *
     * @param string $message The user's date/time input.
     *
     * @return int|false The unix timestamp on success or `false`.
     */
    public static function getTimeFromString(string $message): int|false
    {
        $config          = new Config();
        $defaultDateTime = $config->getDefaultDateTime();

        if (empty($message)) {
            $message = $defaultDateTime;
        } else {
            $message = str_replace(
                ['next week', 'next time', 'next ', 'on ', 'at '],
                [$defaultDateTime, $defaultDateTime, '', '', ''],
                $message
            );
        }

        $time = strtotime($message);

        return $time;
    }

    public static function respondCouldNotParseTime(Interaction $interaction): void
    {
        $interaction
        ->respondWithMessage(
            MessageBuilder::new()
            ->setContent('Sorry, I couldn\'t parse that. Could you please specify a more machine friendly time?')
            ->_setFlags(Message::FLAG_EPHEMERAL)
        );
    }

    /**
     * Construct
     */
    public function __construct()
    {
        $this->commands = new Commands();
        $this->config   = new Config();

        $logLevel = Level::fromName($this->config->getLogLevel());
        $logger   = new Logger('discord-availability');
        $logger->pushHandler(new StreamHandler('php://stdout', $logLevel));

        date_default_timezone_set($this->config->getTimeZone());

        $this->discord = new Discord(
            [
                'token'          => $this->config->getAPIToken(),
                'loadAllMembers' => true,
                'intents'        => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS,
                'logger'         => $logger,
            ]
        );

        // TODO: Add availabilities
    }

    /**
     * Runs the discord bot.
     *
     * @return void
     */
    public function run(): void
    {
        $this->discord->on(
            'init', // Event::READY won't work
            [$this, 'init']
        );

        $this->discord->on(Event::MESSAGE_CREATE, [$this, 'dota2RoleUsed']);
        $this->discord->run();
    }

    public function init(Discord $discord)
    {
        /**
         * `$argv` is a native, global PHP variable.
         */
        global $argv;

        $install = false;

        foreach ($argv as $commandLineArgument) {
            if ('--install' === $commandLineArgument) {
                $install = true;

                $this->installCommands();
            }
        }

        if (!$install) {
            $this->registerCommands($discord);
        }
    }

    /**
     * Removes orphaned commands and adds the active ones.
     *
     * @return void
     */
    public function installCommands(): void
    {
        $this->discord->application->commands
        ->freshen()
        ->done(
            function (GlobalCommandRepository $botCommandsCurrent) {
                $deleted = [];

                foreach ($botCommandsCurrent as $botCommandCurrent) {
                    $deleted[] = $this->discord->application->commands->delete($botCommandCurrent);
                }

                all($deleted)->then([$this, 'registerCommands']);
            }
        );
    }

    /**
     * Registers all slash commands so they can be used.
     *
     * @return void
     */
    public function registerCommands(): void
    {
        $botCommandsDesired = [
            Command::AVAILABILITY => 'Shows everybody\'s availability.',
            Command::AVAILABLE    => 'Mark yourself as available.',
            Command::UNAVAILABLE  => 'Mark yourself as unavailable.',
            Command::SHUTDOWN     => 'Shutdown the bot.',
        ];

        foreach ($botCommandsDesired as $botCommandDesiredName => $botCommandDesiredDescription) {
            $commandObject = new Command($this->discord, $botCommandDesiredName, $botCommandDesiredDescription);
            $commandToRun  = $commandObject->get();

            $this->commands->add($commandToRun);

            $commandToRun->run($this->discord);
        }
    }

    /**
     * Returns whether the current user is subscribed. A user is considered
     * subscribed when the has used the `/available` or `/unavailable` command
     * at least once.
     *
     * @param int $userId The user Id to check.
     *
     * @return boolean
     */
    private function userIsSubscribed(int $userId): bool
    {
        $userIsSubscribed   = false;
        $userAvailabilities = UserAvailabilities::getAll();

        foreach ($userAvailabilities as $availability) {
            if ($availability->getUserId() === $userId) {
                $userIsSubscribed = true;

                break;
            }
        }

        return $userIsSubscribed;
    }

    public function dota2RoleUsed(Message $message, Discord $discord): void
    {
        $roleId = '1344006848650416192';

        if ($message->mention_roles->has($roleId)) {
            $this->checkJaysAvailability($message, $discord);
        }
    }

    private function getCalendars(): array
    {
        $config = new Config();

        $calDavUrl         = 'https://treescloud.online/remote.php/dav/calendars/jay';
        $calDavRequest     = \curl_init($calDavUrl);
        $calDavRequestBody = <<<XML
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:displayname/>
            </d:prop>
        </d:propfind>
        XML;

        $calDavUser     = $config->getNextcloudAppUser();
        $calDavPassword = $config->getNextcloudAppPassword();

        \curl_setopt_array(
            $calDavRequest,
            [
                \CURLOPT_RETURNTRANSFER => true,
                \CURLOPT_USERPWD        => "$calDavUser:$calDavPassword",
                \CURLOPT_HTTPAUTH       => \CURLAUTH_BASIC,
                \CURLOPT_CUSTOMREQUEST  => "PROPFIND",
                \CURLOPT_POSTFIELDS     => $calDavRequestBody,
                \CURLOPT_HTTPHEADER     => [
                    "Content-Type: application/xml; charset=utf-8",
                    "Depth: 1",
                ],
            ]
        );

        $calDavResponse = \curl_exec($calDavRequest);

        \curl_close($calDavRequest);

        /**
         * XML Parsing
         */
        $xml = new \SimpleXMLElement($calDavResponse);
        $xml->registerXPathNamespace('d', 'DAV:');

        $responses = $xml->xpath('//d:response');
        $calendars = [];

        foreach ($responses as $response) {
            $href            = (string) $response->xpath('./d:href')[0];
            $displayNameNode = $response->xpath('.//d:displayname');

            $displayName = $displayNameNode
                ? (string)$displayNameNode[0]
                : null;

            // extract calendar ID from URL
            $parts      = explode('/', trim($href, '/'));
            $calendarId = end($parts);

            $calendars[] = [
                'id'   => $calendarId,
                'name' => $displayName,
                'href' => $href,
            ];
        }

        unset($calendars[0]);

        return $calendars;
    }

    private function getCalendarsEventsForToday(): array
    {
        $config = new Config();

        $dateTimeToday    = new \DateTime();
        $dateTimeTomorrow = new \DateTime();
        $dateTimeZone     = new \DateTimeZone('UTC');
        $dateInterval     = new \DateInterval('P1D');
        $dateTimeFormat   = 'Ymd\THis\Z';
        $dateTimeToday->setTimezone($dateTimeZone);
        $dateTimeToday->setTime(0, 0);
        $dateTimeTomorrow->setTimezone($dateTimeZone);
        $dateTimeTomorrow->setTime(0, 0);
        $dateTimeTomorrow->add($dateInterval);

        $dateStart = $dateTimeToday->format($dateTimeFormat);
        $dateEnd   = $dateTimeTomorrow->format($dateTimeFormat);

        $calendars = $this->getCalendars();
        $events    = [];

        foreach ($calendars as $calendar) {
            $calendarId = $calendar['id'];

            $calDavUrl         = 'https://treescloud.online/remote.php/dav/calendars/jay/' . $calendarId;
            $calDavRequest     = \curl_init($calDavUrl);
            $calDavRequestBody = <<<XML
            <C:calendar-query xmlns:C="urn:ietf:params:xml:ns:caldav">
                <D:prop xmlns:D="DAV:">
                    <C:calendar-data/>
                </D:prop>

                <C:filter>
                    <C:comp-filter name="VCALENDAR">
                        <C:comp-filter name="VEVENT">
                            <C:time-range start="$dateStart" end="$dateEnd"/>
                        </C:comp-filter>
                    </C:comp-filter>
                </C:filter>
            </C:calendar-query>
            XML;

            $calDavUser     = $config->getNextcloudAppUser();
            $calDavPassword = $config->getNextcloudAppPassword();

            \curl_setopt_array(
                $calDavRequest,
                [
                    \CURLOPT_RETURNTRANSFER => true,
                    \CURLOPT_USERPWD        => "$calDavUser:$calDavPassword",
                    \CURLOPT_HTTPAUTH       => \CURLAUTH_BASIC,
                    \CURLOPT_CUSTOMREQUEST  => "REPORT",
                    \CURLOPT_POSTFIELDS     => $calDavRequestBody,
                    \CURLOPT_HTTPHEADER     => [
                        "Content-Type: application/xml; charset=utf-8",
                        "Depth: 1",
                    ],
                ]
            );

            $calDavResponse = \curl_exec($calDavRequest);

            \curl_close($calDavRequest);

            $xml = new \SimpleXMLElement($calDavResponse);
            $xml->registerXPathNamespace('d', 'DAV:');
            $xml->registerXPathNamespace('cal', 'urn:ietf:params:xml:ns:caldav');

            $nodes = $xml->xpath('//cal:calendar-data');

            foreach ($nodes as $node) {
                $icsData   = (string) $node;
                $vcalendar = VObject\Reader::read($icsData);

                foreach ($vcalendar->VEVENT as $event) {
                    $summary  = $event->SUMMARY->getValue();
                    $isAllDay = $event->DTSTART->getValueType() === 'DATE';

                    $event = [
                        'summary'  => $summary,
                        'isAllDay' => $isAllDay,
                    ];

                    if ($isAllDay) {
                        $events[] = $event;

                        continue;
                    }

                    $timeStart = $vcalendar->VEVENT->DTSTART->getDateTime();
                    $timeEnd   = $vcalendar->VEVENT->DTEND->getDateTime();

                    $event['timeStart'] = $timeStart;
                    $event['timeEnd']   = $timeEnd;

                    $events[] = $event;
                }
            }
        }

        return $events;
    }

    public function checkJaysAvailability(Message $message, Discord $discord): void
    {
        $message->channel->broadcastTyping();

        $events = $this->getCalendarsEventsForToday();

        $timeNow        = new \DateTime();
        $jayIsAvailable = true;

        foreach ($events as $event) {
            $eventIsAllDay = $event['isAllDay'];

            if ($eventIsAllDay) {
                continue;
            }

            $eventSpansMultipleDays = $event['timeStart']->format('Y-m-d')
                                  !== $event['timeEnd']->format('Y-m-d');

            if ($eventSpansMultipleDays) {
                continue;
            }

            if ($event['timeStart'] <= $timeNow && $event['timeEnd'] > $timeNow) {
                $jayIsAvailable = false;

                break;
            }
        }

        if (!$jayIsAvailable) {
            $message->reply('According to his calendars, Jay is not available.');
        }
    }
}
