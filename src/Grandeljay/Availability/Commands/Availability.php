<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\Bot;
use Grandeljay\Availability\UserAvailabilities;
use Grandeljay\Availability\UserAvailability;
use Grandeljay\Availability\UserAvailabilityTime;

class Availability extends Bot
{
    public function __construct()
    {
        parent::__construct();
    }

    public function run(): void
    {
        $this->userAvailabilities = UserAvailabilities::getAll();

        $this->discord->listenCommand(
            strtolower(Command::AVAILABILITY),
            function (Interaction $interaction) {
                $messageRows = array();

                foreach ($this->userAvailabilities as $userAvailability) {
                    $userAvailabilityTimes = $userAvailability->getUserAvailabilityTimes()->jsonSerialize();

                    usort(
                        $userAvailabilityTimes,
                        function ($availabilityDataA, $availabilityDataB) {
                            $availabilityA = new UserAvailabilityTime($availabilityDataA);
                            $availabilityB = new UserAvailabilityTime($availabilityDataB);

                            return $availabilityA->getUserAvailabilityTime() <=> $availabilityB->getUserAvailabilityTime();
                        }
                    );

                    $userAvailabilityTimeClosest = $this->getClosestAvailability(
                        $userAvailabilityTimes,
                        $interaction->data->options['date']->value ?? 'now'
                    );

                    $messageRows[] = $userAvailabilityTimeClosest->toString($interaction->user);
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

    private function getClosestAvailability(array $userAvailabilityTimes, string $userAvailabilityTimeText): UserAvailabilityTime
    {
        $closestuserAvailabilityTime           = null;
        $closestuserAvailabilityTimeDifference = null;

        $userAvailabilityTimeTarget = Bot::getTimeFromString($userAvailabilityTimeText);

        foreach ($userAvailabilityTimes as $userAvailabilityTimeData) {
            $userAvailabilityTime = new UserAvailabilityTime($userAvailabilityTimeData);

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
