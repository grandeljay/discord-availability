<?php

namespace Grandeljay\Availability;

class UserAvailabilities extends UserAvailabilitiesIterator
{
    public static function getAll(Config $config): self
    {
        $userAvailabilities = new self();

        $directory = $config->getAvailabilitiesDir();
        $filenames = array_filter(
            scandir($directory),
            function ($filename) use ($directory) {
                $filepath = $directory . '/'  . $filename;

                return is_file($filepath);
            }
        );

        foreach ($filenames as $filename) {
            $filepath                 = $directory . '/' . $filename;
            $userAvailabilityContents = file_get_contents($filepath);
            $userAvailabilityData     = json_decode($userAvailabilityContents, true);
            $userAvailability         = new UserAvailability($userAvailabilityData);

            $userAvailabilities->add($userAvailability);
        }

        return $userAvailabilities;
    }

    public function __construct()
    {
    }
}
