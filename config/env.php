<?php

/**
 * Minimal .env loader. Populates getenv()/$_ENV from a `.env` file at the
 * project root, without overriding variables already set by the hosting
 * environment (real server env vars always win over the file).
 */

if (!function_exists('load_env')) {
    function load_env(string $path): void
    {
        static $loaded = false;
        if ($loaded || !is_file($path)) {
            return;
        }
        $loaded = true;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
            $name = trim($name);
            $value = trim($value);

            if ($value !== '' && $value[0] === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
            } elseif ($value !== '' && $value[0] === "'" && substr($value, -1) === "'") {
                $value = substr($value, 1, -1);
            }

            if ($name === '' || getenv($name) !== false) {
                continue;
            }

            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

load_env(__DIR__ . '/../.env');
