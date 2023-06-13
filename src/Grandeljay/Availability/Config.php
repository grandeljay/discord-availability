<?php

namespace Grandeljay\Availability;

class Config
{
    private array $config;

    private $defaultAvailabilitiesDir = '$HOME/.local/share/discord-availability/availabilities';

    public function __construct()
    {
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        $filepathRelative = 'discord-availability/config.json';
        $filepaths        = array();

        switch (PHP_OS) {
            case 'WINNT':
                $filepaths = array(
                    '$USERPROFILE/.config/' . $filepathRelative,
                    '$APPDATA/' . $filepathRelative,
                );
                break;

            default:
                $filepaths = array(
                    '$HOME/.config/' . $filepathRelative,
                    '/etc/' . $filepathRelative,
                );
                break;
        }

        foreach ($filepaths as $filepath) {
            preg_match('/\$([A-Z]+)/', $filepath, $environmentMatches);

            if (isset($environmentMatches[0], $environmentMatches[1])) {
                $matchFull                = $environmentMatches[0];
                $matchEnvironmentVariable = $environmentMatches[1];

                $filepath = str_replace($matchFull, getenv($matchEnvironmentVariable), $filepath);
            }

            if (file_exists($filepath)) {
                $raw_data    = file_get_contents($filepath);
                $parsed_data = json_decode($raw_data, true, 2, JSON_THROW_ON_ERROR);

                $error = $this->validateConfig($parsed_data);

                if ($error) {
                    die(sprintf('Bad config.json at `%s`: %s\n', $filepath, $error));
                }

                $this->config = $parsed_data;
                return;
            }
        }

        die('Missing config.json. Please refer to README.md.\n');
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
}
