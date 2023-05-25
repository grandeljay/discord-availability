<?php

namespace Grandeljay\Availability;

require __DIR__ . '/vendor/autoload.php';

spl_autoload_register(
    function ($fullyQualifiedNamespace) {
        $namespace      = substr($fullyQualifiedNamespace, 0, strlen(__NAMESPACE__));
        $namespaceIsOwn = __NAMESPACE__ === $namespace;

        if (!$namespaceIsOwn) {
            return;
        }

        $filepath = __DIR__ . '/src/' . str_replace('\\', '/', $fullyQualifiedNamespace) . '.php';

        if (!file_exists($filepath)) {
            return;
        }

        require $filepath;
    }
);

$availability = new Availability();
$availability->initialise();
