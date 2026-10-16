<?php
declare(strict_types=1);

/**
 * Configuration loading.
 *
 * Sources, highest precedence first:
 *   1. Environment variables  W2B_*  (or W2B_*_FILE pointing at a file, e.g. a Docker secret)
 *   2. The PHP config file    (default: <app>/config.php, override with W2B_CONFIG)
 *   3. Built-in defaults      (w2b_config_defaults())
 *
 * The app counts as "configured" when the config file exists OR both
 * W2B_ADMIN_PASSWORD_HASH and W2B_APP_PEPPER are provided via the environment.
 */

/** Read an env var, supporting the NAME_FILE convention. Returns null when unset/empty. */
function w2b_env(string $name): ?string
{
    $v = getenv($name);
    if ($v !== false && $v !== '') {
        return $v;
    }
    $file = getenv($name . '_FILE');
    if ($file !== false && $file !== '' && is_readable($file)) {
        $c = trim((string) file_get_contents($file));
        return $c !== '' ? $c : null;
    }
    return null;
}

function w2b_env_bool(string $name): ?bool
{
    $v = w2b_env($name);
    if ($v === null) {
        return null;
    }
    return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
}

/** Directory for runtime state (SQLite DB, mail log, setup token). */
function w2b_data_dir(): string
{
    return rtrim(w2b_env('W2B_DATA_DIR') ?? (dirname(__DIR__) . '/data'), '/');
}

/** Path of the PHP config file (may not exist yet). */
function w2b_config_file(): string
{
    return w2b_env('W2B_CONFIG') ?? (dirname(__DIR__) . '/config.php');
}

/** True when the config file exists or the mandatory secrets come from the environment. */
function w2b_is_configured(): bool
{
    return is_file(w2b_config_file())
        || (w2b_env('W2B_ADMIN_PASSWORD_HASH') !== null && w2b_env('W2B_APP_PEPPER') !== null);
}

function w2b_config_defaults(): array
{
    return [
        'admin_password_hash'  => '',
        'app_pepper'           => '',
        'base_url'             => '',
        'default_lang'         => 'de',
        'db_file'              => w2b_data_dir() . '/what2bring.sqlite',
        'mail_dry_run'         => true,
        // Dry-run mail log: 'redacted' (recipient address masked), 'full' or 'off'.
        'mail_log'             => 'redacted',
        // Proxies whose X-Forwarded-For / X-Forwarded-Proto headers are trusted (IPs or CIDRs).
        'trusted_proxies'      => [],
        // Send Strict-Transport-Security when the request is HTTPS.
        'hsts'                 => true,
        // Log an admin out after this many seconds without activity.
        'admin_idle_timeout'   => 1800,
        // Delete polls automatically N days after their date (or creation if undated). 0 = off.
        'purge_after_days'     => 0,
        // Max. public submissions per IP and poll within 10 minutes.
        'submit_rate_limit'    => 20,
        // Send participants a confirmation mail containing their personal edit link.
        'confirmation_mail'    => false,
        'smtp'                 => [
            'host'       => '',
            'port'       => 587,
            'secure'     => 'starttls',
            'user'       => '',
            'pass'       => '',
            'from_email' => '',
            'from_name'  => 'What2Bring',
            'timeout'    => 15,
            'verify_tls' => true,
        ],
    ];
}

/** Values provided through the environment (only those that are set). */
function w2b_config_from_env(): array
{
    $c = [];
    $str = [
        'W2B_ADMIN_PASSWORD_HASH' => 'admin_password_hash',
        'W2B_APP_PEPPER'          => 'app_pepper',
        'W2B_BASE_URL'            => 'base_url',
        'W2B_DEFAULT_LANG'        => 'default_lang',
        'W2B_DB_FILE'             => 'db_file',
        'W2B_MAIL_LOG'            => 'mail_log',
    ];
    foreach ($str as $env => $key) {
        if (($v = w2b_env($env)) !== null) {
            $c[$key] = $v;
        }
    }
    $int = [
        'W2B_ADMIN_IDLE_TIMEOUT' => 'admin_idle_timeout',
        'W2B_PURGE_AFTER_DAYS'   => 'purge_after_days',
        'W2B_SUBMIT_RATE_LIMIT'  => 'submit_rate_limit',
    ];
    foreach ($int as $env => $key) {
        if (($v = w2b_env($env)) !== null) {
            $c[$key] = (int) $v;
        }
    }
    $bool = [
        'W2B_MAIL_DRY_RUN'      => 'mail_dry_run',
        'W2B_HSTS'              => 'hsts',
        'W2B_CONFIRMATION_MAIL' => 'confirmation_mail',
    ];
    foreach ($bool as $env => $key) {
        if (($v = w2b_env_bool($env)) !== null) {
            $c[$key] = $v;
        }
    }
    if (($v = w2b_env('W2B_TRUSTED_PROXIES')) !== null) {
        $c['trusted_proxies'] = array_values(array_filter(array_map('trim', explode(',', $v)), 'strlen'));
    }
    $smtp = [];
    foreach (['host', 'secure', 'user', 'pass', 'from_email', 'from_name'] as $k) {
        if (($v = w2b_env('W2B_SMTP_' . strtoupper($k))) !== null) {
            $smtp[$k] = $k === 'secure' && $v === 'none' ? '' : $v;
        }
    }
    foreach (['port', 'timeout'] as $k) {
        if (($v = w2b_env('W2B_SMTP_' . strtoupper($k))) !== null) {
            $smtp[$k] = (int) $v;
        }
    }
    if (($v = w2b_env_bool('W2B_SMTP_VERIFY_TLS')) !== null) {
        $smtp['verify_tls'] = $v;
    }
    if ($smtp) {
        $c['smtp'] = $smtp;
    }
    return $c;
}

/** Build the effective configuration array (defaults < file < environment). */
function w2b_load_config(): array
{
    $cfg = w2b_config_defaults();
    $file = w2b_config_file();
    if (is_file($file)) {
        $fromFile = require $file;
        if (is_array($fromFile)) {
            $smtp = array_merge($cfg['smtp'], (array) ($fromFile['smtp'] ?? []));
            $cfg = array_merge($cfg, $fromFile);
            $cfg['smtp'] = $smtp;
        }
    }
    $env = w2b_config_from_env();
    if (isset($env['smtp'])) {
        $env['smtp'] = array_merge($cfg['smtp'], $env['smtp']);
    }
    $cfg = array_merge($cfg, $env);
    if (!is_array($cfg['trusted_proxies'])) {
        $cfg['trusted_proxies'] = array_filter(array_map('trim', explode(',', (string) $cfg['trusted_proxies'])), 'strlen');
    }
    return $cfg;
}

function cfg(?string $key = null, $default = null)
{
    if (!isset($GLOBALS['__cfg'])) {
        $GLOBALS['__cfg'] = w2b_load_config();
    }
    $c = $GLOBALS['__cfg'];
    if ($key === null) {
        return $c;
    }
    return array_key_exists($key, $c) ? $c[$key] : $default;
}
