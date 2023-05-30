<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\User\User;

class Availability extends \Grandeljay\Availability\Availability
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
                    $user = $this->discord->users->get('id', $availability['userId']);

                    if (!$user instanceof User) {
                        continue;
                    }

                    if ($availability['userAvailabilityTime'] <= time()) {
                        $availability['userAvailabilityTime'] = Availability::getTimeFromString(Availability::DATE_DEFAULT);
                        $availability['userIsAvailable']      = true;
                    }

                    $userLocale        = $user->locale ?? \Locale::getDefault();
                    $dateFormatter     = new \IntlDateFormatter($userLocale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
                    $availabilityText  = $availability['userIsAvailable'] ? 'available' : 'unavailable';
                    $availabilityEmoji = $availability['userIsAvailable'] ? ':star_struck:' : ':angry:';

                    $messageRows[] = sprintf(
                        '- %s %s is %s on `%s` at `%s`',
                        $availabilityEmoji,
                        $user->username,
                        $availabilityText,
                        $dateFormatter->format($availability['userAvailabilityTime']),
                        date('H:i', $availability['userAvailabilityTime'])
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
