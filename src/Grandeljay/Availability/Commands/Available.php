<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\Availability;

class Available extends Availability
{
    public function __construct()
    {
        parent::__construct();
    }

    public function run(): void
    {
        $this->discord->listenCommand(
            strtolower(Command::AVAILABLE),
            function (Interaction $interaction) {
                $timeAvailable = strtotime($interaction->data->options['date']->value);

                if (false === $timeAvailable) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()->setContent('Sorry, I couldn\'t parse that. Could you please specify a more machine friendly time?')
                    );

                    return;
                }

                if (time() >= $timeAvailable) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()->setContent(
                            sprintf(
                                'You\'re available on `%s`? That\'s in the past, silly! Please specify a time in the future.',
                                date('d.m.Y', $timeAvailable)
                            )
                        )
                    );

                    return;
                }

                $this->setUserAvailability($interaction->user->id, true, $timeAvailable);

                $interaction
                ->respondWithMessage(
                    MessageBuilder::new()->setContent(
                        sprintf(
                            'Gotcha! You are **available** for Dota on `%s`.',
                            date('d.m.Y', $timeAvailable)
                        )
                    )
                );
            }
        );
    }
}
