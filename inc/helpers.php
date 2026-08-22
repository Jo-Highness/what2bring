<?php
declare(strict_types=1);

/** HTML-escape for output. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** URL-safe secret token, >=128 bit by default. */
function token_new(int $bytes = 16): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

/** Stable, non-reversible dedup key for an e-mail address. */
function email_hash(string $email): string
{
    return hash_hmac('sha256', mb_strtolower(trim($email)), (string) cfg('app_pepper'));
}

function base_url(): string
{
    return rtrim((string) cfg('base_url'), '/');
}

/** Build an internal URL via the query-based router (works without mod_rewrite). */
function url(string $route, array $params = []): string
{
    return base_url() . '/index.php?' . http_build_query(['r' => $route] + $params);
}

/** Public, shareable link for a poll. */
function poll_link(string $token): string
{
    return base_url() . '/index.php?' . http_build_query(['r' => 'poll', 'token' => $token]);
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function redirect_route(string $route, array $params = []): never
{
    redirect(url($route, $params));
}

function is_valid_email(string $email): bool
{
    return (bool) filter_var(trim($email), FILTER_VALIDATE_EMAIL);
}

/* --- flash messages + sticky form values (session-backed) --- */
function flash(string $msg, string $type = 'info'): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function set_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function old(string $key, $default = '')
{
    return $_SESSION['old'][$key] ?? $default;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

/** German long date from an ISO YYYY-MM-DD string. */
function fmt_date(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $t = strtotime($iso);
    if ($t === false) {
        return $iso;
    }
    $days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    return $days[(int) date('w', $t)] . ', ' . date('d.m.Y', $t);
}

/**
 * Render a template inside the shared layout and echo the result.
 * $public=true marks user-facing pages (kept out of admin nav).
 */
function view(string $template, array $vars, string $title, bool $public = false): void
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/../templates/' . $template . '.php';
    $content = ob_get_clean();
    $flashes = take_flashes();
    require __DIR__ . '/../templates/layout.php';
}
