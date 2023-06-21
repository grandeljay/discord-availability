<?php

namespace Grandeljay\Availability;

class Config
{
    private array $config;

    public function __construct()
    {
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        $filepathRelative     = 'discord-availability/config.json';
        $potentialConfigPaths = array();

        switch (PHP_OS) {
            case 'WINNT':
                $potentialConfigPaths = array(
                    '$USERPROFILE/.config/' . $filepathRelative,
                    '$APPDATA/' . $filepathRelative,
                );
                break;

            default:
                $potentialConfigPaths = array(
                    '$HOME/.config/' . $filepathRelative,
                    '/etc/' . $filepathRelative,
                );
                break;
        }

        foreach ($potentialConfigPaths as $potentialConfigPath) {
            $potentialConfigPath = $this->getPathWithEnvironmentVariable($potentialConfigPath);

            if (file_exists($potentialConfigPath)) {
                $raw_data    = file_get_contents($potentialConfigPath);
                $parsed_data = json_decode($raw_data, true, 2, JSON_THROW_ON_ERROR);

                $error = $this->validateConfig($parsed_data);

                if ($error) {
                    die(sprintf('Bad config.json at `%s`: %s' . PHP_EOL, $potentialConfigPath, $error));
                }

                $this->config = $parsed_data;

                return;
            }
        }

        die('Missing config.json. Please refer to README.md.' . PHP_EOL);
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
     * @param string $key     The value's key.
     * @param mixed  $default The default value to return when the key is not
     *                        found.
     *
     * @return mixed
     */
    private function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        return $default;
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
        $availabilitiesDirDefault = '$HOME/.local/share/discord-availability/availabilities';
        $availabilitiesDir        = $this->get('directoryAvailabilities', $availabilitiesDirDefault);
        $availabilitiesDir        = $this->getPathWithEnvironmentVariable($availabilitiesDir);
        $availabilitiesDir        = realpath($availabilitiesDir);

        return $availabilitiesDir;
    }

    private function getPathWithEnvironmentVariable(string $path): string
    {
        preg_match_all('/\$([A-Z_]+)/', $path, $environmentMatches, PREG_SET_ORDER);

        foreach ($environmentMatches as $match) {
            if (isset($match[0], $match[1])) {
                $matchFull                = $match[0];
                $matchEnvironmentVariable = $match[1];
                $environmentVariable      = getenv($matchEnvironmentVariable);

                if (false === $environmentVariable) {
                    die(sprintf('Could not get value for environment variable "%s".', $matchEnvironmentVariable));
                }

                $path = str_replace($matchFull, $environmentVariable, $path);
            }
        }

        return $path;
    }

    public function getMaxAvailabilitiesPerUser(): int
    {
        return $this->get('maxAvailabilitiesPerUser', 100);
    }

    public function getDefaultTime(): string
    {
        return $this->get('defaultTime', '19:00');
    }

    public function getDefaultDay(): string
    {
        return $this->get('defaultDay', 'monday');
    }

    public function getDefaultDateTime(): string
    {
        $dateTime = $this->getDefaultDay() . ' ' . $this->getDefaultTime();

        return $dateTime;
    }

    public function getEventName(): string
    {
        return $this->get('eventName', 'Dota 2');
    }

    public function getLogLevel(): string
    {
        return $this->get('logLevel', 'Info');
    }
}
