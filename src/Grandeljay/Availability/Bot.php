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
use Grandeljay\Availability\Helpers\Nextcloud;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

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
            $message = \str_replace(
                ['next week', 'next time', 'next ', 'on ', 'at '],
                [$defaultDateTime, $defaultDateTime, '', '', ''],
                $message
            );
        }

        $time = \strtotime($message);

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

        \date_default_timezone_set($this->config->getTimeZone());

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
        $config                     = new Config();
        $dota2RoleId                = $config->getDiscordDotaRoleId();
        $messageContainsDotaMention = $message->mention_roles->has($dota2RoleId);

        if ($messageContainsDotaMention) {
            $this->checkJaysAvailability($message, $discord);
        }
    }

    public function checkJaysAvailability(Message $message, Discord $discord): void
    {
        $message->channel->broadcastTyping();

        $config         = new Config();
        $configTimeZone = $config->getTimeZone();
        $events         = Nextcloud::getCalendarEventsToday();

        $timeNow  = new \DateTime();
        $timeZone = new \DateTimeZone($configTimeZone);

        $dotaMatchDuration = new \DateInterval('PT2H');

        foreach ($events as $event) {
            $eventIsAllDay = $event['isAllDay'];

            if ($eventIsAllDay) {
                continue;
            }

            $event['timeStart'] = $event['timeStart']->setTimezone($timeZone);
            $event['timeEnd']   = $event['timeEnd']->setTimezone($timeZone);

            $eventTimeStart         = $event['timeStart']->sub($dotaMatchDuration);
            $eventTimeEnd           = $event['timeEnd'];
            $eventSpansMultipleDays = $eventTimeStart->format('Y-m-d')
                                  !== $eventTimeEnd->format('Y-m-d');

            if ($eventSpansMultipleDays) {
                continue;
            }

            $eventHasStarted     = $eventTimeStart <= $timeNow;
            $eventHasNotFinished = $eventTimeEnd > $timeNow;
            $eventIsInProgress   = $eventHasStarted && $eventHasNotFinished;

            if ($eventIsInProgress) {
                $eventSummary             = \strtolower($event['summary'] ?? '');
                $eventSummaryContainsDota = \str_contains($eventSummary, 'dota');

                if (!$eventSummaryContainsDota) {
                    $timeEndDiff          = $timeNow->diff($eventTimeEnd);
                    $timeEndDiffFormatted = $timeEndDiff->format('%H:%I hours');
                    $timeEndFormatted     = $eventTimeEnd->format('H:i');

                    $jayIsUnavailableMessage = \sprintf(
                        'Jay is not available according to his calendars. He might be available again at %1$s (in %2$s).',
                        $timeEndFormatted,
                        $timeEndDiffFormatted
                    );

                    $message->reply($jayIsUnavailableMessage);

                    return;
                }
            }
        }
    }
}
