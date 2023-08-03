<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\{Bot, Config, UserAvailabilities, UserAvailability, UserAvailabilityTime};

class Available extends Command
{
    public function run(Discord $discord): void
    {
        $discord->listenCommand(
            strtolower(Command::AVAILABLE),
            function (Interaction $interaction) {
                $this->userAvailabilities = UserAvailabilities::getAll();

                $timeAvailable = Bot::getTimeFromString($interaction->data->options['date']->value);

                if (false === $timeAvailable) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()->setContent('Sorry, I couldn\'t parse that. Could you please specify a more machine friendly time?')
                    );

                    return;
                }

                if (time() < $timeAvailable) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()
                        ->setContent(
                            sprintf(
                                'You\'re available on `%s` at `%s`? That doesn\'t sound right. Please specify a time in the future.',
                                date('d.m.Y', $timeAvailable),
                                date('H:i', $timeAvailable),
                            )
                        )
                        ->_setFlags(Message::FLAG_EPHEMERAL)
                    );

                    return;
                }

                $userAvailabilityTime = new UserAvailabilityTime();
                $userAvailabilityTime->setAvailability(true, false);
                $userAvailabilityTime->setTime($timeAvailable);

                $userAvailability = UserAvailability::get($interaction->user);
                $userAvailability->addAvailability($userAvailabilityTime);
                $userAvailability->save();

                $config = new Config();

                $interaction
                ->respondWithMessage(
                    MessageBuilder::new()->setContent(
                        sprintf(
                            'Gotcha! You are **available** for %s on `%s` at `%s`.',
                            $config->getEventName(),
                            date('d.m.Y', $timeAvailable),
                            date('H:i', $timeAvailable)
                        )
                    ),
                    true
                );
            }
        );
    }
}
