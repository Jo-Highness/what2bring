<?php
declare(strict_types=1);

/*
 * Admin authentication.
 *
 * Failed logins are persisted in SQLite (rate_events), so throttling survives a
 * new session / cleared cookies:
 *   - per client IP: from the 5th failure within 15 min an exponential lockout
 *     (30 s, 60 s, 120 s ... capped at 15 min) applies;
 *   - per account (there is a single admin): from the 50th failure within 15 min
 *     (from any IPs) every further attempt is delayed by 60 s.
 * The password hash is always verified, also while locked, so the response time
 * does not reveal the lock state; a correct password is ignored while locked.
 */

const LOGIN_WINDOW = 900;
const LOGIN_IP_FREE_FAILS = 5;
const LOGIN_IP_MAX_LOCK = 900;
const LOGIN_ACCOUNT_LIMIT = 50;
const LOGIN_ACCOUNT_LOCK = 60;

function is_admin(): bool
{
    return !empty($_SESSION['is_admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect_route('admin.login');
    }
}

/** Seconds until the next login attempt is accepted for this client (0 = not locked). */
function login_locked_seconds(?int $now = null): int
{
    $now ??= time();
    $ip = client_ip();
    $wait = 0;

    $ipFails = rl_count('login_fail', 'ip:' . $ip, LOGIN_WINDOW, $now);
    if ($ipFails >= LOGIN_IP_FREE_FAILS) {
        $delay = min(LOGIN_IP_MAX_LOCK, 30 * (2 ** min(10, $ipFails - LOGIN_IP_FREE_FAILS)));
        $wait = max($wait, rl_last('login_fail', 'ip:' . $ip, LOGIN_WINDOW, $now) + $delay - $now);
    }
    $accFails = rl_count('login_fail', 'account:admin', LOGIN_WINDOW, $now);
    if ($accFails >= LOGIN_ACCOUNT_LIMIT) {
        $wait = max($wait, rl_last('login_fail', 'account:admin', LOGIN_WINDOW, $now) + LOGIN_ACCOUNT_LOCK - $now);
    }
    return max(0, $wait);
}

/**
 * Attempt an admin login. Returns 'ok', 'fail' or 'locked'.
 */
function admin_login(string $password, ?int $now = null): string
{
    $now ??= time();
    $hash = (string) cfg('admin_password_hash');
    // Always run the (slow) hash verification for uniform timing.
    $ok = $hash !== '' && password_verify($password, $hash);

    if (login_locked_seconds($now) > 0) {
        return 'locked';
    }
    $ip = client_ip();
    if ($ok) {
        rl_clear('login_fail', 'ip:' . $ip);
        session_regenerate_id(true);
        $_SESSION = ['is_admin' => true, 'admin_last_seen' => $now, 'csrf' => token_new(32), 'lang' => $_SESSION['lang'] ?? null];
        return 'ok';
    }
    rl_hit('login_fail', 'ip:' . $ip, $now);
    rl_hit('login_fail', 'account:admin', $now);
    return 'fail';
}

/** Enforce the admin idle timeout; returns true if the admin session just expired. */
function admin_session_check(?int $now = null): bool
{
    if (!is_admin()) {
        return false;
    }
    $now ??= time();
    $timeout = (int) cfg('admin_idle_timeout', 1800);
    $last = (int) ($_SESSION['admin_last_seen'] ?? 0);
    if ($timeout > 0 && $now - $last > $timeout) {
        $lang = $_SESSION['lang'] ?? null;
        $_SESSION = [];
        session_regenerate_id(true);
        if ($lang) {
            $_SESSION['lang'] = $lang;
        }
        return true;
    }
    $_SESSION['admin_last_seen'] = $now;
    return false;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}
