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

    private $defaultAvailabilitiesDir = '$HOME/.local/share/discord-availability/availabilities';

    public function __construct()
    {
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        foreach ($this->filepaths as $path) {
            $path = $this->expandHome($path);

            if (file_exists($path)) {
                $raw_data    = file_get_contents($path);
                $parsed_data = json_decode($raw_data, true, 2);

                if ($parsed_data == null) {
                    die(sprintf("Bad config.json at `%s`: Invalid JSON.\n", $path));
                }

                $error = $this->validateConfig($parsed_data);

                if ($error) {
                    die(sprintf("Bad config.json at `%s`: %s\n", $path, $error));
                }

                $this->config = $parsed_data;
                return;
            }
        }

        die("Missing config.json. Please refer to README.md.\n");
    }

    /**
     * Validates the passed config and returns an error if it is invalid.
     *
     * @param array $config The config to validate.
     *
     * @return string|null A potential error that occurred.
     */
    private function validateConfig(array $config): ?string
    {
        if (!isset($config['token'])) {
            return 'Required key "token" is not set.';
        }

        // TODO: Validate that configured availabilities directory exists.

        return null;
    }

    /**
     * Returns a value from the config.
     *
     * @param string $key The value's key.
     *
     * @return mixed
     */
    private function get(string $key): mixed
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

    /**
     * Returns the path of the availabilities directory.
     *
     * @return string
     */
    public function getAvailabilitiesDir(): string
    {
        if ($this->get('directory_availabilities')) {
            return $this->normalizePath($this->get('directory_availabilities'));
        }

        return $this->expandHome($this->defaultAvailabilitiesDir);
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        $cwd = getcwd();

        if (!$cwd) {
            die('Could not determine current working directory.');
        }

        return $cwd . '/' . $path;
    }

    private function expandHome(string $path): string
    {
        return str_replace('$HOME', getenv('HOME'), $path);
    }
}
