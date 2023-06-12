<?php

namespace Grandeljay\Availability;

class Config
{
    private array $config;

    // Ordered list of possible config file locations.
    private $filepaths = array(
        '$HOME/.config/discord-availability/config.json',
        '/etc/discord-availability/config.json',
    );

    public function __construct()
    {
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        foreach ($this->filepaths as $path) {
            $path = str_replace('$HOME', getenv('HOME'), $path);

            if (file_exists($path)) {
                $contents     = file_get_contents($path);
                $this->config = json_decode($contents, true);
                return;
            }
        }

        die("Missing config.json. Please refer to README.md.\n");
    }

    /**
     * Returns a value from the config.
     *
     * @param string $key The value's key.
     *
     * @return mixed
     */
    public function get(string $key): mixed
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        return null;
    }

    /**
     * Returns the Discord API token.
     *
     * @return string
     */
    public function getAPIToken(): string
    {
        return $this->get('token');
    }
}
