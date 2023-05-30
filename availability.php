<?php

namespace Grandeljay\Availability;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/autoload.php';

$availability = new Availability();
$availability->initialise();
