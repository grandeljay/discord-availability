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
            preg_match('/\$([A-Z]+)/', $filepath, $environmentMatches);

            if (isset($environmentMatches[0], $environmentMatches[1])) {
                $matchFull                = $environmentMatches[0];
                $matchEnvironmentVariable = $environmentMatches[1];

                $filepath = str_replace($matchFull, getenv($matchEnvironmentVariable), $filepath);
            }

            if (file_exists($filepath)) {
                $contents     = file_get_contents($filepath);
                $this->config = json_decode($contents, true);

                return;
            }
        }

        die('Missing config.json. Please refer to README.md.\n');
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
     * Returns the Discord API token, depending on what `use_live_token` is set
     * to.
     *
     * @return string
     */
    public function getAPIToken(): string
    {

        $token = '';

        $use_live_token = $this->get('use_live_token');
        $token_live     = $this->get('token_live');
        $token_test     = $this->get('token_test');

        if (true === $use_live_token) {
            $token = $token_live;
        } else {
            $token = $token_test;
        }

        return $token;
    }
}
