<?php
declare(strict_types=1);

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

/**
 * Verify the admin password with a light throttle against brute force.
 * Returns true on success.
 */
function admin_login(string $password): bool
{
    $now = time();
    $gate = $_SESSION['login_gate'] ?? ['fails' => 0, 'until' => 0];
    if ($gate['until'] > $now) {
        return false; // locked out for a short window
    }

    $hash = (string) cfg('admin_password_hash');
    $ok = $hash !== '' && password_verify($password, $hash);

    if ($ok) {
        unset($_SESSION['login_gate']);
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        return true;
    }

    $gate['fails']++;
    // exponential-ish backoff after 5 failures, capped at 5 minutes
    if ($gate['fails'] >= 5) {
        $gate['until'] = $now + min(300, 5 * ($gate['fails'] - 4));
    }
    $_SESSION['login_gate'] = $gate;
    return false;
}

function login_locked_seconds(): int
{
    $until = $_SESSION['login_gate']['until'] ?? 0;
    return max(0, $until - time());
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
