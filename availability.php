<?php

/**
 * Discord availability
 *
 * Lets you know about players availability for Dota.
 *
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

namespace Grandeljay\Availability;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/autoload.php';

$availability = new Bot();
$availability->run();
