<?php
// Tiny dependency-free test helpers.
declare(strict_types=1);

$GLOBALS['__fail'] = 0;
$GLOBALS['__pass'] = 0;

function check(bool $ok, string $what): void
{
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . PHP_EOL;
    if ($ok) {
        $GLOBALS['__pass']++;
    } else {
        $GLOBALS['__fail']++;
    }
}

function finish(): never
{
    echo PHP_EOL . $GLOBALS['__pass'] . ' passed, ' . $GLOBALS['__fail'] . ' failed' . PHP_EOL;
    exit($GLOBALS['__fail'] ? 1 : 0);
}

function tmpdir(string $prefix): string
{
    $d = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(4));
    mkdir($d, 0700, true);
    register_shutdown_function(static function () use ($d): void {
        exec('rm -rf ' . escapeshellarg($d));
    });
    return $d;
}
