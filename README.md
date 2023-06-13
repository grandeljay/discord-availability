# Discord Availability

Discord Availability is a discord bot used to track who will be available for dota.

## Requirements

1. PHP v8.1 or higher
1. Composer

## Getting started

1. Setup configuration

    Copy `config-example.json` to either `~/.config/discord-availability/config.json` or `/etc/discord-availability/config.json`.

1. Install dependencies

    ```sh
    composer install
    ```

1. Run the script

    ```sh
    php availability.php
    ```

    Do not attempt to run this via a web server (i. e. from the browser), it will only work from the command line.

1. Add it to your server

    Use the following link to authorize the bot for your server. It only asks for the permissions it needs (as little as possible).

    https://discord.com/api/oauth2/authorize?client_id=1100405938801872956&permissions=0&scope=bot%20applications.commands

Once started, the script will run in loop, indefinitly until stopped. A schedule/cron is not necessary.

## Bot commands

The bot currently offers three different slash commands:

1. `/available <date>` where `date` is a [`strtotime`](https://www.php.net/manual/en/function.strtotime.php) compatible phrase. According to PHP that is: _about any English textual datetime description_.
2. `/unavailable <date>` where `date` is a [`strtotime`](https://www.php.net/manual/en/function.strtotime.php) compatible phrase. According to PHP that is: _about any English textual datetime description_.
3. `/availability` which lists the availability of everybody. _Everybody_ in this case means users who have used the `/available` or `/unavailable` command at least once.

Once a user is in the system, the bot will automatically assume the user is available unless he specifies otherwise.

Furthermore, the bot will attempt to detect the availability of users based on their messages (outside of the slash commands). This applies to all users who have used the `/available` or `/unavailable` command at least once.

## Notes

If you are a bro who is hosting this for a bro, you da real MVP.
