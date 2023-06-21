<?php

namespace Grandeljay\Availability;

use Discord\Parts\Interactions\Command\Command;

class Commands extends CommandsIterator
{
    /**
     * Returns whether the bot has the specified command registered.
     *
     * @param Command $command The discord command to check.
     *
     * @return bool
     */
    public function contains(Command $command): bool
    {
        foreach ($this->elements as $element) {
            if ($command->name === $element->name) {
                return true;
            }
        }

        return false;
    }
}
