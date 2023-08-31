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
                $rawData    = file_get_contents($potentialConfigPath);
                $parsedData = json_decode($rawData, true, 2, JSON_THROW_ON_ERROR);
                $error      = $this->validateConfig($parsedData);

                if ($error) {
                    $msg = sprintf('Bad config.json at `%s`:' . PHP_EOL, $potentialConfigPath);
                    $msg = $msg . "  Error:       " . $error . PHP_EOL;
                    die($msg);
                }

                $normalisedCfg = $this->normaliseConfig($parsedData);
                $this->config  = $normalisedCfg;

                return;
            }
        }

        die('Missing config.json. Please refer to README.md.' . PHP_EOL);
    }

    /**
     * Processes the passed raw config and returns it in normalised form.
     *
     * Note: This function doesn't do any validation.
     *
     * @param array $config The raw config to normalise. This is essentially
     *                      just the decoded json string.
     *
     * @return array The normalised config.
     */
    private function normaliseConfig(array $rawConfig): array
    {
        $normalisedConfig = $rawConfig; // Create a copy.

        $normalisedConfig['directoryAvailabilities']  = $this->normaliseAvailabilitiesDir($rawConfig['directoryAvailabilities']);
        $normalisedConfig['maxAvailabilitiesPerUser'] = $rawConfig['maxAvailabilitiesPerUser'] ?? 100;
        $normalisedConfig['defaultDay']               = $rawConfig['defaultDay'] ?? "monday";
        $normalisedConfig['defaultTime']              = $rawConfig['defaultTime'] ?? "19:00";
        $normalisedConfig['eventName']                = $rawConfig['eventName'] ?? "Dota 2";
        $normalisedConfig['logLevel']                 = $rawConfig['logLevel'] ?? "Info";

        return $normalisedConfig;
    }

    private function normaliseAvailabilitiesDir(?string $inputDir): string
    {
        $default = '$HOME/.local/share/discord-availability/availabilities';

        $dir = $inputDir ?? $default;
        $dir = $this->getPathWithEnvironmentVariable($dir);
        $dir = $this->normalisePath($dir);

        return $dir;
    }

    /**
     * Returns an absolute path.
     *
     * Unlike `realpath` this function also works on paths that don't point to
     * an existing file.
     *
     * Also, symlinks are not resolved.
     *
     * @param string $path
     *
     * @return string
     */
    private function normalisePath(string $path): string
    {
        if ($this->isPathAbsolute($path)) {
            return $path;
        }

        $cwd = getcwd();

        if (!$cwd) {
            die('Could not determine current working directory.');
        }

        $segments     = array($cwd, $path);
        $absolutePath = implode(DIRECTORY_SEPARATOR, $segments);

        return $absolutePath;
    }

    /**
     * Returns whether `$path` is an absolute path.
     *
     * Examples of absolute paths:
     * - `/var/www/linux`
     * - `C:\Windows`
     * - `\\WindowsNetworkLocation`
     *
     * @param string $path
     *
     * @return bool `true` if the path is absolute, otherwise `false`.
     */
    private function isPathAbsolute(string $path): bool
    {
        // Note: A single backslash must be denoted as four `\` characters in a
        // `preg_match` regex.
        if (1 === preg_match('@^(/|[A-Z]:\\\\|\\\\\\\\)@', $path)) {
            return true;
        } else {
            return false;
        }
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

        $dir = $this->normaliseAvailabilitiesDir($config['directoryAvailabilities']);
        if (file_exists($dir)) {
            if (!is_dir($dir)) {
                $msg = 'The "directoryAvailabilities" directory is a non-directory file.' . PHP_EOL;
                $msg = $msg . sprintf('  Specified:   "%s"' . PHP_EOL, $config['directoryAvailabilities']);
                $msg = $msg . sprintf('  Interpreted: "%s"' . PHP_EOL, $dir);
                return $msg;
            }
        } else {
            $msg = 'The "directoryAvailabilities" directory does not exist.' . PHP_EOL;
            $msg = $msg . sprintf('  Specified:   "%s"' . PHP_EOL, $config['directoryAvailabilities']);
            $msg = $msg . sprintf('  Interpreted: "%s"' . PHP_EOL, $dir);
            return $msg;
        }

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
