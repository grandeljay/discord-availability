<?php

namespace Grandeljay\Availability;

use Iterator;
use JsonSerializable;

class UserAvailabilitiesIterator implements Iterator, JsonSerializable
{
    private int $position     = 0;
    protected array $elements = [];

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

    public function add(UserAvailability $userAvailability): void
    {
        $this->elements[] = $userAvailability;
    }

    public function jsonSerialize(): array
    {
        $json = [];

        foreach ($this->elements as $userAvailabilityTime) {
            $json[] = [
                'userId'                => $userAvailabilityTime->getUserId(),
                'userName'              => $userAvailabilityTime->getUserName(),
                'userAvailabilityTimes' => $userAvailabilityTime->getUserAvailabilityTimes(),
            ];
        }

        return $json;
    }
}
