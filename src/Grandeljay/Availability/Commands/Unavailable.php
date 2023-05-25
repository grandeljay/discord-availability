<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\Availability;

class Unavailable extends Availability
{
    public function __construct()
    {
        parent::__construct();
    }

    public function run(): void
    {
        $this->discord->listenCommand(
            strtolower(Command::UNAVAILABLE),
            function (Interaction $interaction) {
                $timeUnavailable = strtotime($interaction->data->options['date']->value);

                if (false === $timeUnavailable) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()->setContent('Sorry, I couldn\'t parse that. Could you please specify a more machine friendly time?')
                    );

                    return;
                }

                if (time() >= $timeUnavailable) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()->setContent(
                            sprintf(
                                'You\'re unavailable on `%s`? That\'s in the past, silly! Please specify a time in the future.',
                                date('d.m.Y', $timeUnavailable)
                            )
                        )
                    );

                    return;
                }

                $this->setUserAvailability($interaction->user->id, false, $timeUnavailable);

                $interaction
                ->respondWithMessage(
                    MessageBuilder::new()->setContent(
                        sprintf(
                            'Gotcha! You are **unavailable** for Dota on `%s`.',
                            date('d.m.Y', $timeUnavailable)
                        )
                    )
                );
            }
        );
    }
}
