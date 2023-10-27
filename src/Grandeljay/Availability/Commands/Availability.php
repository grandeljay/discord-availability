<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\{Bot, Config, UserAvailability, UserAvailabilities, UserAvailabilityTimes, UserAvailabilityTime};

class Availability extends Command
{
    public function run(Discord $discord): void
    {
        $discord->listenCommand(
            strtolower(Command::AVAILABILITY),
            function (Interaction $interaction) {
                $config            = new Config();
                $time              = \strtotime($interaction->data->options['date']->value ?? $config->getDefaultDateTime());
                $timeThreeHoursAgo = $time - UserAvailability::TIME_PAST;
                $timeInSixHours    = $time + UserAvailability::TIME_FUTURE;
                $messageRows       = array(
                    \sprintf(
                        'Showing availabilities for all users on **%s** (`%s`) between `%s` and `%s`.',
                        date('l', $time),
                        date('d.m.Y', $time),
                        date('H:i', $timeThreeHoursAgo),
                        date('H:i', $timeInSixHours)
                    ),
                    '',
                );

                $userAvailabilities = UserAvailabilities::getAll();

                foreach ($userAvailabilities as $userAvailability) {
                    $userAvailabilityTime = $userAvailability->getUserAvailabilityTimeforTime($time);
                    $userName             = $userAvailability->getUserName();

                    $messageRows[] = $userAvailabilityTime->toString($userName);
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
        );
    }

    private function getClosestAvailability(UserAvailabilityTimes $userAvailabilityTimes, string $userAvailabilityTimeText): UserAvailabilityTime
    {
        $closestuserAvailabilityTime           = null;
        $closestuserAvailabilityTimeDifference = null;

        $userAvailabilityTimeTarget = Bot::getTimeFromString($userAvailabilityTimeText);

        foreach ($userAvailabilityTimes as $userAvailabilityTime) {
            $time       = $userAvailabilityTime->getTime();
            $difference = abs($userAvailabilityTimeTarget - $time);

            if (null === $closestuserAvailabilityTimeDifference || $difference < $closestuserAvailabilityTimeDifference) {
                $closestuserAvailabilityTimeDifference = $difference;
                $closestuserAvailabilityTime           = $userAvailabilityTime;
            }
        }

        return $closestuserAvailabilityTime;
    }
}
