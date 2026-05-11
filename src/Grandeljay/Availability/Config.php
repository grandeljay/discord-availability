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
        $potentialConfigPaths = match (\PHP_OS) {
            'WINNT' => [
                'config.json',
                '$USERPROFILE/.config/' . $filepathRelative,
                '$APPDATA/' . $filepathRelative,
            ],
            default => [
                'config.json',
                '$HOME/.config/' . $filepathRelative,
                '/etc/' . $filepathRelative,
            ],
        };

        foreach ($potentialConfigPaths as $potentialConfigPath) {
            $potentialConfigPathExpanded   = $this->expandEnvVars($potentialConfigPath);
            $potentialConfigPathNormalised = $this->normalisePath($potentialConfigPathExpanded);
            $potentialConfigPathExists     = \file_exists($potentialConfigPathNormalised);

            if (!$potentialConfigPathExists) {
                continue;
            }

            $configPath     = $potentialConfigPathExpanded;
            $configContents = \file_get_contents($configPath);
            $configDecoded  = \json_decode($configContents, true, 2, \JSON_THROW_ON_ERROR);
            $configError    = $this->validateConfig($configDecoded);

            if ($configError) {
                $msg = \sprintf('Bad config.json at `%s`:' . \PHP_EOL, $configPath);
                $msg = $msg . "  Error:       " . $configError . \PHP_EOL;
                die($msg);
            }

            $configNormalised = $this->normaliseConfig($configDecoded);

            $this->config = $configNormalised;

            return;
        }

        die('Missing config.json. Please refer to README.md.' . \PHP_EOL);
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
        $normalisedConfig = [
            'maxAvailabilitiesPerUser' => $rawConfig['maxAvailabilitiesPerUser'] ?? 100,
            'defaultDay'               => $rawConfig['defaultDay'] ?? "monday",
            'defaultTime'              => $rawConfig['defaultTime'] ?? "19:00",
            'eventName'                => $rawConfig['eventName'] ?? "Dota 2",
            'logLevel'                 => $rawConfig['logLevel'] ?? "Info",
            'timeZone'                 => $rawConfig['timeZone'] ?? \ini_get('date.timezone'),
            'nextcloudAppUser'         => $rawConfig['nextcloudAppUser'] ?? null,
            'nextcloudAppPassword'     => $rawConfig['nextcloudAppPassword'] ?? null,
            'discordDotaMentionId'     => $rawConfig['discordDotaMentionId'] ?? null,

            'directoryAvailabilities'  => $this->extractAvailabilitiesDirFromConfig($rawConfig),
            'token'                    => $this->extractTokenFromConfig($rawConfig),
            'tokenNextcloud'           => $this->extractTokenNextcloudFromConfig($rawConfig),
        ];

        return $normalisedConfig;
    }

    private function extractAvailabilitiesDirFromConfig(array $config): string
    {
        $valueFromConfig = $config['directoryAvailabilities'];
        $default         = '$HOME/.local/share/discord-availability/availabilities';

        return $this->normalisePathWithEnvVars($valueFromConfig ?? $default);
    }

    private function extractTokenFromConfig(array $validatedConfig): string
    {
        if (isset($validatedConfig['token'])) {
            return $validatedConfig['token'];
        }

        $path = $this->normalisePathWithEnvVars($validatedConfig['tokenFile']);

        $fileContents = \file_get_contents($path);

        if (!$fileContents) {
            die('Failed to read token from file: ' . $path . PHP_EOL);
        }

        return \trim($fileContents);
    }

    private function extractTokenNextcloudFromConfig(array $validatedConfig): string
    {
        if (isset($validatedConfig['tokenNextcloud'])) {
            return $validatedConfig['tokenNextcloud'];
        }

        $path = $this->normalisePathWithEnvVars($validatedConfig['tokenFileNextcloud']);

        $fileContents = \file_get_contents($path);

        if (!$fileContents) {
            die('Failed to read Nextcloud token from file: ' . $path . PHP_EOL);
        }

        return \trim($fileContents);
    }

    private function normalisePathWithEnvVars(string $path): string
    {
        $path = $this->expandEnvVars($path);
        $path = $this->normalisePath($path);

        return $path;
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

        $cwd = \getcwd();

        if (!$cwd) {
            die('Could not determine current working directory.' . PHP_EOL);
        }

        $segments     = [$cwd, $path];
        $absolutePath = \implode(DIRECTORY_SEPARATOR, $segments);

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
        if (1 === \preg_match('@^(/|[A-Z]:\\\\|\\\\\\\\)@', $path)) {
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
        $path = $this->extractAvailabilitiesDirFromConfig($config);
        if (!\file_exists($path)) {
            $msg = 'The "directoryAvailabilities" directory does not exist.' . PHP_EOL;
            $msg = $msg . \sprintf('  Specified:   "%s"' . PHP_EOL, $config['directoryAvailabilities']);
            $msg = $msg . \sprintf('  Interpreted: "%s"' . PHP_EOL, $path);
            return $msg;
        }

        $tokens = [
            'token'          => 'tokenFile',
            'tokenNextcloud' => 'tokenFileNextcloud',
        ];

        foreach ($tokens as $token => $tokenFile) {
            if (isset($config[$token]) and isset($config[$tokenFile])) {
                return \sprintf(
                    'One of "%1$s" or "%2$s" must be set but both are set.',
                    $token,
                    $tokenFile
                );
            }

            if (!isset($config[$token]) and !isset($config[$tokenFile])) {
                return \sprintf(
                    'One of "%1$s" or "%2$s" must be set but neither are set.',
                    $token,
                    $tokenFile
                );
            }

            if (isset($config[$tokenFile])) {
                $path = $this->normalisePathWithEnvVars($config[$tokenFile]);

                if (!\file_exists($path)) {
                    $msg = \sprintf('The "%1$s" file does not exist.' . PHP_EOL, $tokenFile);
                    $msg = $msg . \sprintf('  Specified:   "%s"' . PHP_EOL, $config[$tokenFile]);
                    $msg = $msg . \sprintf('  Interpreted: "%s"' . PHP_EOL, $path);
                    return $msg;
                }
            }
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
     * Returns the Nextcloud API token.
     *
     * @return string
     */
    public function getAPITokenNextcloud(): string
    {
        return $this->get('tokenNextcloud');
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

    private function expandEnvVars(string $path): string
    {
        \preg_match_all('/\$([A-Z_]+)/', $path, $environmentMatches, PREG_SET_ORDER);

        foreach ($environmentMatches as $match) {
            if (isset($match[0], $match[1])) {
                $matchFull                = $match[0];
                $matchEnvironmentVariable = $match[1];
                $environmentVariable      = \getenv($matchEnvironmentVariable);

                if (false === $environmentVariable) {
                    die(\sprintf('Could not get value for environment variable "%s".', $matchEnvironmentVariable) . PHP_EOL);
                }

                $path = \str_replace($matchFull, $environmentVariable, $path);
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

    public function getTimeZone(): string
    {
        return $this->get('timeZone');
    }

    public function getNextcloudAppUser(): string
    {
        return $this->get('nextcloudAppUser');
    }

    public function getDiscordDotaRoleId(): string
    {
        return $this->get('discordDotaMentionId');
    }
}
