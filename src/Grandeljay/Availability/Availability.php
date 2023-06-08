<?php

namespace Grandeljay\Availability;

use Discord\Parts\User\User;

class Availability
{
    /**
     * The user's discord id.
     *
     * @var integer
     */
    private int $userId;

    /**
     * The user's discord user name without the discriminator.
     *
     * @var string
     */
    private string $userName;

    /**
     * Whether the user is available or not.
     *
     * @var boolean
     */
    private bool $userIsAvailable;

    /**
     * The user's specified availability time as a unix timestamp.
     *
     * @var integer
     */
    private int $userAvailabilityTime;

    /**
     * Whether the user is assumed to be available by the bot. Usually this
     * means that the user did not explicitly specify an availability.
     *
     * @var boolean
     */
    private bool $userIsAvailablePerDefault;

    /**
     * Adds the user's specified availability time to storage.
     *
     * @param User $user                 The message author.
     * @param bool $userIsAvailable      Whether the user is available.
     * @param int  $userAvailabilityTime The unix timestamp of the user's
     *                                   availability.
     *
     * @return void
     */
    public static function add(User $user, bool $userIsAvailable, int $userAvailabilityTime, bool $userIsAvailablePerDefault): void
    {
        $config = new Config();

        $availabilities    = self::get($user);
        $availabilityToAdd = array(
            'userId'                    => $user->id,
            'userName'                  => $user->username,
            'userIsAvailable'           => $userIsAvailable,
            'userAvailabilityTime'      => $userAvailabilityTime,
            'userIsAvailablePerDefault' => $userIsAvailablePerDefault,
        );
        $availabilities[]  = $availabilityToAdd;

        $filename = $user->id . '.json';
        $filepath = $config->get('directory_availabilities') . '/' . $filename;

        file_put_contents($filepath, json_encode($availabilities));
    }

    /**
     * Retrieves the user's availabilities from storage.
     *
     * @param User $user The user's availability to get.
     *
     * @return array
     */
    private static function get(User $user): array
    {
        $config = new Config();

        $id       = $user->id;
        $filepath = $config->get('directory_availabilities') . '/' . $id . '.json';

        if (!file_exists($filepath)) {
            return array();
        }

        $contents       = file_get_contents($filepath);
        $availabilities = json_decode($contents, true);

        /**
         * Temporarily needed for backwards compatibility.
         */
        if (!is_array($availabilities)) {
            return array();
        }

        return $availabilities;
    }

    /**
     * Construct
     *
     * @param array $availability The user's raw availability data from storage.
     */
    public function __construct(array $availability = array())
    {
        if (
            isset(
                $availability['userId'],
                $availability['userName'],
                $availability['userIsAvailable'],
                $availability['userAvailabilityTime'],
                $availability['userIsAvailablePerDefault'],
            )
        ) {
            $this->userId                    = $availability['userId'];
            $this->userName                  = $availability['userName'];
            $this->userIsAvailable           = $availability['userIsAvailable'];
            $this->userAvailabilityTime      = $availability['userAvailabilityTime'];
            $this->userIsAvailablePerDefault = $availability['userIsAvailablePerDefault'];
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
            'userId'                    => $this->userId,
            'userName'                  => $this->userName,
            'userIsAvailable'           => $this->userIsAvailable,
            'userAvailabilityTime'      => $this->userAvailabilityTime,
            'userIsAvailablePerDefault' => $this->userIsAvailablePerDefault,
        );

        return $array;
    }
}
