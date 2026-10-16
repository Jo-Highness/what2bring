<?php
declare(strict_types=1);

// Web bootstrap: library code + hardened session + security headers.
require __DIR__ . '/app.php';

$__https = request_is_https();
// Session cookie renamed in 1.0.0 (was "fmn_sess"): admins are logged out once after the update.
start_secure_session('w2b_sess', $__https);
send_security_headers($__https);
