<?php
declare(strict_types=1);

/** Current schema version, stored in PRAGMA user_version. */
const DB_SCHEMA_VERSION = 2;

/** Return the shared PDO connection, initialising/migrating the schema on first use. */
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

    // The schema is idempotent (CREATE ... IF NOT EXISTS): initialises a fresh DB and
    // adds new tables to an existing one. Column changes follow in db_migrate().
    $schema = @file_get_contents(dirname(__DIR__) . '/schema.sql');
    if ($schema !== false && $schema !== '') {
        $pdo->exec($schema);
    }
    db_migrate($pdo);

    if ($fresh) {
        @chmod($file, 0660);
    }

    return $pdo;
}

/**
 * Versioned, idempotent migrations for databases created by earlier versions.
 * Each step checks the actual table layout, so it is safe to re-run.
 */
function db_migrate(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version >= DB_SCHEMA_VERSION) {
        return;
    }
    $cols = static fn(string $table): array => array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');

    // v1 (pre-1.0): per-poll e-mail requirement.
    if (!in_array('email_required', $cols('polls'), true)) {
        $pdo->exec('ALTER TABLE polls ADD COLUMN email_required INTEGER NOT NULL DEFAULT 1');
    }
    // v2 (1.0.0): personal edit tokens for participants.
    if (!in_array('edit_token_hash', $cols('contributions'), true)) {
        $pdo->exec('ALTER TABLE contributions ADD COLUMN edit_token_hash TEXT');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contrib_edit ON contributions(edit_token_hash)');

    $pdo->exec('PRAGMA user_version = ' . DB_SCHEMA_VERSION);
}
