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
                $rawData       = file_get_contents($potentialConfigPath);
                $parsedData    = json_decode($rawData, true, 2, JSON_THROW_ON_ERROR);
                $normalizedCfg = $this->normalizeConfig($parsedData);
                $error         = $this->validateConfig($normalizedCfg);

                if ($error) {
                    die(sprintf('Bad config.json at `%s`: %s' . PHP_EOL, $potentialConfigPath, $error));
                }

                $this->config = $normalizedCfg;

                return;
            }
        }

        die('Missing config.json. Please refer to README.md.' . PHP_EOL);
    }

    /**
     * Processes the passed raw config and returns it in normalized form.
     *
     * Note: This function doesn't do any validation.
     *
     * @param array $config The raw config to normalize. This is essentially
     *                      just the decoded json string.
     *
     * @return array The normalized config.
     */
    private function normalizeConfig(array $rawConfig): array
    {
        $normalizedConfig = $rawConfig; // Create a copy.

        $normalizedConfig['directoryAvailabilities'] = $this->normalizeAvailabilitiesDir($rawConfig['directoryAvailabilities']);
        $normalizedConfig['maxAvailabilitiesPerUser'] = $rawConfig['maxAvailabilitiesPerUser'] ?? 100;
        $normalizedConfig['defaultDay'] = $rawConfig['defaultDay'] ?? "monday";
        $normalizedConfig['defaultTime'] = $rawConfig['defaultTime'] ?? "19:00";
        $normalizedConfig['eventName'] = $rawConfig['eventName'] ?? "Dota 2";
        $normalizedConfig['logLevel'] = $rawConfig['logLevel'] ?? "Info";

        return $normalizedConfig;
    }

    private function normalizeAvailabilitiesDir(?string $inputDir): string
    {
        $default = '$HOME/.local/share/discord-availability/availabilities';

        $dir = $inputDir ?? $default;
        $dir = $this->getPathWithEnvironmentVariable($dir);
        $dir = realpath($dir);

        return $dir;
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
        return $this->get('directoryAvailabilities');
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
        return $this->get('maxAvailabilitiesPerUser');
    }

    public function getDefaultTime(): string
    {
        return $this->get('defaultTime');
    }

    public function getDefaultDay(): string
    {
        return $this->get('defaultDay');
    }

    public function getDefaultDateTime(): string
    {
        $dateTime = $this->getDefaultDay() . ' ' . $this->getDefaultTime();

        return $dateTime;
    }

    public function getEventName(): string
    {
        return $this->get('eventName');
    }

    public function getLogLevel(): string
    {
        return $this->get('logLevel');
    }
}
