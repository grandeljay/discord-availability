<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\{Bot, Config, UserAvailabilities, UserAvailabilityTimes, UserAvailabilityTime};

class Availability extends Command
{
    public function run(Discord $discord): void
    {
        $discord->listenCommand(
            strtolower(Command::AVAILABILITY),
            function (Interaction $interaction) {
                $config      = new Config();
                $messageRows = array();

                $this->userAvailabilities = UserAvailabilities::getAll($this->logger);

                foreach ($this->userAvailabilities as $userAvailability) {
                    $userAvailabilityTimes = $userAvailability->getUserAvailabilityTimes();

                    $userAvailabilityTimeClosest = $this->getClosestAvailability(
                        $userAvailabilityTimes,
                        $interaction->data->options['date']->value ?? $config->getDefaultDateTime()
                    );

                    $userName = $userAvailability->getUserName();

                    $messageRows[] = $userAvailabilityTimeClosest->toString($userName);
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
