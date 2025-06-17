<?php

namespace Grandeljay\Availability;

class Time
{
    public static function getStartOfDay(int $forDay): int
    {
        $date = new \DateTime();
        $date->setTimestamp($forDay);
        $date->setTime(0, 0, 0);

        $startOfDay = $date->getTimestamp();

        return $startOfDay;
    }

    public static function getEndOfDay(int $forDay): int
    {
        $date = new \DateTime();
        $date->setTimestamp($forDay);
        $date->setTime(23, 59, 59);

        $endOfDay = $date->getTimestamp();

        return $endOfDay;
    }
}
