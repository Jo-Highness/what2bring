<?php
// Print the web installer's setup token (creating it if necessary) while the app is
// not configured yet. Called by the Docker entrypoint; can also be run by hand:
//   php bin/setup-token.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__ . '/../inc/app.php';

if (w2b_is_configured()) {
    echo "What2Bring is already configured — no setup token needed.\n";
    exit(0);
}
$token = setup_ensure_token(false);
if ($token === null) {
    fwrite(STDERR, 'Cannot store a setup token in ' . setup_token_file() . " (not writable). Set W2B_SETUP_TOKEN instead.\n");
    exit(1);
}
$line = str_repeat('=', 72);
echo $line, "\n",
    "  What2Bring is not configured yet. Open the site in your browser and\n",
    "  enter this setup token in the installer:\n\n",
    "      ", $token, "\n\n",
    "  (stored in ", setup_token_file(), w2b_env('W2B_SETUP_TOKEN') !== null ? '; taken from W2B_SETUP_TOKEN' : '', ")\n",
    $line, "\n";
