<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;

class Shutdown extends Command
{
    public function run(Discord $discord): void
    {
        $discord->listenCommand(
            strtolower(Command::SHUTDOWN),
            function (Interaction $interaction) use ($discord) {
                /**
                 * Using
                 * `isset($interaction->member->permissions->administrator)`
                 * always returns `false`, even when the `administrator`
                 * property is set to `true` which is why some warnings are
                 * currently being generated.
                 */
                $userIsAdministrator = true === $interaction->member->permissions->administrator;

                if ($userIsAdministrator) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()
                        ->setContent('Alright everybody, I\'m out. Bot commands such as `/availability` will no longer work until I\'m back.')
                    )
                    ->done(
                        function () use ($discord) {
                            $discord->close();
                        }
                    );
                } else {
                    $interaction->respondWithMessage(
                        MessageBuilder::new()
                        ->setContent('You must be an administrator to shutdown the bot (d\'uh).'),
                        true
                    );
                }
            }
        );
    }

    private function closeOnComplete()
    {
    }
}
