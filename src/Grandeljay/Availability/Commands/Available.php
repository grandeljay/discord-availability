<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Discord\Repository\Interaction\OptionRepository;
use Grandeljay\Availability\Bot;
use Grandeljay\Availability\Config;
use Grandeljay\Availability\UserAvailability;
use Grandeljay\Availability\UserAvailabilityTime;

class Available extends Command
{
    public function run(Discord $discord): void
    {
        $command  = strtolower(Command::AVAILABLE);
        $callback = [$this, 'command'];

        $discord->listenCommand($command, $callback);
    }

    private function getOptions(Interaction $interaction): OptionRepository
    {
        return $interaction->data->options;
    }

    private function parseOptions(OptionRepository $options): array
    {
        $timeFromText = $options['from']->value ?? '';
        $timeToText   = $options['to']->value   ?? '';

        if (empty($timeToText)) {
            $timeAvailabilityFrom = Bot::getTimeFromString($timeFromText);
            $timeAvailabilityTo   = $timeAvailabilityFrom + 3600 * 4;
        } elseif (!empty($timeToText) && empty($timeFromText)) {
            $timeAvailabilityTo   = Bot::getTimeFromString($timeToText);
            $timeAvailabilityFrom = $timeAvailabilityTo - 3600 * 4;
        } else {
            $timeAvailabilityFrom = Bot::getTimeFromString($timeFromText);
            $timeAvailabilityTo   = Bot::getTimeFromString($timeToText);
        }

        return [
            'from' => $timeAvailabilityFrom,
            'to'   => $timeAvailabilityTo,
        ];
    }

    private function validateOptions(Interaction $interaction, array $parsedOptions): bool
    {
        $timeAvailableFrom = $parsedOptions['from'];
        $timeAvailableTo   = $parsedOptions['to'];

        if (false === $timeAvailableFrom || false === $timeAvailableTo) {
            Bot::respondCouldNotParseTime($interaction);

            return false;
        }

        return true;
    }

    private function setAvailability(Interaction $interaction, int $from, int $to): void
    {
        $userAvailabilityTime = new UserAvailabilityTime();
        $userAvailabilityTime->setAvailability(true);
        $userAvailabilityTime->setTimeFrom($from);
        $userAvailabilityTime->setTimeTo($from);
        $userAvailabilityTime->setAvailablePerDefault(false);

        if ($userAvailabilityTime->isInPast()) {
            $interaction
            ->respondWithMessage(
                MessageBuilder::new()
                ->setContent(
                    sprintf(
                        'You\'re available on `%s` at `%s`? That doesn\'t sound right. Please specify a time in the future.',
                        date('d.m.Y', $from),
                        date('H:i', $from),
                    )
                )
                ->_setFlags(Message::FLAG_EPHEMERAL)
            );

            return;
        }

        $userAvailability = UserAvailability::get($interaction->user);
        $userAvailability->addAvailability($userAvailabilityTime);
        $userAvailability->save();

        $config = new Config();

        $interaction
        ->respondWithMessage(
            MessageBuilder::new()
            ->setContent(
                sprintf(
                    'Gotcha! You are **available** for **%s** on `%s` at `%s` (until `%s` at `%s`).',
                    $config->getEventName(),
                    date('d.m.Y', $from),
                    date('H:i', $from),
                    date('d.m.Y', $to),
                    date('H:i', $to)
                )
            )
            ->_setFlags(Message::FLAG_EPHEMERAL)
        );

        $userAvailabilityTime->promptIfAvailableNow($this->discord, $interaction);
    }

    public function command(Interaction $interaction): void
    {
        $options         = $this->getOptions($interaction);
        $optionsParsed   = $this->parseOptions($options);
        $optionsAreValid = $this->validateOptions($interaction, $optionsParsed);

        if (!$optionsAreValid) {
            return;
        }

        $this->setAvailability($interaction, $optionsParsed['from'], $optionsParsed['to']);
    }
}
