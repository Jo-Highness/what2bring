<?php
declare(strict_types=1);

/** Supported UI languages (code => native label). */
function available_langs(): array
{
    return ['de' => 'Deutsch', 'en' => 'English', 'es' => 'Español'];
}

/** Configured default language (falls back to German). */
function default_lang(): string
{
    $d = function_exists('cfg') ? (string) cfg('default_lang', 'de') : 'de';
    return isset(available_langs()[$d]) ? $d : 'de';
}

/**
 * Resolve the active language, in order:
 *   1. explicit ?lang= switch (persisted to the session)
 *   2. session choice
 *   3. browser Accept-Language
 *   4. configured default
 */
function current_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $langs = available_langs();
    $hasSession = session_status() === PHP_SESSION_ACTIVE;

    if (isset($_GET['lang']) && isset($langs[$_GET['lang']])) {
        $lang = (string) $_GET['lang'];
        if ($hasSession) {
            $_SESSION['lang'] = $lang;
        }
        return $lang;
    }
    if ($hasSession && isset($_SESSION['lang']) && isset($langs[$_SESSION['lang']])) {
        return $lang = (string) $_SESSION['lang'];
    }
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    foreach ($langs as $code => $_label) {
        if ($accept !== '' && str_starts_with($accept, $code)) {
            return $lang = $code;
        }
    }
    return $lang = default_lang();
}

/** Load and cache a language's string table. */
function load_lang(string $code): array
{
    static $cache = [];
    if (isset($cache[$code])) {
        return $cache[$code];
    }
    $file = __DIR__ . '/lang/' . $code . '.php';
    $cache[$code] = is_file($file) ? (array) require $file : [];
    return $cache[$code];
}

/**
 * Translate a key for the active language. Missing keys fall back to German,
 * then to the key itself. {placeholders} are replaced from $params.
 */
function t(string $key, array $params = []): string
{
    $s = load_lang(current_lang())[$key] ?? (load_lang('de')[$key] ?? $key);
    foreach ($params as $k => $v) {
        $s = str_replace('{' . $k . '}', (string) $v, $s);
    }
    return $s;
}

/** Build a URL that switches language while preserving the current route/query. */
function lang_switch_url(string $code): string
{
    $q = $_GET;
    $q['lang'] = $code;
    return '?' . http_build_query($q);
}
