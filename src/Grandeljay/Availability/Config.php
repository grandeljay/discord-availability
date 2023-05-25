<?php

namespace Grandeljay\Availability;

class Config
{
    private array $config;

    /**
     * Returns the project's root.
     *
     * @return string
     */
    public static function getRoot(): string
    {
        $root = dirname(dirname(dirname(__DIR__)));

        return $root;
    }

    public function __construct()
    {
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        $filepath = $this->getRoot() . '/src/config/config.json';

        $this->config = json_decode(file_get_contents($filepath), true);
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
}
