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
     * @var Availabilities
     */
    private Availabilities $availabilities;

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

        $this->availabilities = new Availabilities();

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
            foreach ($availabilityData['availabilities'] as $availabilityTimeData) {
                $userAvailabilityTime = new UserAvailabilityTime($availabilityTimeData);

                $this->availabilities->add($userAvailabilityTime);
            }
        }
    }

    public function addAvailability(UserAvailabilityTime $availabilityTime): void
    {
        $this->availabilities->add($availabilityTime);
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
            'availabilities' => $this->availabilities->jsonSerialize(),
        );

        return $json;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }
}
