<?php

namespace Grandeljay\Availability;

use Discord\Builders\Components\{Button, ActionRow};
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Discord\Repository\Interaction\GlobalCommandRepository;
use Discord\WebSockets\{Event, Intents};
use Grandeljay\Availability\Commands;
use Grandeljay\Availability\Commands\Command;
use Monolog\{Logger, Level};
use Monolog\Handler\StreamHandler;

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
     * @param string $message The user's date/time input.
     *
     * @return int|false The unix timestamp on success or `false`.
     */
    public static function getTimeFromString(string $message): int|false
    {
        $config          = new Config();
        $defaultDateTime = $config->getDefaultDateTime();

        $message = str_replace(
            array('next week', 'next time', 'next ', 'on ', 'at '),
            array($defaultDateTime, $defaultDateTime, '', '', ''),
            $message
        );

        $time = strtotime($message);

        // TODO: Add time based on the configuration value `defaultTime`.
        if ('00:00' === date('H:i', $time)) {
            $time += 19 * 3600;
        }

        return $time;
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

        $this->discord = new Discord(
            array(
                'token'          => $this->config->getAPIToken(),
                'loadAllMembers' => true,
                'intents'        => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS,
                'logger'         => $logger,
            )
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
            'ready', // Event::READY won't work
            array($this, 'ready')
        );

        $this->discord->run();
    }

    public function ready(Discord $discord)
    {
        $this->addCommands($discord);

        $discord->on(
            Event::MESSAGE_CREATE,
            function (Message $message, Discord $discord) {
                if (!$this->determineIfUnavailable($message, $discord)) {
                    $this->determineIfAvailable($message, $discord);
                }
            }
        );
    }

    /**
     * Removes orphaned commands and adds the active ones.
     *
     * @return void
     */
    public function addCommands(Discord $discord): void
    {
        $discord->application->commands
        ->freshen()
        ->done(
            function (GlobalCommandRepository $botCommandsCurrent) use ($discord) {
                $deleted = array();

                foreach ($botCommandsCurrent as $botCommandCurrent) {
                    $deleted[] = $discord->application->commands->delete($botCommandCurrent);
                }

                all($deleted)->then(
                    function () use ($discord) {
                        $botCommandsDesired = array(
                            Command::AVAILABILITY => 'Shows everybody\'s availability.',
                            Command::AVAILABLE    => 'Mark yourself as available.',
                            Command::UNAVAILABLE  => 'Mark yourself as unavailable.',
                            Command::SHUTDOWN     => 'Shutdown the bot.',
                        );

                        foreach ($botCommandsDesired as $botCommandDesiredName => $botCommandDesiredDescription) {
                            $commandObject = new Command($discord, $botCommandDesiredName, $botCommandDesiredDescription);
                            $commandToRun  = $commandObject->get();

                            $this->commands->add($commandToRun);

                            $commandToRun->run($discord);
                        }
                    }
                );
            }
        );
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

    private function determineIfAvailable(Message $message, Discord $discord): bool
    {
        if (!$this->userIsSubscribed($message->author->id)) {
            return false;
        }

        /** Parse message and determine if it means availability */
        $userAvailabilityPhrase = '';

        if ('' === $userAvailabilityPhrase) {
            $availableKeywordsSingles = array(
                'available',
                'coming',
            );

            foreach ($availableKeywordsSingles as $keyword) {
                if (str_contains($message->content, $keyword)) {
                    $userAvailabilityPhrase .= $keyword . ' ';
                }
            }
        }

        if ('' === $userAvailabilityPhrase) {
            $availableKeywordsPairs = array(
                array(
                    'can',
                    'going to',
                    'able to',
                    'will',
                ),
                array(
                    'be there',
                    'come',
                    'make it',
                ),
            );

            foreach ($availableKeywordsPairs as $keywordsSet) {
                foreach ($keywordsSet as $keyword) {
                    if (str_contains($message->content, $keyword)) {
                        $userAvailabilityPhrase .= $keyword . ' ';
                    }
                }
            }
        }

        $userAvailabilityPhrase = trim($userAvailabilityPhrase);

        if ('' === $userAvailabilityPhrase) {
            return false;
        }

        $userIsAvailable = 1 === preg_match('/' . $userAvailabilityPhrase . ' (.+)/i', $message->content, $matches);

        if (!$userIsAvailable || !isset($matches[1])) {
            return false;
        }

        /** Validate availability time */
        $userAvailableTime = Bot::getTimeFromString($matches[1]);

        if (false === $userAvailableTime || time() >= $userAvailableTime) {
            return false;
        }

        /** Respond with a prompt */
        $actionRow = ActionRow::new()
        ->addComponent(
            Button::new(Button::STYLE_PRIMARY)
            ->setLabel('Yes')
            ->setListener(
                function (Interaction $interaction) use ($userAvailableTime, $message) {
                    $userAvailabilityTime = new UserAvailabilityTime();
                    $userAvailabilityTime->setAvailability(true, false);
                    $userAvailabilityTime->setTime($userAvailableTime);

                    $userAvailability = UserAvailability::get($interaction->user);
                    $userAvailability->addAvailability($userAvailabilityTime);
                    $userAvailability->save();

                    $interaction->message->delete();

                    $message->reply(
                        MessageBuilder::new()
                        ->setContent(
                            sprintf(
                                'Alrighty! You are now officially **available** on `%s` at `%s`.',
                                date('d.m.Y', $userAvailableTime),
                                date('H:i', $userAvailableTime)
                            )
                        )
                        ->_setFlags(Message::FLAG_EPHEMERAL)
                    );
                },
                $discord
            )
        )
        ->addComponent(
            Button::new(Button::STYLE_SECONDARY)
            ->setLabel('No')
            ->setListener(
                function (Interaction $interaction) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()
                        ->setContent('Whoops, sorry!'),
                        true
                    );
                    $interaction->message->delete();
                },
                $discord
            )
        );

        $messageReply = MessageBuilder::new()
        ->setContent(
            sprintf(
                'You will be **available** for %s on `%s` at `%s`, did I get that right?',
                $this->config->getEventName(),
                date('d.m.Y', $userAvailableTime),
                date('H:i', $userAvailableTime),
            )
        )
        ->_setFlags(Message::FLAG_EPHEMERAL)
        ->addComponent($actionRow);

        $message->reply($messageReply);

        return true;
    }

    private function determineIfUnavailable(Message $message, Discord $discord): bool
    {
        if (!$this->userIsSubscribed($message->author->id)) {
            return false;
        }

        /** Parse message and determine if it means unavailability */
        $userAvailabilityPhrase = '';

        if ('' === $userAvailabilityPhrase) {
            $unavailableKeywordsSingles = array(
                'not available',
                'not coming',
                'unavailable',
            );

            foreach ($unavailableKeywordsSingles as $keyword) {
                if (str_contains($message->content, $keyword)) {
                    $userAvailabilityPhrase .= $keyword . ' ';
                }
            }
        }

        if ('' === $userAvailabilityPhrase) {
            $unavailableKeywordsPairs = array(
                array(
                    'can not',
                    'can\'t',
                    'cannot',
                    'cant',
                    'not going to',
                    'unable to',
                    'will not',
                    'won\'t',
                    'wont',
                ),
                array(
                    'be available',
                    'be there',
                    'come',
                    'make it',
                ),
            );

            foreach ($unavailableKeywordsPairs as $keywordSetIndex => $keywordsSet) {
                foreach ($keywordsSet as $keyword) {
                    if (str_contains($message->content, $keyword)) {
                        $userAvailabilityPhrase .= $keyword . ' ';
                    }
                }

                if (0 === $keywordSetIndex && '' === $userAvailabilityPhrase) {
                    break;
                }
            }
        }

        $userAvailabilityPhrase = trim($userAvailabilityPhrase);

        if ('' === $userAvailabilityPhrase) {
            return false;
        }

        $userIsUnavailable = 1 === preg_match('/' . $userAvailabilityPhrase . ' (.+)/i', $message->content, $matches);

        if (!$userIsUnavailable || !isset($matches[1])) {
            return false;
        }

        /** Validate unavailability time */
        $userUnavailableTime = Bot::getTimeFromString($matches[1]);

        if (false === $userUnavailableTime || time() >= $userUnavailableTime) {
            return false;
        }

        /** Respond with a prompt */
        $actionRow = ActionRow::new()
        ->addComponent(
            Button::new(Button::STYLE_PRIMARY)
            ->setLabel('Yes')
            ->setListener(
                function (Interaction $interaction) use ($userUnavailableTime, $message) {
                    $userAvailabilityTime = new UserAvailabilityTime();
                    $userAvailabilityTime->setAvailability(false, false);
                    $userAvailabilityTime->setTime($userUnavailableTime);

                    $userAvailability = UserAvailability::get($interaction->user);
                    $userAvailability->addAvailability($userAvailabilityTime);
                    $userAvailability->save();

                    $interaction->message->delete();

                    $message->reply(
                        MessageBuilder::new()
                        ->setContent(
                            sprintf(
                                'Alrighty! You are now officially **unavailable** on `%s` at `%s`.',
                                date('d.m.Y', $userUnavailableTime),
                                date('H:i', $userUnavailableTime)
                            )
                        )
                        ->_setFlags(Message::FLAG_EPHEMERAL)
                    );
                },
                $discord
            )
        )
        ->addComponent(
            Button::new(Button::STYLE_SECONDARY)
            ->setLabel('No')
            ->setListener(
                function (Interaction $interaction) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()
                        ->setContent('Whoops, sorry!'),
                        true
                    );
                    $interaction->message->delete();
                },
                $discord
            )
        );

        $messageReply = MessageBuilder::new()
        ->setContent(
            sprintf(
                'You will be **unavailable** for %s on `%s` at `%s`, did I get that right?',
                $this->config->getEventName(),
                date('d.m.Y', $userUnavailableTime),
                date('H:i', $userUnavailableTime),
            )
        )
        ->_setFlags(Message::FLAG_EPHEMERAL)
        ->addComponent($actionRow);

        $message->reply($messageReply);

        return true;
    }
}
