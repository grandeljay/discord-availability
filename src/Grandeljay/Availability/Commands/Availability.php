<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\Bot;
use Grandeljay\Availability\UserAvailabilities;
use Grandeljay\Availability\UserAvailabilityTime;

class Availability extends Bot
{
    public function __construct()
    {
        parent::__construct();
    }

    public function run(): void
    {
        $this->userAvailabilities = UserAvailabilities::getAll($this->config);

        $this->discord->listenCommand(
            strtolower(Command::AVAILABILITY),
            function (Interaction $interaction) {
                $messageRows = array();

                foreach ($this->userAvailabilities as $userAvailability) {
                    $userAvailabilityTimes = array_filter(
                        $userAvailability->getUserAvailabilityTimes()->jsonSerialize(),
                        function ($userAvailabilityTimeData) {
                            $userAvailabilityTime = new UserAvailabilityTime($userAvailabilityTimeData);

                            return !$userAvailabilityTime->isInPast();
                        }
                    );

                    usort(
                        $userAvailabilityTimes,
                        function ($availabilityDataA, $availabilityDataB) {
                            $availabilityA = new UserAvailabilityTime($availabilityDataA);
                            $availabilityB = new UserAvailabilityTime($availabilityDataB);

                            return $availabilityA->getUserAvailabilityTime() <=> $availabilityB->getUserAvailabilityTime();
                        }
                    );

                    $userAvailabilityTimeDataClosest = reset($userAvailabilityTimes);
                    $userAvailabilityTimeClosest     = new UserAvailabilityTime($userAvailabilityTimeDataClosest);

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
}
