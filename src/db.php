<?php

require_once __DIR__ . '/env.php';

/**
 * Build a PostgreSQL PDO connection from the DATABASE_URL environment variable.
 *
 * The connection is created once per process and reused. PERSISTENT connections
 * are intentionally avoided: a leftover aborted transaction would poison them.
 */
function pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    loadEnv(dirname(__DIR__) . '/.env');

    $url = getenv('DATABASE_URL');

    if ($url === false || $url === '') {
        throw new RuntimeException('DATABASE_URL is not set');
    }

    $parts = parse_url($url);

    if ($parts === false || !isset($parts['host'])) {
        throw new RuntimeException('DATABASE_URL is malformed');
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        $parts['host'],
        $parts['port'] ?? 5432,
        ltrim($parts['path'] ?? '', '/')
    );

    $pdo = new PDO($dsn, $parts['user'] ?? null, $parts['pass'] ?? null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}