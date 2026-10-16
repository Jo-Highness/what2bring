<?php
// Consistent online backup of the SQLite database (includes data still in the WAL file).
//   php bin/backup.php                 -> <data dir>/backups/what2bring-YYYYmmdd-HHMMSS.sqlite
//   php bin/backup.php /path/to/file.sqlite
// In Docker: docker compose exec what2bring php bin/backup.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../inc/app.php';

if (!w2b_is_configured()) {
    fwrite(STDERR, "What2Bring is not configured yet.\n");
    exit(1);
}
$target = $argv[1] ?? (dirname((string) cfg('db_file')) . '/backups/what2bring-' . date('Ymd-His') . '.sqlite');
if (file_exists($target)) {
    fwrite(STDERR, "Target exists: $target\n");
    exit(1);
}
if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0770, true)) {
    fwrite(STDERR, 'Cannot create directory ' . dirname($target) . "\n");
    exit(1);
}
$old = umask(0077);
try {
    // VACUUM INTO (SQLite >= 3.27) writes a transactionally consistent, compacted copy.
    db()->prepare('VACUUM INTO ?')->execute([$target]);
} catch (Throwable $e) {
    fwrite(STDERR, 'Backup failed: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    umask($old);
}
echo "Backup written: $target\n";
