<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\{CommandBuilder, MessageBuilder};
use Discord\Discord;
use Discord\Parts\Interactions\Command\Option;
use Grandeljay\Availability\Bot;

class Command extends Bot
{
    public const AVAILABLE    = 'Available';
    public const AVAILABILITY = 'Availability';
    public const UNAVAILABLE  = 'Unavailable';

    /**
     * Runs a command
     *
     * @param string $command The command to run.
     *
     * @return void
     */
    public static function run(string $command): void
    {
        $commandClassName = __NAMESPACE__ . '\\' . $command;
        $commandClass     = new $commandClassName();
        $commandClass->run();
    }

    /**
     * Construct
     *
     * @param string $command     The command to add and listen for.
     * @param string $description Description for the command.
     */
    public function __construct(string $command, string $description)
    {
        parent::__construct();

        $this->discord->on(
            'ready',
            function (Discord $discord) use ($command, $description) {
                /**
                 * When the bot is ready, attempt to create a global slash
                 * command. After the command was successfully created, please
                 * remove this code.
                 *
                 * @see https://github.com/discord-php/DiscordPHP/wiki/Slash-Command
                 */
                $commandObject = CommandBuilder::new()
                ->setName(strtolower($command))
                ->setDescription($description);

                switch ($command) {
                    case Command::AVAILABLE:
                        $option = new Option($discord);
                        $option
                        ->setType(Option::STRING)
                        ->setName('date')
                        ->setDescription('When will you be available?')
                        ->setRequired(true);

                        $commandObject->addOption($option);
                        break;

                    case Command::UNAVAILABLE:
                        $option = new Option($discord);
                        $option
                        ->setType(Option::STRING)
                        ->setName('date')
                        ->setDescription('When will you be unavailable?')
                        ->setRequired(true);

                        $commandObject->addOption($option);
                        break;

                    default:
                        # code...
                        break;
                }

                $discord->application->commands->save(
                    $discord->application->commands->create($commandObject->toArray())
                );
            }
        );

        self::run($command);
    }
}
