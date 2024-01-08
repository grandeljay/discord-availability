<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\{Bot, UserAvailabilities, Config};

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
        $config = new Config();

        $timeFromText = $interaction->data->options['from']->value ?? '';
        $timeToText   = $interaction->data->options['to']->value   ?? '';

        if (empty($timeToText)) {
            $timeFrom = Bot::getTimeFromString($timeFromText);
            $timeTo   = $timeFrom + 3600 * 4;
        } elseif (!empty($timeToText) && empty($timeFromText)) {
            $timeTo   = Bot::getTimeFromString($timeToText);
            $timeFrom = $timeTo - 3600 * 4;
        } else {
            $timeFrom = Bot::getTimeFromString($timeFromText);
            $timeTo   = Bot::getTimeFromString($timeToText);
        }

        $timeDefault   = Bot::getTimeFromString($config->getDefaultDateTime());
        $timeIsDefault = $timeFrom === $timeDefault;
        $timeIsMore    = false;
        $timeIsLess    = false;

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

        $guild              = $interaction->guild;
        $userAvailabilities = UserAvailabilities::getAll();

        foreach ($userAvailabilities as $userAvailability) {
            $userAvailabilityTime      = $userAvailability->getUserAvailabilityforTime($timeFrom, $timeTo);
            $userIsAvailableFrom       = $userAvailabilityTime->getUserIsAvailableFrom($timeFrom);
            $userIsAvailableTo         = $userAvailabilityTime->getUserIsAvailableTo($timeTo);
            $userIsAvailable           = $userAvailabilityTime->getUserIsAvailable();
            $userIsAvailablePerDefault = $userAvailabilityTime->getUserIsAvailablePerDefault();

            $userIcon       = $userIsAvailable ? 'Y' : 'N';
            $userName       = $userAvailability->getUserName();
            $userStatus     = $userIsAvailable ? 'Available' : 'Unavailable';
            $outputTimeFrom = max($timeFrom, $userAvailabilityTime->getUserAvailabilityTimeFrom());
            $outputTimeTo   = min($timeTo, $userAvailabilityTime->getUserAvailabilityTimeTo());
            $userStatusFrom = date('H:i', $outputTimeFrom);
            $userStatusTo   = date('H:i', $outputTimeTo);

            if ($userAvailabilityTime->getUserAvailabilityTimeFrom() < $timeFrom) {
                $timeIsLess = true;

                $userStatusFrom = '<' . $userStatusFrom;
            }

            if ($userAvailabilityTime->getUserAvailabilityTimeTo() > $timeTo) {
                $timeIsMore = true;

                $userStatusTo = '>' . $userStatusTo;
            }

            /** Get user */
            $member   = $guild->members->get('id', $userAvailability->getUserId());
            $userName = $member->nick ?? $member->user->username ?? $userAvailability->getUserName();

            /** Truncate name */
            $userNameMaxLength = 19;

            if (\mb_strlen($userName) > $userNameMaxLength) {
                $dots       = '...';
                $dotsLength = \mb_strlen($dots);

                $userName = \substr($userName, 0, $userNameMaxLength - $dotsLength) . $dots;
            }

            /** Output */
            if ($userIsAvailablePerDefault) {
                $userIcon       = '-';
                $userStatus    .= '*';
                $userStatusFrom = \date('H:i', $timeFrom);
                $userStatusTo   = \date('H:i', $timeTo);
            }

            $messageTable[] = array(
                'icon'   => $userIcon,
                'name'   => $userName,
                'status' => $userStatus,
                'from'   => $userStatusFrom,
                'to'     => $userStatusTo,
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
                    switch ($column) {
                        case 'from':
                        case 'to':
                            $messageRow[$column] = \str_pad($messageRow[$column], $amount, ' ', \STR_PAD_LEFT);
                            break;

                        default:
                            $messageRow[$column] = \str_pad($messageRow[$column], $amount);
                            break;
                    }
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

            if ($timeIsDefault) {
                $messageRows[] = '`*` = The user is available per default and did not explicitly specify his availability.';
            }

            if ($timeIsLess) {
                $messageRows[] = '`<` = The user\'s _From_ availability starts earlier than displayed.';
            }

            if ($timeIsMore) {
                $messageRows[] = '`>` = The user\'s _To_ availability is later than displayed.';
            }

            $interaction->respondWithMessage(
                MessageBuilder::new()
                ->setContent(implode(PHP_EOL, $messageRows)),
                true
            );
        }
    }
}
