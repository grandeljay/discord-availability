<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\Bot;

class Shutdown extends Bot
{
    public function __construct()
    {
        parent::__construct();
    }

    public function run(): void
    {
        $this->discord->listenCommand(
            strtolower(Command::SHUTDOWN),
            function (Interaction $interaction) {
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
                        function () {
                            $this->discord->close();
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
}
