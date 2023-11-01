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

        $messageRows  = array(
            \sprintf(
                'Showing availabilities for all users on `%s` at `%s` (until `%s` at `%s`).',
                date('d.m.Y', $timeFrom),
                date('H:i', $timeFrom),
                date('d.m.Y', $timeTo),
                date('H:i', $timeTo)
            ),
            '',
        );
        $messageTable = array(
            array(
                'icon'   => '',
                'name'   => 'User',
                'status' => 'Status',
                'from'   => 'From',
                'to'     => 'To',
            ),
            array(
                'icon'   => '-',
                'name'   => '-',
                'status' => '-',
                'from'   => '-',
                'to'     => '-',
            ),
        );

        $userAvailabilities = UserAvailabilities::getAll();

        foreach ($userAvailabilities as $userAvailability) {
            $userAvailabilityTime = $userAvailability->getUserAvailabilityforTime($timeFrom, $timeTo);
            $userIsAvailableFrom  = $userAvailabilityTime->getUserIsAvailableFrom($timeFrom);
            $userIsAvailableTo    = $userAvailabilityTime->getUserIsAvailableTo($timeTo);
            $userIsAvailable      = $userIsAvailableFrom || $userIsAvailableTo;

            $userIcon       = $userIsAvailable ? 'Y' : 'N';
            $userName       = $userAvailability->getUserName();
            $userStatus     = $userIsAvailable ? 'Available' : 'Unavailable';
            $userStatusFrom = '';
            $userStatusTo   = '';

            if ($userIsAvailable) {
                $userStatusFrom = date('d.m.Y H:i', $userAvailabilityTime->getUserAvailabilityTimeFrom());
                $userStatusTo   = date('d.m.Y H:i', $userAvailabilityTime->getUserAvailabilityTimeTo());
            }

            $messageTable[] = array(
                'icon'   => $userIcon,
                'name'   => $userName,
                'status' => $userStatus,
                'from'   => $userStatusFrom ?: '',
                'to'     => $userStatusTo   ?: '',
            );
        }

        $pad = array();

        foreach ($messageTable as $messageRow) {
            foreach ($messageRow as $key => $value) {
                if (isset($pad[$key])) {
                    $pad[$key] = max($pad[$key], \mb_strlen($value));
                } else {
                    $pad[$key] = \mb_strlen($value);
                }
            }
        }

        foreach ($messageTable as $index => &$messageRow) {
            foreach ($pad as $column => $amount) {
                if (1 === $index) {
                    $messageRow[$column] = \str_pad($messageRow[$column], $amount, '-');
                } else {
                    $messageRow[$column] = \str_pad($messageRow[$column], $amount);
                }
            }
        }

        if (2 === count($messageTable)) {
            $interaction->respondWithMessage(
                MessageBuilder::new()
                ->setContent(
                    'Woah! I can\'t find shit.' . PHP_EOL . PHP_EOL .
                    'Please use the `/available` or `/unavailable` command to add yourself.'
                ),
                true
            );
        } else {
            $messageRows[] = '```';

            foreach ($messageTable as $columns) {
                $row = '| ';

                foreach ($columns as $value) {
                    $row .= $value . ' | ';
                }

                $messageRows[] = $row;
            }

            $messageRows[] = '```';

            $interaction->respondWithMessage(
                MessageBuilder::new()
                ->setContent(implode(PHP_EOL, $messageRows)),
                true
            );
        }
    }
}
