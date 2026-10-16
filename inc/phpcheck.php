<?php
// Deliberately written in PHP 5/7-compatible syntax so that an outdated PHP shows a
// clear message instead of a parse error or a cryptic fatal error later on.
if (PHP_VERSION_ID < 80100) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo 'What2Bring requires PHP 8.1 or newer (found ' . PHP_VERSION . ').'
        . ' Please switch to a newer PHP version in your hosting control panel.' . PHP_EOL
        . 'What2Bring benötigt PHP 8.1 oder neuer (gefunden: ' . PHP_VERSION . ').'
        . ' Bitte im Hosting-Kundenmenü eine neuere PHP-Version auswählen.' . PHP_EOL;
    exit(1);
}
if (!extension_loaded('pdo_sqlite')) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo 'What2Bring requires the PHP extension pdo_sqlite. / What2Bring benötigt die PHP-Erweiterung pdo_sqlite.' . PHP_EOL;
    exit(1);
}
