<?php

namespace Grandeljay\Availability;

use Grandeljay\Availability\Commands\Command;
use Iterator;

class CommandsIterator implements Iterator
{
    private int $position = 0;

    protected array $elements = array();

    public function current(): mixed
    {
        $current = $this->elements[$this->position];

        return $current;
    }

    public function key(): int
    {
        $position = $this->position;

        return $position;
    }

    public function next(): void
    {
        $this->position += 1;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        $valid = isset($this->elements[$this->position]);

        return $valid;
    }

    public function add(Command $command): void
    {
        $this->elements[] = $command;
    }
}
