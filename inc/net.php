<?php
declare(strict_types=1);

/**
 * Request metadata that depends on reverse proxies. X-Forwarded-* headers are
 * only honoured when the direct peer (REMOTE_ADDR) is listed in the
 * 'trusted_proxies' config (IP addresses or CIDR ranges, IPv4 and IPv6).
 */

function ip_in_cidr(string $ip, string $cidr): bool
{
    $cidr = trim($cidr);
    if ($cidr === '') {
        return false;
    }
    [$net, $bits] = str_contains($cidr, '/') ? explode('/', $cidr, 2) : [$cidr, null];
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton($net);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
        return false;
    }
    $max = strlen($ipBin) * 8;
    $bits = $bits === null ? $max : (int) $bits;
    if ($bits < 0 || $bits > $max) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    if (strncmp($ipBin, $netBin, $bytes) !== 0) {
        return false;
    }
    $rest = $bits % 8;
    if ($rest === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $rest)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
}

function is_trusted_proxy(string $ip): bool
{
    foreach ((array) cfg('trusted_proxies', []) as $cidr) {
        if (ip_in_cidr($ip, (string) $cidr)) {
            return true;
        }
    }
    return false;
}

/** Client IP: REMOTE_ADDR, or the right-most untrusted X-Forwarded-For hop behind trusted proxies. */
function client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remote === '' || !is_trusted_proxy($remote)) {
        return $remote !== '' ? $remote : 'unknown';
    }
    $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    $hops = array_reverse(array_filter(array_map('trim', explode(',', $xff)), 'strlen'));
    foreach ($hops as $hop) {
        if (@inet_pton($hop) === false) {
            break; // garbage -> stop, fall back to the last trusted hop
        }
        if (!is_trusted_proxy($hop)) {
            return $hop;
        }
        $remote = $hop;
    }
    return $remote;
}

/** Whether the client connection is HTTPS (directly or via a trusted proxy). */
function request_is_https(): bool
{
    $https = (string) ($_SERVER['HTTPS'] ?? '');
    if ($https !== '' && strtolower($https) !== 'off') {
        return true;
    }
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remote !== '' && is_trusted_proxy($remote)) {
        $proto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        return $proto === 'https';
    }
    return false;
}
