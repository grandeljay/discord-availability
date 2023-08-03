<?php

namespace Grandeljay\Availability;

class UserAvailabilities extends UserAvailabilitiesIterator
{
    public static function getAll(): self
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
            $filepath = $directory . '/' . $filename;

            $userAvailability = UserAvailability::fromFile($filepath);
            $userAvailabilities->add($userAvailability);
        }

        return $userAvailabilities;
    }

    public function __construct()
    {
    }
}
