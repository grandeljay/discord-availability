<?php

namespace Grandeljay\Availability;

class Config
{
    private array $config;

    public string $filepath = '/etc/grandeljay/discord-availability/config.json';

    public function __construct()
    {
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        if (file_exists($this->filepath)) {
            $contents     = file_get_contents($this->filepath);
            $this->config = json_decode($contents, true);
        } else {
            die(sprintf('Missing config.json at "%s". Please refer to README.md.', $this->filepath));
        }
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
