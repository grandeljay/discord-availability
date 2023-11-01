<?php

namespace Grandeljay\Availability;

use Discord\Parts\User\User;

class UserAvailabilityTime
{
    /**
     * The user's specified availability time as a unix timestamp.
     *
     * @var int
     */
    private int $userAvailabilityTimeFrom;
    private int $userAvailabilityTimeTo;

    /**
     * Whether the user is assumed to be available by the bot. Usually this
     * means that the user did not explicitly specify an availability.
     *
     * @var bool
     */
    private bool $userIsAvailablePerDefault;

    /**
     * Retrieves all subscribed user's availabilities from storage.
     *
     * @return array
     */
    public static function getAll(): array
    {
        $config = new Config();

        $availabilities = array();

        $directory = $config->get('directoryAvailabilities');
        $files     = array_filter(
            scandir($directory),
            function ($file) use ($directory) {
                return is_file($directory . '/' . $file);
            }
        );

        foreach ($files as $file) {
            $contents            = file_get_contents($directory . '/' . $file);
            $availabilitiesStack = json_decode($contents, true);

            $availabilities[] = $availabilitiesStack;
        }

        return $availabilities;
    }

    /**
     * Construct
     *
     * @param array $availability The user's raw availability data from storage.
     */
    public function __construct(array $availabilityTimeData = array())
    {
        foreach ($availabilityTimeData as $property => $value) {
            if (property_exists($this::class, $property)) {
                $this->$property = $value;
            }
        }

        /** Backwards compatibility */
        if (isset($availabilityTimeData['userAvailabilityTime'])) {
            $this->userAvailabilityTimeFrom = $availabilityTimeData['userAvailabilityTime'];
            $this->userAvailabilityTimeTo   = $availabilityTimeData['userAvailabilityTime'] + 3600 * 3;
        }
    }

    /**
     * Returns this instance's properties into an array for storage.
     *
     * @return array
     */
    public function toArray(): array
    {
        $array = array(
            'userAvailabilityTimeFrom'  => $this->userAvailabilityTimeFrom,
            'userAvailabilityTimeTo'    => $this->userAvailabilityTimeTo,
            'userIsAvailablePerDefault' => $this->userIsAvailablePerDefault,
        );

        return $array;
    }

    /**
     * Returns whether this instance's availability time is in the past.
     *
     * @return boolean
     */
    public function isInPast(): bool
    {
        $isInPast = $this->userAvailabilityTimeFrom < time();

        return $isInPast;
    }

    public function isCurrent(): bool
    {
        $isCurrent = time() >= $this->userAvailabilityTimeFrom && time() < $this->userAvailabilityTimeTo;

        return $isCurrent;
    }

    /**
     * Returns this instance's available time as a unix timestamp.
     *
     * @return integer
     */
    public function getTimeFrom(): int
    {
        return $this->userAvailabilityTimeFrom;
    }

    public function getTimeTo(): int
    {
        return $this->userAvailabilityTimeTo;
    }

    /**
     * Set this instance's availability time using a unix timestamp.
     *
     * @param integer $userAvailabilityTimeFrom The user's availability time as
     *                                          a unix timestamp.
     *
     * @return void
     */
    public function setTimeFrom(int $userAvailabilityTimeFrom): void
    {
        $this->userAvailabilityTimeFrom = $userAvailabilityTimeFrom;
    }

    public function setTimeTo(int $userAvailabilityTimeTo): void
    {
        $this->userAvailabilityTimeTo = $userAvailabilityTimeTo;
    }

    /**
     * Returns whether this instance's availability is assumed as `true`,
     * without the user explicitly specifying whether he is.
     *
     * @return boolean
     */
    public function isAvailablePerDefault(): bool
    {
        $isAvailablePerDefault = $this->userIsAvailablePerDefault;

        return $isAvailablePerDefault;
    }

    public function setAvailablePerDefault(bool $userIsAvailablePerDefault): void
    {
        $this->userIsAvailablePerDefault = $userIsAvailablePerDefault;
    }

    public function getUserIsAvailableFrom(int $timeFrom): bool
    {
        $userIsAvailableFrom = false;

        if (isset($this->userAvailabilityTimeFrom)) {
            $userIsAvailableFrom = $this->userAvailabilityTimeFrom >= $timeFrom;
        }

        return $userIsAvailableFrom;
    }

    public function getUserIsAvailableTo(int $timeTo): bool
    {
        $userIsAvailableTo = false;

        if (isset($this->userAvailabilityTimeTo)) {
            $userIsAvailableTo = $this->userAvailabilityTimeTo <= $timeTo;
        }

        return $userIsAvailableTo;
    }

    public function getUserAvailabilityTimeFrom(): int
    {
        return $this->userAvailabilityTimeFrom;
    }

    public function getUserAvailabilityTimeTo(): int
    {
        return $this->userAvailabilityTimeTo;
    }

    public function getUserIsAvailablePerDefault(): bool
    {
        return $this->userIsAvailablePerDefault;
    }
}
