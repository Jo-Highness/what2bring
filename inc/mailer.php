<?php
declare(strict_types=1);

/**
 * Minimal dependency-free SMTP client (no Composer, no PHPMailer, no mail()).
 * Sends ONE plain-text UTF-8 message to ONE recipient. Header fields are
 * hardened against CR/LF injection. Returns true on success; on failure it
 * throws a RuntimeException with the SMTP dialogue detail.
 */

final class SmtpException extends RuntimeException
{
}

/** Encode a header value as RFC 2047 (UTF-8, base64) and strip CR/LF. */
function mail_encode_header(string $value): string
{
    $value = str_replace(["\r", "\n"], '', $value);
    if (preg_match('/[^\x20-\x7E]/', $value)) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
    return $value;
}

/** Guard against header injection in an address (local display only). */
function mail_clean_addr(string $addr): string
{
    return str_replace(["\r", "\n", "\0"], '', trim($addr));
}

/**
 * Send a single mail. $cfg is the ['smtp'=>...] sub-array; if cfg('mail_dry_run')
 * is true, the message is appended to data/mail.log instead of being sent.
 */
function send_mail(string $to_email, string $to_name, string $subject, string $body): bool
{
    $smtp = (array) cfg('smtp', []);
    $from_email = mail_clean_addr((string) ($smtp['from_email'] ?? ''));
    $from_name = (string) ($smtp['from_name'] ?? '');
    $to_email = mail_clean_addr($to_email);

    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'From: ' . mail_encode_header($from_name) . ' <' . $from_email . '>';
    $headers[] = 'To: ' . mail_encode_header($to_name) . ' <' . $to_email . '>';
    $headers[] = 'Subject: ' . mail_encode_header($subject);
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';
    $headers[] = 'Auto-Submitted: auto-generated';

    // Normalise the body to CRLF and dot-stuff for the DATA phase.
    $body = preg_replace("/\r\n|\r|\n/", "\r\n", $body);
    $body = preg_replace('/^\./m', '..', $body);
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n";

    if (cfg('mail_dry_run')) {
        $log = dirname(__DIR__) . '/data/mail.log';
        @file_put_contents(
            $log,
            "=== " . date('c') . " DRY-RUN ===\r\n" . $message . "\r\n\r\n",
            FILE_APPEND
        );
        return true;
    }

    return smtp_deliver($smtp, $from_email, $to_email, $message);
}

function smtp_deliver(array $smtp, string $from, string $to, string $message): bool
{
    $host = (string) ($smtp['host'] ?? '');
    $port = (int) ($smtp['port'] ?? 587);
    $secure = (string) ($smtp['secure'] ?? 'starttls');
    $user = (string) ($smtp['user'] ?? '');
    $pass = (string) ($smtp['pass'] ?? '');
    $timeout = (int) ($smtp['timeout'] ?? 15);
    $verify = (bool) ($smtp['verify_tls'] ?? true);

    if ($host === '') {
        throw new SmtpException('SMTP-Host ist nicht konfiguriert.');
    }

    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => $verify,
        'verify_peer_name'  => $verify,
        'SNI_enabled'       => true,
        'peer_name'         => $host,
    ]]);

    $transport = ($secure === 'ssl') ? "ssl://$host:$port" : "tcp://$host:$port";
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($transport, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new SmtpException("Verbindung zu $host:$port fehlgeschlagen: $errstr ($errno)");
    }
    stream_set_timeout($fp, $timeout);

    $read = function () use ($fp): array {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            // multi-line replies: "250-" continues, "250 " ends
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($data, 0, 3);
        return [$code, $data];
    };
    $cmd = function (string $line) use ($fp, $read): array {
        fwrite($fp, $line . "\r\n");
        return $read();
    };

    try {
        [$code] = $read();
        if ($code !== 220) {
            throw new SmtpException("Unerwartete Begrüßung ($code)");
        }

        $ehlo = 'what2bring.local';
        [$code, $resp] = $cmd("EHLO $ehlo");
        if ($code !== 250) {
            throw new SmtpException("EHLO abgelehnt ($code): $resp");
        }

        if ($secure === 'starttls') {
            [$code] = $cmd('STARTTLS');
            if ($code !== 220) {
                throw new SmtpException("STARTTLS abgelehnt ($code)");
            }
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!stream_socket_enable_crypto($fp, true, $crypto)) {
                throw new SmtpException('TLS-Aushandlung (STARTTLS) fehlgeschlagen.');
            }
            [$code, $resp] = $cmd("EHLO $ehlo");
            if ($code !== 250) {
                throw new SmtpException("EHLO nach STARTTLS abgelehnt ($code): $resp");
            }
        }

        if ($user !== '') {
            [$code] = $cmd('AUTH LOGIN');
            if ($code !== 334) {
                throw new SmtpException("AUTH LOGIN abgelehnt ($code)");
            }
            [$code] = $cmd(base64_encode($user));
            if ($code !== 334) {
                throw new SmtpException("SMTP-Benutzer abgelehnt ($code)");
            }
            [$code, $resp] = $cmd(base64_encode($pass));
            if ($code !== 235) {
                throw new SmtpException("SMTP-Anmeldung fehlgeschlagen ($code)");
            }
        }

        [$code, $resp] = $cmd("MAIL FROM:<$from>");
        if ($code !== 250) {
            throw new SmtpException("MAIL FROM abgelehnt ($code): $resp");
        }
        [$code, $resp] = $cmd("RCPT TO:<$to>");
        if ($code !== 250 && $code !== 251) {
            throw new SmtpException("RCPT TO abgelehnt ($code): $resp");
        }
        [$code] = $cmd('DATA');
        if ($code !== 354) {
            throw new SmtpException("DATA abgelehnt ($code)");
        }
        fwrite($fp, $message . "\r\n.\r\n");
        [$code, $resp] = $read();
        if ($code !== 250) {
            throw new SmtpException("Nachricht abgelehnt ($code): $resp");
        }
        $cmd('QUIT');
    } finally {
        fclose($fp);
    }

    return true;
}
