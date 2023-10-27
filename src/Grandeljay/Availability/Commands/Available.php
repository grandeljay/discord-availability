<?php

namespace Grandeljay\Availability\Commands;

use Discord\Builders\Components\{Button, ActionRow};
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Grandeljay\Availability\{Bot, Config, UserAvailabilities, UserAvailability, UserAvailabilityTime};

class Available extends Command
{
    public function run(Discord $discord): void
    {
        $discord->listenCommand(
            strtolower(Command::AVAILABLE),
            function (Interaction $interaction) {
                $timeAvailable = Bot::getTimeFromString($interaction->data->options['date']->value);
                $timeNow       = time();

                if (false === $timeAvailable) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()
                        ->setContent('Sorry, I couldn\'t parse that. Could you please specify a more machine friendly time?'),
                        true
                    );

                    return;
                }

                if ($timeAvailable < time()) {
                    $interaction
                    ->respondWithMessage(
                        MessageBuilder::new()
                        ->setContent(
                            sprintf(
                                'You\'re available on `%s` at `%s`? That doesn\'t sound right. Please specify a time in the future.',
                                date('d.m.Y', $timeAvailable),
                                date('H:i', $timeAvailable),
                            )
                        )
                        ->_setFlags(Message::FLAG_EPHEMERAL)
                    );

                    return;
                }

                $userAvailabilityTime = new UserAvailabilityTime();
                $userAvailabilityTime->setAvailability(true, false);
                $userAvailabilityTime->setTime($timeAvailable);

                $userAvailability = UserAvailability::get($interaction->user);
                $userAvailability->addAvailability($userAvailabilityTime);
                $userAvailability->save();

                $config = new Config();

                $interaction
                ->respondWithMessage(
                    MessageBuilder::new()->setContent(
                        sprintf(
                            'Gotcha! You are **available** for %s on `%s` at `%s`.',
                            $config->getEventName(),
                            date('d.m.Y', $timeAvailable),
                            date('H:i', $timeAvailable)
                        )
                    ),
                    true
                );

                if (\date('d.m.Y H:i', $timeNow) === \date('d.m.Y H:i', $timeAvailable)) {
                    $userId = $interaction->user->id;

                    $actionRow = ActionRow::new()
                    ->addComponent(
                        Button::new(Button::STYLE_PRIMARY)
                        ->setLabel('Yes, I am available')
                        ->setListener(
                            function (Interaction $interaction) use ($timeAvailable, $userId) {
                                $config       = new Config();
                                $userIdButton = $interaction->user->id;
                                $guild        = $interaction->guild;
                                $member       = $guild->members->get('id', $userId);
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
                                    $userAvailabilityTime->setAvailability(true, false);
                                    $userAvailabilityTime->setTime($timeAvailable);

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
        );
    }
}
