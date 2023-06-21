<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\CommandBuilder;
use Discord\Discord;
use Discord\Parts\Interactions\Command\Option;

class Command
{
    private Discord $discord;
    private string $description;

    protected string $name;

    public const AVAILABLE    = 'Available';
    public const AVAILABILITY = 'Availability';
    public const UNAVAILABLE  = 'Unavailable';
    public const SHUTDOWN     = 'Shutdown';

    /**
     * Construct
     *
     * @param string $command     The command to add and listen for.
     * @param string $description Description for the command.
     */
    public function __construct(Discord $discord, string $command, string $description)
    {
        $this->discord     = $discord;
        $this->name        = $command;
        $this->description = $description;

        /**
         * When the bot is ready, attempt to create a global slash
         * command. After the command was successfully created, please
         * remove this code.
         *
         * @see https://github.com/discord-php/DiscordPHP/wiki/Slash-Command
         */
        $commandBuilder = CommandBuilder::new()
        ->setName(strtolower($this->name))
        ->setDescription($this->description);

        switch ($this->name) {
            case Command::AVAILABILITY:
                $option = new Option($this->discord);
                $option
                ->setType(Option::STRING)
                ->setName('date')
                ->setDescription('Check user availability for date/time. Leave empty to check for monday.');

                $commandBuilder->addOption($option);
                break;

            case Command::AVAILABLE:
                $option = new Option($this->discord);
                $option
                ->setType(Option::STRING)
                ->setName('date')
                ->setDescription('When will you be available?')
                ->setRequired(true);

                $commandBuilder->addOption($option);
                break;

            case Command::AVAILABLE:
                $option = new Option($this->discord);
                $option
                ->setType(Option::STRING)
                ->setName('date')
                ->setDescription('When will you be available?')
                ->setRequired(true);

                $commandBuilder->addOption($option);
                break;

            case Command::UNAVAILABLE:
                $option = new Option($this->discord);
                $option
                ->setType(Option::STRING)
                ->setName('date')
                ->setDescription('When will you be unavailable?')
                ->setRequired(true);

                $commandBuilder->addOption($option);
                break;
        }

        $commandArray  = $commandBuilder->toArray();
        $commandObject = $this->discord->application->commands->create($commandArray);

        $this->discord->application->commands->save($commandObject);
    }

    /**
     * Returns the current command.
     *
     * @return void
     */
    public function get(): self
    {
        $commandClassName = __NAMESPACE__ . '\\' . $this->name;
        $commandClass     = new $commandClassName($this->discord, $this->name, $this->description);

        return $commandClass;
    }
}
