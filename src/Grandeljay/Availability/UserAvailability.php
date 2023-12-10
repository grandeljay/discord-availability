<?php

namespace Grandeljay\Availability;

use Discord\Parts\User\User;

class UserAvailability implements \JsonSerializable
{
    private Config $config;

    /**
     * The discord user id.
     *
     * @var int
     */
    private int $userId;

    /**
     * The discord user name.
     *
     * @var string
     */
    private string $userName;

    /**
     * The discord user's availabilities.
     *
     * @var UserAvailabilityTimes
     */
    private UserAvailabilityTimes $userAvailabilityTimes;

    public static function fromFile(string $path): self
    {
        $text = file_get_contents($path);
        $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        return new self($data['userId'], $data['userName'], $data['availabilities']);
    }

    /**
     * Returns the specified user's availability.
     *
     * @param User $user
     *
     * @return self
     */
    public static function get(User $user): self
    {
        $config = new Config();

        $filepathUserAvailability = $config->getAvailabilitiesDir() . '/' . $user->id . '.json';
        if (file_exists($filepathUserAvailability)) {
            return UserAvailability::fromFile($filepathUserAvailability);
        } else {
            return new self($user->id, $user->username, array());
        }
    }

    public function __construct(string $userId, string $userName, array $availabilities)
    {
        $this->userId   = $userId;
        $this->userName = $userName;

        $this->config = new Config();

        $this->userAvailabilityTimes = new UserAvailabilityTimes();

        foreach ($availabilities as $userAvailabilityTimeData) {
            $userAvailabilityTime = new UserAvailabilityTime($userAvailabilityTimeData);
            $this->userAvailabilityTimes->add($userAvailabilityTime);
        }
    }

    public function addAvailability(UserAvailabilityTime $userAvailabilityTime): void
    {
        $this->userAvailabilityTimes->add($userAvailabilityTime);
    }

    /**
     * Write this availability to storage for later use.
     *
     * @return void
     */
    public function save(): void
    {
        $this->truncate();

        $filepathUserAvailability = $this->config->getAvailabilitiesDir() . '/' . $this->userId . '.json';
        $json                     = json_encode($this->jsonSerialize());

        file_put_contents($filepathUserAvailability, $json);
    }

    public function jsonSerialize(): array
    {
        $json = array(
            'userId'         => $this->userId,
            'userName'       => $this->userName,
            'availabilities' => $this->userAvailabilityTimes->jsonSerialize(),
        );

        return $json;
    }

    public function getUserAvailabilityTimes(): UserAvailabilityTimes
    {
        return $this->userAvailabilityTimes;
    }

    public function truncate(): void
    {
        $maxValuesAllowed = $this->config->getMaxAvailabilitiesPerUser();

        if (count($this->userAvailabilityTimes) <= $maxValuesAllowed) {
            return;
        }

        $this->userAvailabilityTimes->sort('DESC');

        $truncated = new UserAvailabilityTimes();

        for ($i = 0; $i < $maxValuesAllowed; $i++) {
            $userAvailabilityTime = $this->userAvailabilityTimes->get($i);

            $truncated->add($userAvailabilityTime);
        }

        $this->userAvailabilityTimes = $truncated;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getUserAvailabilityforTime(int $timeFrom, int $timeTo): UserAvailabilityTime
    {
        $timesPotential = array();

        foreach ($this->userAvailabilityTimes as $userAvailabilityTime) {
            $timeAvailabilityFrom           = $userAvailabilityTime->getTimeFrom();
            $timeAvailabilityTo             = $userAvailabilityTime->getTimeTo();
            $timeAvailabilityStartedInPast  = $timeAvailabilityFrom <= $timeFrom && $timeAvailabilityTo >= $timeFrom;
            $timeAvailabilityStartsInFuture = $timeAvailabilityFrom <= $timeTo   && $timeAvailabilityTo >= $timeTo;
            $timeAvailabilityInBetween      = $timeAvailabilityFrom >= $timeFrom && $timeAvailabilityTo <= $timeTo;
            $timeAvailabilityIsOngoing      = $timeAvailabilityStartedInPast || $timeAvailabilityStartsInFuture || $timeAvailabilityInBetween;

            if ($timeAvailabilityIsOngoing) {
                $differenceFrom    = \abs($timeAvailabilityFrom - $timeFrom);
                $differenceTo      = \abs($timeAvailabilityTo   - $timeTo);
                $differenceAverage = ($differenceFrom + $differenceTo) / 2;

                $timesPotential[] = array(
                    'differenceFrom'       => $differenceFrom,
                    'differenceTo'         => $differenceTo,
                    'differenceAverage'    => $differenceAverage,
                    'userAvailabilityTime' => $userAvailabilityTime,
                );
            }
        }

        if (empty($timesPotential)) {
            /** Return user as unavailable */
            $unavailableTime = new UserAvailabilityTime();
            $unavailableTime->setAvailability(false);
            $unavailableTime->setTimeFrom($timeFrom);
            $unavailableTime->setTimeTo($timeTo);

            /** Available per default */
            $config          = new Config();
            $defaultDay      = \strtolower($config->getDefaultDay());
            $defaultDateTime = Bot::getTimeFromString($config->getDefaultDateTime());
            $requestedDay    = \strtolower(\date('l', $timeFrom));

            if ($defaultDay === $requestedDay) {
                $unavailableTime->setAvailability(true);
                $unavailableTime->setAvailablePerDefault(true);
                $unavailableTime->setTimeFrom($defaultDateTime);
                $unavailableTime->setTimeTo($defaultDateTime + 3600 * 4);
            }

            return $unavailableTime;
        } else {
            /** Return closest availability */
            \usort(
                $timesPotential,
                function ($a, $b) {
                    return $a['differenceAverage'] <=> $b['differenceAverage'];
                }
            );
            $closestTime = \reset($timesPotential);

            return $closestTime['userAvailabilityTime'];
        }
    }
}
