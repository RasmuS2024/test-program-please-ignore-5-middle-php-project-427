<?php

/**
 * Load key=value pairs from a .env file into the environment.
 *
 * Existing environment variables are never overwritten, so real env vars (for
 * example on Render) always win over the local .env file.
 */
function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' || $first === "'") && $first === $last) {
                $value = substr($value, 1, -1);
            }
        }

        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}