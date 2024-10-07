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
        $callback = [$this, 'getUsersAvailabilities'];

        $discord->listenCommand($command, $callback);
    }

    public function getUsersAvailabilities(Interaction $interaction): void
    {
        $config = new Config();

        $requestedDateTime     = $this->getRequestedDateTime($interaction);
        $requestedDateTimeFrom = $requestedDateTime['from'];
        $requestedDateTimeTo   = $requestedDateTime['to'];

        $availabilityTimeDefault = Bot::getTimeFromString($config->getDefaultDateTime());
        $requestedTimeIsDefault  = $requestedDateTimeFrom === $availabilityTimeDefault;
        $requestedTimeIsMore     = false;
        $requestedTimeIsLess     = false;

        if (false === $requestedDateTimeFrom || false === $requestedDateTimeTo) {
            Bot::respondCouldNotParseTime($interaction);

            return;
        }

        $messageRows = [
            \sprintf(
                'Showing availabilities for all users on `%s` at `%s` (until `%s` at `%s`).',
                date('d.m.Y', $requestedDateTimeFrom),
                date('H:i', $requestedDateTimeFrom),
                date('d.m.Y', $requestedDateTimeTo),
                date('H:i', $requestedDateTimeTo)
            ),
        ];

        $messageTable = [
            [
                'icon'   => '',
                'name'   => 'User',
                'status' => 'Status',
                'from'   => 'From',
                'to'     => 'To',
            ],
            [
                'icon'   => '-',
                'name'   => '-',
                'status' => '-',
                'from'   => '-',
                'to'     => '-',
            ],
        ];

        $guild              = $interaction->guild;
        $userAvailabilities = UserAvailabilities::getAll();
        $usersSkipped       = 0;

        foreach ($userAvailabilities as $userAvailability) {
            $userAvailabilityTime       = $userAvailability->getUserAvailabilityforTime($requestedDateTimeFrom, $requestedDateTimeTo);
            $userAvailabilityIsRelevant = $userAvailability->isRelevant($requestedDateTimeFrom);
            $userIsAvailableFrom        = $userAvailabilityTime->getUserIsAvailableFrom($requestedDateTimeFrom);
            $userIsAvailableTo          = $userAvailabilityTime->getUserIsAvailableTo($requestedDateTimeTo);
            $userIsAvailable            = $userAvailabilityTime->getUserIsAvailable();
            $userIsAvailablePerDefault  = $userAvailabilityTime->getUserIsAvailablePerDefault();

            if (!$userAvailabilityIsRelevant) {
                $usersSkipped++;
                continue;
            }

            $userIcon       = $userIsAvailable ? 'Y' : 'N';
            $userName       = $userAvailability->getUserName();
            $userStatus     = $userIsAvailable ? 'Available' : 'Unavailable';
            $outputTimeFrom = max($requestedDateTimeFrom, $userAvailabilityTime->getUserAvailabilityTimeFrom());
            $outputTimeTo   = min($requestedDateTimeTo, $userAvailabilityTime->getUserAvailabilityTimeTo());
            $userStatusFrom = date('H:i', $outputTimeFrom);
            $userStatusTo   = date('H:i', $outputTimeTo);

            if ($userAvailabilityTime->getUserAvailabilityTimeFrom() < $requestedDateTimeFrom) {
                $requestedTimeIsLess = true;

                $userStatusFrom = '<' . $userStatusFrom;
            }

            if ($userAvailabilityTime->getUserAvailabilityTimeTo() > $requestedDateTimeTo) {
                $requestedTimeIsMore = true;

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
                $userStatusFrom = \date('H:i', $requestedDateTimeFrom);
                $userStatusTo   = \date('H:i', $requestedDateTimeTo);
            }

            $messageTable[] = [
                'icon'   => $userIcon,
                'name'   => $userName,
                'status' => $userStatus,
                'from'   => $userStatusFrom,
                'to'     => $userStatusTo,
            ];
        }

        if ($usersSkipped > 0) {
            if (1 === $usersSkipped) {
                $messageRows[] = \sprintf('%d user has been skipped and determined irrelevant for this query.', $usersSkipped);
            } elseif ($usersSkipped >= 2) {
                $messageRows[] = \sprintf('%d users have been skipped and determined as irrelevant for this query.', $usersSkipped);
            }
        }

        $pad = [];

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

            if ($requestedTimeIsDefault) {
                $messageRows[] = '`*` = The user is available per default and did not explicitly specify his availability.';
            }

            if ($requestedTimeIsLess) {
                $messageRows[] = '`<` = The user\'s _From_ availability starts earlier than displayed.';
            }

            if ($requestedTimeIsMore) {
                $messageRows[] = '`>` = The user\'s _To_ availability is later than displayed.';
            }

            $interaction->respondWithMessage(
                MessageBuilder::new()
                ->setContent(implode(PHP_EOL, $messageRows)),
                true
            );
        }
    }

    private function getRequestedDateTime(Interaction $interaction): array
    {
        $requestedTimeTextFrom = $interaction->data->options['from']->value ?? '';
        $requestedTimeTextTo   = $interaction->data->options['to']->value   ?? '';

        $requestedDateTimeFrom = $requestedTimeTextFrom ? \strtotime($requestedTimeTextFrom) : \strtotime('today 00:00');
        $requestedDateTimeTo   = $requestedTimeTextTo   ? \strtotime($requestedTimeTextTo)   : \strtotime('today 23:59');

        $requestedDateTime = [
            'from' =>  $requestedDateTimeFrom,
            'to'   =>  $requestedDateTimeTo,
        ];

        return $requestedDateTime;
    }
}
