<?php
declare(strict_types=1);

/**
 * Persistent, SQLite-backed event counters for throttling (login failures,
 * public submissions). Keys (IP addresses etc.) are stored only as an HMAC with
 * the app pepper, never in plain text. Rows older than one day are pruned.
 */

const RL_MAX_AGE = 86400;

function rl_key(string $key): string
{
    $pepper = (string) cfg('app_pepper', '');
    return hash_hmac('sha256', $key, $pepper !== '' ? $pepper : 'w2b-rate-limit');
}

function rl_hit(string $bucket, string $key, ?int $now = null): void
{
    $now ??= time();
    db()->prepare('INSERT INTO rate_events (bucket, key_hash, ts) VALUES (?, ?, ?)')
        ->execute([$bucket, rl_key($key), $now]);
    // Opportunistic pruning (~2 % of writes) keeps the table small without a cron job.
    if (random_int(1, 50) === 1) {
        rl_prune($now);
    }
}

function rl_prune(?int $now = null): void
{
    $now ??= time();
    db()->prepare('DELETE FROM rate_events WHERE ts < ?')->execute([$now - RL_MAX_AGE]);
}

/** Number of events for $bucket/$key within the last $window seconds. */
function rl_count(string $bucket, string $key, int $window, ?int $now = null): int
{
    $now ??= time();
    $st = db()->prepare('SELECT COUNT(*) FROM rate_events WHERE bucket = ? AND key_hash = ? AND ts > ?');
    $st->execute([$bucket, rl_key($key), $now - $window]);
    return (int) $st->fetchColumn();
}

/** Timestamp of the most recent event (0 if none) within the window. */
function rl_last(string $bucket, string $key, int $window, ?int $now = null): int
{
    $now ??= time();
    $st = db()->prepare('SELECT MAX(ts) FROM rate_events WHERE bucket = ? AND key_hash = ? AND ts > ?');
    $st->execute([$bucket, rl_key($key), $now - $window]);
    return (int) $st->fetchColumn();
}

function rl_clear(string $bucket, string $key): void
{
    db()->prepare('DELETE FROM rate_events WHERE bucket = ? AND key_hash = ?')->execute([$bucket, rl_key($key)]);
}
