<?php

/**
 * Vercel serverless entrypoint for Laravel.
 * Runtime files must use /tmp because the deployed source is read-only.
 */
$runtimeVariables = [
    'APP_CONFIG_CACHE' => '/tmp/config.php',
    'APP_EVENTS_CACHE' => '/tmp/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/services.php',
    'VIEW_COMPILED_PATH' => '/tmp/views',
];

foreach ($runtimeVariables as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

if (! is_dir('/tmp/views')) {
    mkdir('/tmp/views', 0755, true);
}

require dirname(__DIR__).'/public/index.php';
