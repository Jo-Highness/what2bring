<?php
declare(strict_types=1);

/** Read a free-form setting value; returns $default when unset/empty. */
function get_setting(string $key, string $default = ''): string
{
    $st = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $st->execute([$key]);
    $val = $st->fetchColumn();
    return ($val === false || $val === null) ? $default : (string) $val;
}

/** Upsert a setting value. */
function set_setting(string $key, string $value): void
{
    // INSERT OR REPLACE is version-safe (no ON CONFLICT dependency); settings has
    // no foreign keys pointing at it, so replacing the row is harmless.
    $st = db()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
    $st->execute([$key, $value]);
}

/** Load a bundled default template (used to pre-fill the admin editor). */
function legal_default(string $key): string
{
    $file = dirname(__DIR__) . '/templates/legal/' . $key . '_default.txt';
    $txt = @file_get_contents($file);
    return $txt !== false ? $txt : '';
}
