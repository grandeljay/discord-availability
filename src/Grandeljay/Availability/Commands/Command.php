<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\CommandBuilder;
use Discord\Discord;
use Discord\Parts\Interactions\Command\Option;
use Grandeljay\Availability\Config;

class Command
{
    private string $description;

    protected Discord $discord;
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
         * @var `$argv` The native, global PHP variable with all startup
         *      arguments.
         * @see https://github.com/discord-php/DiscordPHP/wiki/Slash-Command
         */
        global $argv;

        foreach ($argv as $commandLineArgument) {
            if ('--install' === $commandLineArgument) {
                $this->addAll();

                break;
            }
        }
    }

    private function addAll(): void
    {
        $config = new Config();

        $commandBuilder = CommandBuilder::new()
        ->setName(strtolower($this->name))
        ->setDescription($this->description);

        switch ($this->name) {
            case Command::AVAILABILITY:
                $optionFrom = new Option($this->discord);
                $optionFrom
                ->setType(Option::STRING)
                ->setName('from')
                ->setDescription(
                    \sprintf(
                        'Check user availability for date/time. Leave empty to check for %s.',
                        $config->getDefaultDay()
                    )
                )
                ->setRequired(false);

                $optionTo = new Option($this->discord);
                $optionTo
                ->setType(Option::STRING)
                ->setName('to')
                ->setDescription('Date/time to include availabilities. Leave empty to check for default.')
                ->setRequired(false);

                $commandBuilder->addOption($optionFrom);
                $commandBuilder->addOption($optionTo);
                break;

            case Command::AVAILABLE:
                $optionFrom = new Option($this->discord);
                $optionFrom
                ->setType(Option::STRING)
                ->setName('from')
                ->setDescription('When will you be available?')
                ->setRequired(true);

                $optionTo = new Option($this->discord);
                $optionTo
                ->setType(Option::STRING)
                ->setName('to')
                ->setDescription('Until when will you be available?')
                ->setRequired(false);

                $commandBuilder->addOption($optionFrom);
                $commandBuilder->addOption($optionTo);
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
