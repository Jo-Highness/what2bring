<?php
declare(strict_types=1);

// --- Load config (lives ONE level above the public/ document root) ---
$__cfg_file = dirname(__DIR__) . '/config.php';
if (!is_file($__cfg_file)) {
    http_response_code(500);
    exit('Konfiguration fehlt: bitte config.php.example nach config.php kopieren und ausfüllen.');
}
$GLOBALS['__cfg'] = require $__cfg_file;

function cfg(?string $key = null, $default = null)
{
    $c = $GLOBALS['__cfg'];
    if ($key === null) {
        return $c;
    }
    return array_key_exists($key, $c) ? $c[$key] : $default;
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/i18n.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/repo_admin.php';
require __DIR__ . '/repo_public.php';

// --- Secure session ---
if (session_status() === PHP_SESSION_NONE) {
    $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('fmn_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
    ]);
    session_start();
}

// --- Security headers (the whole app is private) ---
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
