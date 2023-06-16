<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\{Bot, UserAvailabilities, UserAvailability, UserAvailabilityTime};

class Unavailable extends Bot
{
    public function __construct()
    {
        parent::__construct();
    }

    public function run(): void
    {
        $this->discord->listenCommand(
            strtolower(Command::UNAVAILABLE),
            function (Interaction $interaction) {
                $this->userAvailabilities = UserAvailabilities::getAll();

                $timeUnavailable = Bot::getTimeFromString($interaction->data->options['date']->value);

                if (false === $timeUnavailable) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()->setContent('Sorry, I couldn\'t parse that. Could you please specify a more machine friendly time?')
                    );

                    return;
                }

                if (time() >= $timeUnavailable) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()
                        ->setContent(
                            sprintf(
                                'You\'re unavailable on `%s` at `%s`? That doesn\'t sound right. Please specify a time in the future.',
                                date('d.m.Y', $timeUnavailable),
                                date('H:i', $timeUnavailable),
                            )
                        )
                        ->_setFlags(Message::FLAG_EPHEMERAL)
                    );

                    return;
                }

                $userUnavailabilityTime = new UserAvailabilityTime();
                $userUnavailabilityTime->setAvailability(false, false);
                $userUnavailabilityTime->setTime($timeUnavailable);

                $userUnavailability = UserAvailability::get($interaction->user);
                $userUnavailability->addAvailability($userUnavailabilityTime);
                $userUnavailability->save();

                $interaction
                ->respondWithMessage(
                    MessageBuilder::new()->setContent(
                        sprintf(
                            'Gotcha! You are **unavailable** for Dota on `%s` at `%s`.',
                            date('d.m.Y', $timeUnavailable),
                            date('H:i', $timeUnavailable)
                        )
                    ),
                    true
                );
            }
        );
    }
}
