<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\User\User;
use Grandeljay\Availability\Bot;

class Availability extends Bot
{
    public function __construct()
    {
        parent::__construct();
    }

    public function run(): void
    {
        $this->discord->listenCommand(
            strtolower(Command::AVAILABILITY),
            function (Interaction $interaction) {
                $messageRows = array();

                $availabilities = \Grandeljay\Availability\Availability::getAll();

                foreach ($availabilities as $availabilitiesStack) {
                    $availabilitiesStack = array_filter(
                        $availabilitiesStack,
                        function (array $availabilityData) {
                            $availability = new \Grandeljay\Availability\Availability($availabilityData);

                            return !$availability->isInPast();
                        }
                    );

                    if (empty($availabilitiesStack)) {
                        $timeDefault = Bot::getTimeFromString(Bot::DATE_DEFAULT);

                        $availability = new \Grandeljay\Availability\Availability();
                        $availability->setUser($interaction->user);
                        $availability->setAvailability(true, true);
                        $availability->setTime($timeDefault);

                        $availabilitiesStack[] = $availability->toArray();
                    }
                }

                foreach ($availabilities as $availabilitiesStack) {
                    usort(
                        $availabilitiesStack,
                        function ($availabilityA, $availabilityB) {
                            return $availabilityA['userAvailabilityTime'] <=> $availabilityB['userAvailabilityTime'];
                        }
                    );

                    $availabilityClosest = new \Grandeljay\Availability\Availability(reset($availabilitiesStack));

                    $messageRows[] = $availabilityClosest->toString();
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
