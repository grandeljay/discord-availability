<?php

/**
 * Discord availability
 *
 * Lets you know about players availability for Dota.
 *
 * @link OAuth (live) https://discord.com/api/oauth2/authorize?client_id=1100405938801872956&permissions=0&redirect_uri=https%3A%2F%2Fgithub.com%2Fgrandeljay%2Fdiscord-availability&response_type=code&scope=identify%20applications.commands%20bot
 * @link OAuth (test) https://discord.com/api/oauth2/authorize?client_id=1115198941659664397&permissions=0&redirect_uri=https%3A%2F%2Fgithub.com%2Fgrandeljay%2Fdiscord-availability&response_type=code&scope=identify%20bot%20applications.commands
 *
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

namespace Grandeljay\Availability;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/autoload.php';

$availability = new Bot();
$availability->initialise();
