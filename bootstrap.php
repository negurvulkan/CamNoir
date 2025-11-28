<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/src/helpers.php';

// Load environment variables from .env if present
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    foreach (file($envPath) as $line) {
        if (preg_match('/^\s*#/', $line) || trim($line) === '') {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"' ");
        putenv("{$name}={$value}");
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/EventRepository.php';
require_once __DIR__ . '/src/SessionRepository.php';
require_once __DIR__ . '/src/PhotoRepository.php';
