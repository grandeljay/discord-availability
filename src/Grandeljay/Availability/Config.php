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
            $filepath = $this->getPathWithEnvironmentVariable($filepath);

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
        $availabilitiesDir        = $this->get('directory_availabilities', $availabilitiesDirDefault);
        $availabilitiesDir        = $this->getPathWithEnvironmentVariable($availabilitiesDir);
        $availabilitiesDir        = $this->normalizePath($availabilitiesDir);

        return $availabilitiesDir;
    }

    private function normalizePath(string $path): string
    {
        $cwd = getcwd();

        if (str_starts_with($path, $cwd)) {
            return $path;
        }

        return $cwd . '/' . $path;
    }

    private function getPathWithEnvironmentVariable(string $path): string
    {
        preg_match_all('/\$([A-Z]+)/', $path, $environmentMatches, PREG_SET_ORDER);

        foreach ($environmentMatches as $match) {
            if (isset($match[0], $match[1])) {
                $matchFull                = $match[0];
                $matchEnvironmentVariable = $match[1];

                $path = str_replace($matchFull, getenv($matchEnvironmentVariable), $path);
            }
        }

        return $path;
    }

    public function getMaxAvailabilitiesPerUser(): int
    {
        return $this->get('maxAvailabilitiesPerUser', 100);
    }
}
