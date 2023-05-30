<?php

namespace Grandeljay\Availability;

use Discord\Builders\Components\{Button, ActionRow};
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Discord\WebSockets\Event;
use Grandeljay\Availability\Commands\Command;

class Availability
{
    private const PATH_AVAILABILITIES = __DIR__ . '/availabilities';

    protected Discord $discord;
    protected Config $config;
    protected Action $action;

    public function __construct()
    {
        $this->config  = new Config();
        $this->discord = new Discord(
            array(
                'token' => $this->config->get('token'),
            )
        );
    }

    /**
     * Removes orphaned commands and adds the active ones.
     *
     * @return void
     */
    public function install(): void
    {
        $this->discord->on(
            'ready',
            function (Discord $discord) {
                /** Remove orphaned commands */
                $discord->application->commands->freshen()->then(
                    function ($commands) use ($discord) {
                        foreach ($commands as $command) {
                            $discord->application->commands->delete($command);
                        }
                    }
                );

                /** Commands */
                $command = new Command(
                    Command::AVAILABILITY,
                    'Shows everybody\'s availability.'
                );
                $command = new Command(
                    Command::AVAILABLE,
                    'Mark yourself as available.'
                );
                $command = new Command(
                    Command::UNAVAILABLE,
                    'Mark yourself as unavailable.'
                );
            }
        );
    }

    public function initialise(): void
    {
        $this->install();

        $this->discord->on(
            Event::MESSAGE_CREATE,
            function (Message $message, Discord $discord) {
                $this->determineIfUnavailable($message, $discord);
            }
        );

        // $this->discord->on(
        //     Event::INTERACTION_CREATE,
        //     function (Interaction $interaction, Discord $discord) {
        //         var_dump($interaction->message);
        //         die();
        //     }
        // );

        $this->discord->run();
    }

    /**
     * Permanently saves the user's specified availability time.
     *
     * @param int  $userId               The message author id.
     * @param bool $userIsAvailable      Whether the user is available.
     * @param int  $userAvailabilityTime The unix timestamp of the user's
     *                                   availability.
     *
     * @return void
     */
    public function setUserAvailability(int $userId, bool $userIsAvailable, int $userAvailabilityTime): void
    {
        if (!file_exists(self::PATH_AVAILABILITIES) || !is_dir(self::PATH_AVAILABILITIES)) {
            mkdir(self::PATH_AVAILABILITIES);
        }

        $filename = $userId . '.json';
        $filepath = self::PATH_AVAILABILITIES . '/' . $filename;

        $availability = array(
            'userId'               => $userId,
            'userIsAvailable'      => $userIsAvailable,
            'userAvailabilityTime' => $userAvailabilityTime,
        );

        file_put_contents($filepath, json_encode($availability));
    }

    private function determineIfUnavailable(Message $message, Discord $discord): void
    {
        /** Determine if user is subscribed */
        $availabilities   = $this->getAvailabilities();
        $userIsSubscribed = false;

        foreach ($availabilities as $availability) {
            if ($availability['userId'] === $message->author->id) {
                $userIsSubscribed = true;

                break;
            }
        }

        if (!$userIsSubscribed) {
            return;
        }

        /** Parse message and determine if it means unavailability */
        $userAvailabilityPhrase = '';

        if ('' === $userAvailabilityPhrase) {
            $unavailableKeywordsSingles = array(
                'not available',
                'not coming',
            );

            foreach ($unavailableKeywordsSingles as $keywords) {
                if (str_contains($message->content, $keywords)) {
                    $userAvailabilityPhrase .= $keywords . ' ';
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
                    'unable to',
                    'will not',
                    'won\'t',
                    'wont',
                    'not going to',
                ),
                array(
                    'come',
                    'make it',
                    'be there',
                ),
            );

            foreach ($unavailableKeywordsPairs as $keywordsSet) {
                foreach ($keywordsSet as $keyword) {
                    if (str_contains($message->content, $keyword)) {
                        $userAvailabilityPhrase .= $keyword . ' ';
                    }
                }
            }
        }

        $userAvailabilityPhrase = trim($userAvailabilityPhrase);

        $userIsUnavailable = 1 === preg_match('/' . $userAvailabilityPhrase . ' (.+)/i', $message->content, $matches);

        if (!$userIsUnavailable || !isset($matches[1])) {
            return;
        }

        /** Validate unavailability time */
        $userUnavailableTime = strtotime(
            str_replace(
                array('next week', 'next time', 'next ', 'on '),
                array('monday', 'monday', '', ''),
                $matches[1]
            )
        );

        if (false === $userUnavailableTime || time() >= $userUnavailableTime) {
            return;
        }

        /** Respond with a prompt */
        $actionRow = ActionRow::new()
        ->addComponent(
            Button::new(Button::STYLE_PRIMARY)
            ->setLabel('Yes')
            ->setListener(
                function (Interaction $interaction) use ($userUnavailableTime, $message) {
                    $this->setUserAvailability($interaction->user->id, false, $userUnavailableTime);

                    $message->reply(
                        MessageBuilder::new()
                        ->setContent(
                            sprintf(
                                'Alrighty! You are now officially **unavailable** on `%s`.',
                                date('d.m.Y', $userUnavailableTime)
                            )
                        )
                    );
                    $interaction->message->delete();
                },
                $discord
            )
        )
        ->addComponent(
            Button::new(Button::STYLE_SECONDARY)
            ->setLabel('No')
            ->setListener(
                function (Interaction $interaction) {
                    $interaction->respondWithMessage(
                        MessageBuilder::new()
                        ->setContent('Whoops, sorry!')
                    );
                },
                $discord
            )
        );

        $messageReply = MessageBuilder::new()
        ->setContent(
            sprintf(
                'You won\'t be available for dota on `%s`, did I get that right?',
                date('d.m.Y', $userUnavailableTime)
            )
        )
        ->addComponent($actionRow);

        $message->reply($messageReply);
    }

    protected function getAvailabilities(): array
    {
        $availabilities = array();
        $directory      = Config::getRoot() . '/availabilities';

        if (!is_dir($directory)) {
            return $availabilities;
        }

        $files = array_filter(
            scandir($directory),
            function ($filename) use ($directory) {
                $filepath = $directory . '/'  . $filename;

                return is_file($filepath);
            }
        );

        foreach ($files as $filename) {
            $filepath                = $directory . '/' . $filename;
            $fileContents            = file_get_contents($filepath);
            $availabilitiy           = json_decode($fileContents, true);
            $availabilitiy['userId'] = pathinfo($filename, PATHINFO_FILENAME);
            $availabilities[]        = $availabilitiy;
        }

        return $availabilities;
    }
}
