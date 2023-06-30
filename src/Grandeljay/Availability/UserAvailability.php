<?php

namespace Grandeljay\Availability;

use Discord\Parts\User\User;
use Psr\Log\LoggerInterface;

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

    private LoggerInterface $logger;

    /**
     * Returns the specified user's availability.
     *
     * @param User $user
     * @param LoggerInterface $logger
     *
     * @return self
     */
    public static function get(User $user, LoggerInterface $logger): self
    {
        $availability = new self($logger);
        $config       = new Config();

        $filepathUserAvailability = $config->getAvailabilitiesDir() . '/' . $user->id . '.json';

        if (file_exists($filepathUserAvailability)) {
            $availabilityContents = file_get_contents($filepathUserAvailability);
            $availabilityData     = json_decode($availabilityContents, true, 512, JSON_THROW_ON_ERROR);
            $availability         = new self($logger, $availabilityData);
        }

        return $availability;
    }

    public function __construct(LoggerInterface $logger, array $availabilityData = array())
    {
        $this->logger = $logger;

        $this->config                = new Config();
        $this->userAvailabilityTimes = new UserAvailabilityTimes();

        if (isset($availabilityData['userId'])) {
            $this->userId = $availabilityData['userId'];
        }

        if (isset($availabilityData['userName'])) {
            $this->userName = $availabilityData['userName'];
        }

        if (isset($availabilityData['availabilities'])) {
            foreach ($availabilityData['availabilities'] as $userAvailabilityTimeData) {
                $userAvailabilityTime = new UserAvailabilityTime($this->logger, $userAvailabilityTimeData);

                $this->userAvailabilityTimes->add($userAvailabilityTime);
            }
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
        $userAvailabilityTimes        = $this->userAvailabilityTimes;
        $userHasAvailabilityForMonday = false;

        foreach ($userAvailabilityTimes as $userAvailabilityTime) {
            $userAvailabilityTimeDay = strtolower(date('l', $userAvailabilityTime->getTime()));
            $defaultDay              = $this->config->getDefaultDay();

            if ($defaultDay === $userAvailabilityTimeDay) {
                $userHasAvailabilityForMonday = true;

                break;
            }
        }

        if (false === $userHasAvailabilityForMonday) {
            $defaultDateTime = $this->config->getDefaultDateTime();

            $userAvailabilityDefault = new UserAvailabilityTime($this->logger);
            $userAvailabilityDefault->setAvailability(true, true);
            $userAvailabilityDefault->setTime($defaultDateTime);

            $userAvailabilityTimes->add($userAvailabilityDefault);
        }

        return $userAvailabilityTimes;
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
}
