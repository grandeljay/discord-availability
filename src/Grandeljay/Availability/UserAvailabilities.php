<?php

namespace Grandeljay\Availability;

use Psr\Log\LoggerInterface;

class UserAvailabilities extends UserAvailabilitiesIterator
{
    public static function getAll(LoggerInterface $logger): self
    {
        $config             = new Config();
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
            $userAvailability         = new UserAvailability($logger, $userAvailabilityData);

            $userAvailabilities->add($userAvailability);
        }

        return $userAvailabilities;
    }

    public function __construct()
    {
    }
}
