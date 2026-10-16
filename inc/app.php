<?php
declare(strict_types=1);

/*
 * Loads configuration and all library code WITHOUT side effects (no session, no
 * headers). Used by the web front controller (via bootstrap.php), the CLI tools in
 * bin/ and the tests.
 */

require_once __DIR__ . '/phpcheck.php';
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/net.php';
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ratelimit.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/repo_admin.php';
require_once __DIR__ . '/repo_public.php';
require_once __DIR__ . '/setup.php';
