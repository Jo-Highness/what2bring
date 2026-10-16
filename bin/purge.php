<?php
// Delete polls older than 'purge_after_days' (or the number of days given as argument)
// and prune throttling counters. Suitable for a daily cron job:
//   php bin/purge.php          (uses purge_after_days from the config / W2B_PURGE_AFTER_DAYS)
//   php bin/purge.php 90       (explicit retention in days)
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../inc/app.php';

if (!w2b_is_configured()) {
    fwrite(STDERR, "What2Bring is not configured yet.\n");
    exit(1);
}
$days = isset($argv[1]) ? (int) $argv[1] : (int) cfg('purge_after_days', 0);
rl_prune();
if ($days <= 0) {
    echo "Auto-purge disabled (purge_after_days = 0); throttling counters pruned.\n";
    exit(0);
}
$n = purge_old_polls($days);
set_setting('last_purge', date('Y-m-d'));
echo "Deleted $n poll(s) older than $days day(s).\n";
