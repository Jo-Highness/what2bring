<?php
declare(strict_types=1);

/** Return the shared PDO connection, initialising the schema on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $file = (string) cfg('db_file');
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $fresh = !is_file($file) || filesize($file) === 0;

    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    // Schema is fully idempotent (CREATE ... IF NOT EXISTS), so run it on every
    // connect: initialises a fresh DB and auto-migrates an existing one (e.g. new
    // tables added in later versions).
    $schema = @file_get_contents(dirname(__DIR__) . '/schema.sql');
    if ($schema !== false && $schema !== '') {
        $pdo->exec($schema);
    }
    if ($fresh) {
        @chmod($file, 0660);
    }

    return $pdo;
}
