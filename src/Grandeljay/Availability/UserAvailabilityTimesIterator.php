<?php

namespace Grandeljay\Availability;

use Countable;
use Iterator;
use JsonSerializable;

class UserAvailabilityTimesIterator implements Iterator, JsonSerializable, Countable
{
    private int $position     = 0;
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

    public function count(): int
    {
        return count($this->elements);
    }

    public function add(UserAvailabilityTime $userAvailabilityTime): void
    {
        $this->elements[] = $userAvailabilityTime;
    }

    public function get(int $index): UserAvailabilityTime
    {
        return $this->elements[$index];
    }

    public function jsonSerialize(): array
    {
        $json = array();

        foreach ($this->elements as $userAvailabilityTime) {
            $userIsAvailable           = $userAvailabilityTime->getUserIsAvailable();
            $userAvailabilityTimeTime  = $userAvailabilityTime->getUserAvailabilityTime();
            $userIsAvailablePerDefault = $userAvailabilityTime->getUserIsAvailablePerDefault();

            $json[$userAvailabilityTimeTime] = array(
                'userIsAvailable'           => $userIsAvailable,
                'userAvailabilityTime'      => $userAvailabilityTimeTime,
                'userIsAvailablePerDefault' => $userIsAvailablePerDefault,
            );
        }

        return $json;
    }

    public function sort(string $order)
    {
        switch ($order) {
            case 'ASC':
                sort($this->elements);
                break;

            case 'DESC':
                rsort($this->elements);
                break;
        }
    }
}
