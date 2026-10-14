<?php

require __DIR__ . '/../vendor/autoload.php';

$pdo = pdo();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version    text PRIMARY KEY,
        applied_at timestamptz NOT NULL DEFAULT now()
    )'
);

$applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(__DIR__ . '/../db/migrations/*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $version = basename($file, '.sql');

    if (in_array($version, $applied, true)) {
        continue;
    }

    $sql = file_get_contents($file);

    $pdo->beginTransaction();

    try {
        $pdo->exec($sql);

        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
        $stmt->execute(['version' => $version]);

        $pdo->commit();
        fwrite(STDOUT, "applied {$version}\n");
    } catch (Throwable $exception) {
        $pdo->rollBack();
        fwrite(STDERR, "failed {$version}: {$exception->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, "migrations up to date\n");