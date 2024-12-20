<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\{Config, UserAvailability, UserAvailabilityTime};

class Unavailable extends Command
{
    public function run(Discord $discord): void
    {
        $command  = strtolower(Command::UNAVAILABLE);
        $callback = [$this, 'setUserUnavailability'];

        $discord->listenCommand($command, $callback);
    }

    public function setUserUnavailability(Interaction $interaction): void
    {
        $timeAvailable       = Command::getAvailabilityTimes($interaction);
        $timeUnavailableFrom = $timeAvailable['from'];
        $timeUnavailableTo   = $timeAvailable['to'];

        if (false === $timeUnavailableFrom || false === $timeUnavailableTo) {
            Bot::respondCouldNotParseTime($interaction);

            return;
        }

        $userAvailabilityTime = new UserAvailabilityTime();
        $userAvailabilityTime->setAvailability(false);
        $userAvailabilityTime->setTimeFrom($timeUnavailableFrom);
        $userAvailabilityTime->setTimeTo($timeUnavailableTo);
        $userAvailabilityTime->setAvailablePerDefault(false);

        if ($userAvailabilityTime->isInPast()) {
            $interaction
            ->respondWithMessage(
                MessageBuilder::new()
                ->setContent(
                    sprintf(
                        'You\'re unavailable on `%s` at `%s`? That doesn\'t sound right. Please specify a time in the future.',
                        date('d.m.Y', $timeUnavailableFrom),
                        date('H:i', $timeUnavailableFrom),
                    )
                )
                ->_setFlags(Message::FLAG_EPHEMERAL)
            );

            return;
        }

        $userAvailability = UserAvailability::get($interaction->user);
        $userAvailability->addAvailability($userAvailabilityTime);
        $userAvailability->save();

        $config = new Config();

        $interaction
        ->respondWithMessage(
            MessageBuilder::new()
            ->setContent(
                sprintf(
                    'Gotcha! You are **unavailable** for **%s** on `%s` at `%s` (until `%s` at `%s`).',
                    $config->getEventName(),
                    date('d.m.Y', $timeUnavailableFrom),
                    date('H:i', $timeUnavailableFrom),
                    date('d.m.Y', $timeUnavailableTo),
                    date('H:i', $timeUnavailableTo)
                )
            )
            ->_setFlags(Message::FLAG_EPHEMERAL)
        );
    }
}
