<?php

namespace Grandeljay\Availability;

use Discord\Parts\User\User;

use Psr\Log\LoggerInterface;

class UserAvailabilityTime
{
    /**
     * Whether the user is available or not.
     *
     * @var bool
     */
    private bool $userIsAvailable;

    /**
     * The user's specified availability time as a unix timestamp.
     *
     * @var int
     */
    private int $userAvailabilityTime;

    /**
     * Whether the user is assumed to be available by the bot. Usually this
     * means that the user did not explicitly specify an availability.
     *
     * @var bool
     */
    private bool $userIsAvailablePerDefault;

    private LoggerInterface $logger;

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
     * @param LoggerInterface $logger
     * @param array $availability The user's raw availability data from storage.
     */
    public function __construct(LoggerInterface $logger, array $availabilityTimeData = array())
    {
        $this->logger = $logger;

        foreach ($availabilityTimeData as $property => $value) {
            if (property_exists($this::class, $property)) {
                $this->$property = $value;
            }
        }
    }

    /**
     * Returns this instance's properties into an array for storage.
     *
     * @return array
     */
    public function toArray(): array
    {
        $this->userAvailabilities = UserAvailabilities::getAll($this->logger);

        $array = array(
            'userIsAvailable'           => $this->userIsAvailable,
            'userAvailabilityTime'      => $this->userAvailabilityTime,
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
        $isInPast = $this->userAvailabilityTime < time();

        return $isInPast;
    }

    /**
     * Returns whether this instance's availability is `true`.
     *
     * @return boolean
     */
    public function isAvailable(): bool
    {
        $isAvailable = $this->userIsAvailable;

        return $isAvailable;
    }

    /**
     * Set this instance's availability.
     *
     * @param boolean $userIsAvailable           Whether the user is available.
     * @param boolean $userIsAvailablePerDefault Whether the user is available
     *                                           without the user explicitly
     *                                           specifying whether he is.
     *
     * @return void
     */
    public function setAvailability(bool $userIsAvailable, bool $userIsAvailablePerDefault): void
    {
        $this->userIsAvailable           = $userIsAvailable;
        $this->userIsAvailablePerDefault = $userIsAvailablePerDefault;
    }

    /**
     * Returns this instance's available time as a unix timestamp.
     *
     * @return integer
     */
    public function getTime(): int
    {
        $time = $this->userAvailabilityTime;

        return $time;
    }

    /**
     * Set this instance's availability time using a unix timestamp.
     *
     * @param integer $userAvailabilityTime The user's availability time as a
     *                                      unix timestamp.
     *
     * @return void
     */
    public function setTime(int $userAvailabilityTime): void
    {
        $this->userAvailabilityTime = $userAvailabilityTime;
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

    /**
     * Returns this instance's availability as a pretty (your mileage may vary),
     * human readable string.
     *
     * @return string
     */
    public function toString(string $userName): string
    {
        $text       = $this->userIsAvailable           ? 'available'      : 'unavailable';
        $emoji      = $this->userIsAvailable           ? ':star_struck:'  : ':angry:';
        $perDefault = $this->userIsAvailablePerDefault ? ' (per default)' : '';
        $date       = date('d.m.Y', $this->userAvailabilityTime);
        $time       = date('H:i', $this->userAvailabilityTime);

        $string = sprintf(
            '- %s %s is %s on `%s` at `%s`%s',
            $emoji,
            $userName,
            $text,
            $date,
            $time,
            $perDefault
        );

        return $string;
    }

    public function getUserIsAvailable(): bool
    {
        return $this->userIsAvailable;
    }

    public function getUserAvailabilityTime(): int
    {
        return $this->userAvailabilityTime;
    }

    public function getUserIsAvailablePerDefault(): bool
    {
        return $this->userIsAvailablePerDefault;
    }
}
