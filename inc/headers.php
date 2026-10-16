<?php
declare(strict_types=1);

/**
 * Content-Security-Policy. All JavaScript lives in public/assets/app.js, so scripts
 * are restricted to 'self'. Inline style attributes are still used by the templates
 * (no script execution possible through them), hence 'unsafe-inline' for styles only.
 */
function csp_policy(): string
{
    return implode('; ', [
        "default-src 'none'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data:",
        "connect-src 'self'",
        "form-action 'self'",
        "base-uri 'none'",
        "frame-ancestors 'none'",
    ]);
}

/** Security headers for every HTML response (the whole app is private / noindex). */
function send_security_headers(bool $https): void
{
    if (headers_sent()) {
        return;
    }
    header('Content-Security-Policy: ' . csp_policy());
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
    if ($https && cfg('hsts', true)) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/** Start the session with hardened settings. */
function start_secure_session(string $name, bool $https, string $sameSite = 'Lax'): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => $sameSite,
        'secure'   => $https,
    ]);
    session_start();
}
