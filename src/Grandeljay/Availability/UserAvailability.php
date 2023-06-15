<?php

namespace Grandeljay\Availability;

use Discord\Parts\User\User;

class UserAvailability extends Bot implements \JsonSerializable
{
    /**
     * The discord user.
     *
     * @var User
     */
    private User $user;

    /**
     * The discord user's availabilities.
     *
     * @var UserAvailabilityTimes
     */
    private UserAvailabilityTimes $userAvailabilityTimes;

    /**
     * Returns the specified user's availability.
     *
     * @param User $user
     *
     * @return self
     */
    public static function get(User $user): self
    {
        $availability = new self();
        $config       = new Config();

        $filepathUserAvailability = $config->getAvailabilitiesDir() . '/' . $user->id . '.json';

        if (file_exists($filepathUserAvailability)) {
            $availabilityContents = file_get_contents($filepathUserAvailability);
            $availabilityData     = json_decode($availabilityContents, true, 512, JSON_THROW_ON_ERROR);
            $availability         = new self($availabilityData);
        }

        $availability->setUser($user);

        return $availability;
    }

    public function __construct(array $availabilityData = array())
    {
        parent::__construct();

        $this->userAvailabilityTimes = new UserAvailabilityTimes();

        if (isset($availabilityData['userId'])) {
            $userId = $availabilityData['userId'];
            $user   = $this->discord->users->get('id', $userId);

            if (null === $user) {
                $this->discord->users->fetch($userId)->done(
                    function (User $user) {
                        $this->user = $user;
                    }
                );
            } else {
                $this->user = $user;
            }
        }

        if (isset($availabilityData['availabilities'])) {
            foreach ($availabilityData['availabilities'] as $userAvailabilityTimeData) {
                $userAvailability = new UserAvailabilityTime($userAvailabilityTimeData);

                $this->userAvailabilityTimes->add($userAvailability);
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
        $filepathUserAvailability = $this->config->getAvailabilitiesDir() . '/' . $this->user->id . '.json';
        $json                     = json_encode($this->jsonSerialize());

        file_put_contents($filepathUserAvailability, $json);
    }

    public function jsonSerialize(): array
    {
        $json = array(
            'userId'         => $this->user->id,
            'userName'       => $this->user->username,
            'availabilities' => $this->userAvailabilityTimes->jsonSerialize(),
        );

        return $json;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getUserAvailabilityTimes(): UserAvailabilityTimes
    {
        return $this->userAvailabilityTimes;
    }
}
