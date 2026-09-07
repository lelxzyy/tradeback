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

// Vercel rewrites every request to this function. Restore the original path
// so Laravel's router sees /api/v1/... instead of /api/index.php.
if (isset($_GET['path'])) {
    $path = '/'.ltrim((string) $_GET['path'], '/');
    unset($_GET['path']);
    $query = http_build_query($_GET);
    $_SERVER['REQUEST_URI'] = $path.($query !== '' ? '?'.$query : '');
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
}

require dirname(__DIR__).'/public/index.php';
