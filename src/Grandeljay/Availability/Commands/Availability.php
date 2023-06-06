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
                $availabilities = $this->getAvailabilities();
                $messageRows    = array();

                foreach ($availabilities as $availability) {
                    if ($availability['userAvailabilityTime'] <= time()) {
                        $availability['userAvailabilityTime']      = Bot::getTimeFromString(Bot::DATE_DEFAULT);
                        $availability['userIsAvailable']           = true;
                        $availability['userIsAvailablePerDefault'] = true;
                    }

                    $availabilityText    = $availability['userIsAvailable'] ? 'available' : 'unavailable';
                    $availabilityEmoji   = $availability['userIsAvailable'] ? ':star_struck:' : ':angry:';
                    $availablePerDefault = $availability['userIsAvailablePerDefault'] ? ' (per default)' : '';

                    $messageRows[] = sprintf(
                        '- %s %s is %s on `%s` at `%s`%s',
                        $availabilityEmoji,
                        $availability['userName'],
                        $availabilityText,
                        date('d.m.Y', $availability['userAvailabilityTime']),
                        date('H:i', $availability['userAvailabilityTime']),
                        $availablePerDefault
                    );
                }

                if (empty($messageRows)) {
                    $interaction->respondWithMessage(
                        MessageBuilder::new()
                        ->setContent(
                            'Woah! I can\'t find shit.' . PHP_EOL . PHP_EOL .
                            'Please use the `/available` or `/unavailable` command to add yourself.'
                        )
                    );
                } else {
                    $interaction->respondWithMessage(
                        MessageBuilder::new()
                        ->setContent(implode(PHP_EOL, $messageRows))
                    );
                }
            }
        );
    }
}
