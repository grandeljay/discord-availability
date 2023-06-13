<?php

namespace Grandeljay\Availability;

use Iterator;
use JsonSerializable;

class AvailabilitiesIterator implements Iterator, JsonSerializable
{
    private int $position   = 0;
    private array $elements = array();

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

    public function add(UserAvailabilityTime $userAvailabilityTime): void
    {
        $this->elements[] = $userAvailabilityTime;
    }

    public function jsonSerialize(): array
    {
        $json = array();

        foreach ($this->elements as $userAvailabilityTime) {
            $json[] = array(
                'userIsAvailable'           => $userAvailabilityTime->getUserIsAvailable(),
                'userAvailabilityTime'      => $userAvailabilityTime->getUserAvailabilityTime(),
                'userIsAvailablePerDefault' => $userAvailabilityTime->getUserIsAvailablePerDefault(),
            );
        }

        return $json;
    }
}
