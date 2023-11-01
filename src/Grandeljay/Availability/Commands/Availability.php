<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\{Bot, UserAvailabilities};

class Availability extends Command
{
    public function run(Discord $discord): void
    {
        $command  = strtolower(Command::AVAILABILITY);
        $callback = array($this, 'getUsersAvailabilities');

        $discord->listenCommand($command, $callback);
    }

    public function getUsersAvailabilities(Interaction $interaction): void
    {
        $timeFromText = $interaction->data->options['from']->value ?? '';
        $timeFrom     = Bot::getTimeFromString($timeFromText);
        $timeToText   = $interaction->data->options['to']->value ?? '';
        $timeTo       = Bot::getTimeFromString($timeToText);

        if (false === $timeFrom || false === $timeTo) {
            $interaction
            ->respondWithMessage(
                MessageBuilder::new()
                ->setContent('Sorry, I couldn\'t parse that. Could you please specify a more machine friendly time?')
                ->_setFlags(Message::FLAG_EPHEMERAL)
            );

            return;
        }

        $messageRows = array(
            \sprintf(
                'Showing availabilities for all users on `%s` at `%s` (until `%s` at `%s`).',
                date('d.m.Y', $timeFrom),
                date('H:i', $timeFrom),
                date('d.m.Y', $timeTo),
                date('H:i', $timeTo)
            ),
            '',
        );

        $userAvailabilities = UserAvailabilities::getAll();

        foreach ($userAvailabilities as $userAvailability) {
            $userAvailabilityTime = $userAvailability->getUserAvailabilityforTime($timeFrom, $timeTo);
            $userName             = $userAvailability->getUserName();

            $messageRows[] = $userAvailabilityTime->toString($userName, $timeFrom, $timeTo);
        }

        if (empty($messageRows)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()
                ->setContent(
                    'Woah! I can\'t find shit.' . PHP_EOL . PHP_EOL .
                    'Please use the `/available` or `/unavailable` command to add yourself.'
                ),
                true
            );
        } else {
            $interaction->respondWithMessage(
                MessageBuilder::new()
                ->setContent(implode(PHP_EOL, $messageRows)),
                true
            );
        }
    }
}
