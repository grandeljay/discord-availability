<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\{Bot, Config, UserAvailabilities, UserAvailability, UserAvailabilityTime};

class Unavailable extends Command
{
    public function run(Discord $discord): void
    {
        $discord->listenCommand(
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

                if (time() < $timeUnavailable) {
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

                $config = new Config();

                $interaction
                ->respondWithMessage(
                    MessageBuilder::new()->setContent(
                        sprintf(
                            'Gotcha! You are **unavailable** for %s on `%s` at `%s`.',
                            $config->getEventName(),
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
