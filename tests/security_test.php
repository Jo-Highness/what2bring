<?php
// Minimal regression checks (Audit 2026-10). Run: php tests/security_test.php
declare(strict_types=1);
require __DIR__ . '/../inc/helpers.php';
require __DIR__ . '/../inc/mailer.php';

$fail = 0;
function check(bool $ok, string $what): void
{
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . PHP_EOL;
    $fail += $ok ? 0 : 1;
}

check(csv_safe('=HYPERLINK("http://x")') === '\'=HYPERLINK("http://x")', 'csv: = neutralised');
check(csv_safe('+1') === "'+1" && csv_safe('@SUM(A1)') === "'@SUM(A1)", 'csv: + and @ neutralised');
check(csv_safe("\tx") === "'\tx", 'csv: tab neutralised');
check(csv_safe('Anna') === 'Anna' && csv_safe('') === '', 'csv: plain values untouched');

check(mail_encode_name('Anna Muster') === 'Anna Muster', 'mail: plain ASCII name untouched');
$n = mail_encode_name('x <evil@example.org>, y');
check(str_starts_with($n, '=?UTF-8?B?') && !str_contains($n, '<'), 'mail: specials encoded');
check(!str_contains(mail_encode_name("a\r\nBcc: z@example.org"), "\n"), 'mail: CRLF stripped');
check(str_starts_with(mail_encode_name('Jürgen'), '=?UTF-8?B?'), 'mail: non-ASCII encoded');

exit($fail ? 1 : 0);
