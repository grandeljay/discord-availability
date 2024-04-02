# Discord Availability

Discord Availability is a discord bot used to track who will be available for a certain event (i. e. a game).

## Requirements

1. PHP v8.1 (v8.2 works but produces deprected notices)
1. Composer

## Getting started

1. Setup configuration

    Copy `config-example.json` to either `~/.config/discord-availability/config.json` or `/etc/discord-availability/config.json`.

1. Run the script

    ```sh
    php availability.php
    ```

    Do not attempt to run this via a web server (i. e. from the browser), it will only work from the command line.

1. Add it to your server

    1. Create an app (key) for your server: https://discord.com/developers/applications
    1. Set the OAuth scope to

        - `identify`
        - `bot`

    1. Copy the generated URL and follow the link to add it to your server. It should look like this:
        ```
         https://discord.com/api/oauth2/authorize?client_id=XXXXXXXXXXXXXXXXXXX&permissions=0&redirect_uri=https%3A%2F%2Fgithub.com%2Fgrandeljay%2Fdiscord-availability&response_type=code&scope=identify%20applications.commands%20bot
        ```

Once started, the script will run in loop, indefinitly until stopped. A schedule/cron is not necessary.

## Bot commands

The bot currently offers three different slash commands:

1. `/available <date>` where `date` is a [`strtotime`](https://www.php.net/manual/en/function.strtotime.php) compatible phrase. According to PHP that is: _about any English textual datetime description_.
2. `/unavailable <date>` where `date` is a [`strtotime`](https://www.php.net/manual/en/function.strtotime.php) compatible phrase. According to PHP that is: _about any English textual datetime description_.
3. `/availability` which lists the availability of everybody. _Everybody_ in this case means users who have used the `/available` or `/unavailable` command at least once.
4. `/shutdown` which shuts the bot down. You must be an administrator to do this. Using this before restarting the bot will speed up the process significantly.

Once a user is in the system, the bot will automatically assume the user is available unless he specifies otherwise.

Furthermore, the bot will attempt to detect the availability of users based on their messages (outside of the slash commands). This applies to all users who have used the `/available` or `/unavailable` command at least once.

## Bot startup arguments

-   `--install`

    Updates all of the discord slash commands. Unused/orpahned commands are removed and new ones are added.

## Notes

If you are a bro who is hosting this for a bro, you da real MVP.
