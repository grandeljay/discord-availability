<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\{Config, UserAvailability, UserAvailabilityTime};

class Available extends Command
{
    public function run(Discord $discord): void
    {
        $command  = strtolower(Command::AVAILABLE);
        $callback = array($this, 'setUserAvailability');

        $discord->listenCommand($command, $callback);
    }

    public function setUserAvailability(Interaction $interaction): void
    {
        $timeAvailable     = Command::getAvailabilityTimes($interaction);
        $timeAvailableFrom = $timeAvailable['from'];
        $timeAvailableTo   = $timeAvailable['to'];

        if (false === $timeAvailableFrom || false === $timeAvailableTo) {
            $interaction
            ->respondWithMessage(
                MessageBuilder::new()
                ->setContent('Sorry, I couldn\'t parse that. Could you please specify a more machine friendly time?')
                ->_setFlags(Message::FLAG_EPHEMERAL)
            );

            return;
        }

        $userAvailabilityTime = new UserAvailabilityTime();
        $userAvailabilityTime->setAvailability(true);
        $userAvailabilityTime->setTimeFrom($timeAvailableFrom);
        $userAvailabilityTime->setTimeTo($timeAvailableTo);
        $userAvailabilityTime->setAvailablePerDefault(false);

        if ($userAvailabilityTime->isInPast()) {
            $interaction
            ->respondWithMessage(
                MessageBuilder::new()
                ->setContent(
                    sprintf(
                        'You\'re available on `%s` at `%s`? That doesn\'t sound right. Please specify a time in the future.',
                        date('d.m.Y', $timeAvailableFrom),
                        date('H:i', $timeAvailableFrom),
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
                    'Gotcha! You are **available** for **%s** on `%s` at `%s` (until `%s` at `%s`).',
                    $config->getEventName(),
                    date('d.m.Y', $timeAvailableFrom),
                    date('H:i', $timeAvailableFrom),
                    date('d.m.Y', $timeAvailableTo),
                    date('H:i', $timeAvailableTo)
                )
            )
            ->_setFlags(Message::FLAG_EPHEMERAL)
        );

        $userAvailabilityTime->promptIfAvailableNow($this->discord, $interaction);
    }
}
