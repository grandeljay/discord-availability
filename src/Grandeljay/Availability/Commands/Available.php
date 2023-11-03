<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\Components\{Button, ActionRow};
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\{Bot, Config, UserAvailability, UserAvailabilityTime};

class Available extends Command
{
    public function run(Discord $discord): void
    {
        $command  = strtolower(Command::AVAILABLE);
        $callback = array($this, 'setUserAvailability');

        $discord->listenCommand($command, $callback);
    }

    public function setUserAvailability(Interaction $interaction): void
    {
        $timeFromText = $interaction->data->options['from']->value ?? '';
        $timeToText   = $interaction->data->options['to']->value   ?? '';

        if (empty($timeToText)) {
            $timeAvailableFrom = Bot::getTimeFromString($timeFromText);
            $timeAvailableTo   = $timeAvailableFrom + 3600 * 4;
        } elseif (!empty($timeToText) && empty($timeFromText)) {
            $timeAvailableTo   = Bot::getTimeFromString($timeToText);
            $timeAvailableFrom = $timeAvailableTo - 3600 * 4;
        } else {
            $timeAvailableFrom = Bot::getTimeFromString($timeFromText);
            $timeAvailableTo   = Bot::getTimeFromString($timeToText);
        }

        if (false === $timeAvailableFrom || false === $timeAvailableTo) {
            $interaction
            ->respondWithMessage(
                MessageBuilder::new()
                ->setContent('Sorry, I couldn\'t parse that. Could you please specify a more machine friendly time?')
                ->_setFlags(Message::FLAG_EPHEMERAL)
            );

            return;
        }

        $userAvailabilityTime = new UserAvailabilityTime();
        $userAvailabilityTime->setTimeFrom($timeAvailableFrom);
        $userAvailabilityTime->setTimeTo($timeAvailableTo);
        $userAvailabilityTime->setAvailablePerDefault(false);

        if ($userAvailabilityTime->isInPast()) {
            $interaction
            ->respondWithMessage(
                MessageBuilder::new()
                ->setContent(
                    sprintf(
                        'You\'re available on `%s` at `%s`? That doesn\'t sound right. Please specify a time in the future.',
                        date('d.m.Y', $timeAvailableFrom),
                        date('H:i', $timeAvailableFrom),
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
                    date('d.m.Y', $timeAvailableFrom),
                    date('H:i', $timeAvailableFrom),
                    date('d.m.Y', $timeAvailableTo),
                    date('H:i', $timeAvailableTo)
                )
            )
            ->_setFlags(Message::FLAG_EPHEMERAL)
        );

        if ($userAvailabilityTime->isNow()) {
            $userId = $interaction->user->id;

            $actionRow = ActionRow::new()
            ->addComponent(
                Button::new(Button::STYLE_PRIMARY)
                ->setLabel('Yes, I am available')
                ->setListener(
                    function (Interaction $interaction) use ($userId, $timeAvailableFrom, $timeAvailableTo) {
                        $config       = new Config();
                        $userIdButton = $interaction->user->id;
                        $guild        = $interaction->guild;
                        $member       = $guild->members->get('id', $userIdButton);
                        $userName     = $member->nick ?: $member->user->username;
                        $event        = $config->getEventName();

                        if ($userId === $userIdButton) {
                            $interaction
                            ->respondWithMessage(
                                MessageBuilder::new()
                                ->setContent('D\'uh!')
                                ->_setFlags(Message::FLAG_EPHEMERAL)
                            );
                        } else {
                            $userAvailabilityTime = new UserAvailabilityTime();
                            $userAvailabilityTime->setTimeFrom($timeAvailableFrom);
                            $userAvailabilityTime->setTimeTo($timeAvailableTo);
                            $userAvailabilityTime->setAvailablePerDefault(false);

                            $userAvailability = UserAvailability::get($interaction->user);
                            $userAvailability->addAvailability($userAvailabilityTime);
                            $userAvailability->save();

                            $interaction
                            ->respondWithMessage(
                                MessageBuilder::new()->setContent(
                                    sprintf(
                                        '**%s** is available for **%s** now!',
                                        $userName,
                                        $event
                                    )
                                )
                            );
                        }
                    },
                    $this->discord
                )
            )
            ->addComponent(
                Button::new(Button::STYLE_SECONDARY)
                ->setLabel('Ignore')
                ->setListener(
                    function (Interaction $interaction) {
                        $interaction->acknowledge();
                    },
                    $this->discord
                )
            );

            $guild    = $interaction->guild;
            $member   = $guild->members->get('id', $userId);
            $userName = $member->nick ?: $member->user->username;
            $event    = $config->getEventName();

            $messageReply = MessageBuilder::new()
            ->setContent(
                sprintf(
                    '**%s** is available for **%s** now! Are you available too?',
                    $userName,
                    $event
                )
            )
            ->addComponent($actionRow);

            $interaction->sendFollowUpMessage($messageReply);
        }
    }
}
