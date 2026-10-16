<?php
// Unit/regression tests (no web server needed). Run: php tests/security_test.php
declare(strict_types=1);

require __DIR__ . '/lib.php';

$tmp = tmpdir('w2b-unit-');
$adminPw = 'correct horse battery';
putenv('W2B_DATA_DIR=' . $tmp);
putenv('W2B_CONFIG=' . $tmp . '/config.php');       // does not exist: env-only configuration
putenv('W2B_ADMIN_PASSWORD_HASH=' . password_hash($adminPw, PASSWORD_DEFAULT));
file_put_contents($tmp . '/pepper.txt', str_repeat('ab', 32) . "\n");
putenv('W2B_APP_PEPPER_FILE=' . $tmp . '/pepper.txt'); // *_FILE convention (Docker secrets)
putenv('W2B_BASE_URL=https://events.example.org');
putenv('W2B_MAIL_DRY_RUN=1');
putenv('W2B_TRUSTED_PROXIES=10.0.0.0/8, 2001:db8::/32');
putenv('W2B_SETUP_TOKEN');
putenv('W2B_SETUP_ALLOW_LOCAL');

ob_start(); // buffer output so the session functions behave as in a web request (flushed at exit)
ini_set('session.save_path', $tmp);
ini_set('session.use_cookies', '0');
session_start();

require __DIR__ . '/../inc/app.php';

/* ------------------------------------------------------------- existing guards */
check(csv_safe('=HYPERLINK("http://x")') === '\'=HYPERLINK("http://x")', 'csv: = neutralised');
check(csv_safe('+1') === "'+1" && csv_safe('@SUM(A1)') === "'@SUM(A1)", 'csv: + and @ neutralised');
check(csv_safe("\tx") === "'\tx", 'csv: tab neutralised');
check(csv_safe('Anna') === 'Anna' && csv_safe('') === '', 'csv: plain values untouched');
check(mail_encode_name('Anna Muster') === 'Anna Muster', 'mail: plain ASCII name untouched');
$n = mail_encode_name('x <evil@example.org>, y');
check(str_starts_with($n, '=?UTF-8?B?') && !str_contains($n, '<'), 'mail: specials encoded');
check(!str_contains(mail_encode_name("a\r\nBcc: z@example.org"), "\n"), 'mail: CRLF stripped');
check(str_starts_with(mail_encode_name('Jürgen'), '=?UTF-8?B?'), 'mail: non-ASCII encoded');
check(e('<a href="x">\'') === '&lt;a href=&quot;x&quot;&gt;&#039;', 'escape: e() encodes HTML specials and quotes');

/* --------------------------------------------------------------- configuration */
check(w2b_is_configured(), 'config: env-only configuration counts as configured');
check(cfg('app_pepper') === str_repeat('ab', 32), 'config: W2B_*_FILE is read and trimmed');
check(cfg('base_url') === 'https://events.example.org', 'config: env value applied');
check(cfg('trusted_proxies') === ['10.0.0.0/8', '2001:db8::/32'], 'config: trusted proxies parsed from env');
check(cfg('mail_log') === 'redacted' && cfg('purge_after_days') === 0, 'config: privacy-friendly defaults');
check(str_starts_with((string) cfg('db_file'), $tmp), 'config: DB defaults into W2B_DATA_DIR');

/* ------------------------------------------------------------ proxies / client IP */
check(ip_in_cidr('10.1.2.3', '10.0.0.0/8') && !ip_in_cidr('11.1.2.3', '10.0.0.0/8'), 'cidr: IPv4');
check(ip_in_cidr('192.168.1.7', '192.168.1.0/29') && !ip_in_cidr('192.168.1.8', '192.168.1.0/29'), 'cidr: IPv4 partial byte');
check(ip_in_cidr('2001:db8::1', '2001:db8::/32') && !ip_in_cidr('2001:db9::1', '2001:db8::/32'), 'cidr: IPv6');
check(!ip_in_cidr('10.0.0.1', '2001:db8::/32') && !ip_in_cidr('garbage', '10.0.0.0/8'), 'cidr: family mismatch / garbage');

$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
unset($_SERVER['HTTPS']);
check(client_ip() === '203.0.113.9', 'proxy: X-Forwarded-For ignored from untrusted peer');
check(!request_is_https(), 'proxy: X-Forwarded-Proto ignored from untrusted peer');
$_SERVER['REMOTE_ADDR'] = '10.0.0.5';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6, 198.51.100.7, 10.0.0.9';
check(client_ip() === '198.51.100.7', 'proxy: right-most untrusted X-Forwarded-For hop used');
check(request_is_https(), 'proxy: X-Forwarded-Proto honoured from trusted proxy');
unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

/* --------------------------------------------------------------- login throttle */
$_SERVER['REMOTE_ADDR'] = '198.51.100.20';
$t = 1_000_000;
for ($i = 0; $i < 5; $i++) {
    admin_login('wrong', $t + $i);
}
check(login_locked_seconds($t + 5) > 0, 'login: locked after 5 failures');
$_SESSION = [];                                   // attacker discards the session cookie …
session_regenerate_id(true);
check(login_locked_seconds($t + 5) > 0, 'login: lock persists across sessions (stored in DB)');
check(admin_login($adminPw, $t + 6) === 'locked' && !is_admin(), 'login: correct password ignored while locked');
$_SERVER['REMOTE_ADDR'] = '198.51.100.21';
check(login_locked_seconds($t + 6) === 0, 'login: other IP not affected by per-IP lock');
$_SERVER['REMOTE_ADDR'] = '198.51.100.20';
check(login_locked_seconds($t + 6 + LOGIN_IP_MAX_LOCK) === 0, 'login: lock expires');
check(admin_login($adminPw, $t + 6 + LOGIN_IP_MAX_LOCK) === 'ok' && is_admin(), 'login: success after lock expired');
check(rl_count('login_fail', 'ip:198.51.100.20', LOGIN_WINDOW, $t + 6 + LOGIN_IP_MAX_LOCK) === 0, 'login: success clears IP failures');
$st = db()->query("SELECT key_hash FROM rate_events")->fetchAll(PDO::FETCH_COLUMN);
check(!array_filter($st, fn($k) => str_contains((string) $k, '198.51.100')), 'login: IP addresses stored only as HMAC');
admin_logout();
session_start();

$t2 = 2_000_000;
for ($i = 0; $i < LOGIN_ACCOUNT_LIMIT; $i++) {
    $_SERVER['REMOTE_ADDR'] = '192.0.2.' . ($i % 250 + 1) . '';
    // spread over many IPs, max 2 per IP -> never hits the per-IP lock
    admin_login('wrong', $t2 + $i);
}
$_SERVER['REMOTE_ADDR'] = '198.51.100.99';
check(login_locked_seconds($t2 + LOGIN_ACCOUNT_LIMIT) > 0, 'login: account-wide delay after distributed attack');
check(login_locked_seconds($t2 + LOGIN_ACCOUNT_LIMIT + LOGIN_WINDOW + 1) === 0, 'login: account-wide delay expires');

/* ----------------------------------------------------------------- session idle */
$_SESSION = ['is_admin' => true, 'admin_last_seen' => 100];
check(admin_session_check(100 + 60) === false && is_admin(), 'session: active admin stays logged in');
check(admin_session_check(160 + (int) cfg('admin_idle_timeout') + 1) === true && !is_admin(), 'session: idle admin logged out');

/* ------------------------------------------------------------------ setup token */
$tok = setup_ensure_token(false);
check(is_string($tok) && strlen($tok) >= 32, 'setup: token generated');
check(is_file(setup_token_file()) && (fileperms(setup_token_file()) & 0777) === 0600, 'setup: token file is mode 0600');
check(setup_ensure_token(false) === $tok, 'setup: token is stable');
check(setup_token_valid($tok) && setup_token_valid(" $tok\n"), 'setup: correct token accepted');
check(!setup_token_valid('') && !setup_token_valid('x' . $tok) && !setup_token_valid(substr($tok, 1)), 'setup: wrong token rejected');
putenv('W2B_SETUP_TOKEN=from-env-0123456789');
check(setup_token_valid('from-env-0123456789') && !setup_token_valid($tok), 'setup: W2B_SETUP_TOKEN takes precedence');
putenv('W2B_SETUP_TOKEN');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
check(!setup_local_bypass(), 'setup: localhost needs the token unless explicitly allowed');
putenv('W2B_SETUP_ALLOW_LOCAL=1');
check(setup_local_bypass(), 'setup: localhost bypass when W2B_SETUP_ALLOW_LOCAL=1');
$_SERVER['REMOTE_ADDR'] = '203.0.113.1';
check(!setup_local_bypass(), 'setup: bypass never applies to remote clients');
putenv('W2B_SETUP_ALLOW_LOCAL');
setup_finish();
check(!is_file(setup_token_file()), 'setup: token file removed after installation');

$cfgPhp = setup_render_config([
    'hash' => '$2y$10$abc', 'pepper' => 'p', 'base_url' => "https://x.example/it's", 'lang' => 'en',
    'dry_run' => true, 'trusted_proxies' => ['10.0.0.1'],
    'smtp' => ['host' => 'h', 'port' => 25, 'secure' => '', 'user' => 'u', 'pass' => "pa'ss\\", 'from_email' => 'f@x', 'from_name' => 'N'],
], $tmp . '/gen-config.php');
file_put_contents($tmp . '/gen-config.php', $cfgPhp);
$gen = require $tmp . '/gen-config.php';
check(is_array($gen) && $gen['smtp']['pass'] === "pa'ss\\" && $gen['base_url'] === "https://x.example/it's"
    && $gen['trusted_proxies'] === ['10.0.0.1'] && !isset($gen['db_file']), 'setup: generated config is valid PHP with escaped values');

/* ---------------------------------------------------------- public submissions */
$p1 = admin_create_poll('Sommerfest', '', '2026-07-01', 'who_and_what', 1, ['Kuchen', 'Salat']);
$p2 = admin_create_poll('Other', '', null, 'none', 0, ['Bier']);
$items1 = array_column(admin_get_items($p1['id']), 'id');
$items2 = array_column(admin_get_items($p2['id']), 'id');

$a = public_submit($p1['id'], 'Anna', 'anna@example.org', [$items1[0] => '2 Bleche']);
check($a['status'] === 'created' && strlen($a['token']) >= 40, 'submit: new entry gets an edit token');
$row = db()->query('SELECT edit_token_hash FROM contributions WHERE id = ' . (int) $a['id'])->fetchColumn();
check($row === hash('sha256', $a['token']), 'submit: only the token hash is stored');

$b = public_submit($p1['id'], 'Mallory', ' ANNA@Example.org ', [$items1[1] => 'nothing']);
check($b['status'] === 'email_taken', 'submit: same e-mail without token is rejected (no silent overwrite)');
$c = admin_contributions($p1['id']);
check(count($c) === 1 && $c[0]['name'] === 'Anna' && $c[0]['items'][0]['detail'] === '2 Bleche', 'submit: victim entry unchanged');

$u = public_submit($p1['id'], 'Anna M.', 'anna@example.org', [$items1[1] => 'Nudelsalat'], $a['token']);
check($u['status'] === 'updated' && $u['id'] === $a['id'], 'submit: edit token updates the own entry');
$c = admin_contributions($p1['id']);
check(count($c) === 1 && $c[0]['name'] === 'Anna M.' && $c[0]['items'][0]['label'] === 'Salat', 'submit: selection replaced');

$own = participant_own_entry($p1['id'], $a['token']);
check($own !== null && $own['email'] === 'anna@example.org' && isset($own['selections'][$items1[1]]), 'submit: own entry loadable with token');
check(participant_own_entry($p2['id'], $a['token']) === null, 'submit: token is bound to its poll');
check(participant_own_entry($p1['id'], 'guess') === null, 'submit: wrong token finds nothing');
check(public_submit($p1['id'], 'X', 'x@example.org', [$items1[0] => ''], 'bogus')['status'] === 'invalid_token', 'submit: invalid token rejected');

$z = public_submit($p1['id'], 'Zoe', 'zoe@example.org', [$items1[0] => '']);
check(public_submit($p1['id'], 'Anna', 'zoe@example.org', [], $a['token'])['status'] === 'email_taken', 'submit: cannot switch own entry to someone else\'s e-mail');

$x = public_submit($p2['id'], 'NoMail1', '', [$items2[0] => '', $items1[0] => 'foreign item']);
$y = public_submit($p2['id'], 'NoMail2', '', [$items2[0] => '']);
$c2 = admin_contributions($p2['id']);
check(count($c2) === 2 && count($c2[0]['items']) === 1, 'submit: entries without e-mail are separate; foreign items ignored');

check(public_withdraw($p1['id'], $z['token']) && count(admin_contributions($p1['id'])) === 1, 'submit: participant can withdraw own entry');
check(!public_withdraw($p1['id'], $z['token']) && !public_withdraw($p1['id'], ''), 'submit: withdraw needs a valid token');

$pub = json_encode([public_summary_by_item($p1['id']), public_contributor_names($p1['id'])]);
check(!str_contains((string) $pub, '@'), 'privacy: public views never contain e-mail addresses');

/* ------------------------------------------------------------------- validation */
$poll = ['email_required' => 1];
$v = validate_submission(['name' => 'A', 'email' => 'a@example.org', 'items' => 'notanarray'], $poll, $items1);
check($v['selections'] === [] && in_array(t('msg.need_selection'), $v['problems'], true), 'validate: scalar items[] handled (no TypeError)');
$v = validate_submission(['name' => ['x'], 'email' => 'a@example.org', 'items' => [$items1[0] => 1], 'detail' => 'x'], $poll, $items1);
check(in_array(t('msg.need_name'), $v['problems'], true) && $v['selections'] === [$items1[0] => ''], 'validate: array name / scalar detail[] handled');
$v = validate_submission(['name' => str_repeat('ä', LIMIT_NAME + 1), 'email' => 'a@example.org', 'items' => [$items1[0] => 1]], $poll, $items1);
check(count($v['problems']) === 1, 'validate: over-long name rejected (multibyte-aware)');
$v = validate_submission(['name' => str_repeat('ä', LIMIT_NAME), 'email' => 'a@example.org', 'items' => [$items1[0] => 1]], $poll, $items1);
check($v['problems'] === [], 'validate: name at the limit accepted');
$v = validate_submission(['name' => 'A', 'email' => 'a@example.org', 'items' => [$items1[0] => 1], 'detail' => [$items1[0] => str_repeat('x', LIMIT_DETAIL + 1)]], $poll, $items1);
check(count($v['problems']) === 1, 'validate: over-long detail rejected');
$v = validate_submission(['name' => 'A', 'email' => str_repeat('a', 250) . '@example.org', 'items' => [$items1[0] => 1]], $poll, $items1);
check(count($v['problems']) === 1, 'validate: over-long e-mail rejected');
$many = array_fill_keys(range(1, LIMIT_SELECTIONS + 1), 1) + [$items1[0] => 1];
$v = validate_submission(['name' => 'A', 'email' => 'a@example.org', 'items' => $many], $poll, $items1);
check(in_array(t('msg.too_many_items', ['max' => LIMIT_SELECTIONS]), $v['problems'], true), 'validate: too many items rejected');
$v = validate_submission(['name' => 'A', 'email' => '', 'items' => [$items1[0] => 1]], ['email_required' => 0], $items1);
check($v['problems'] === [], 'validate: optional e-mail may be empty');
$f = validate_poll_form(['title' => str_repeat('t', LIMIT_TITLE + 1), 'item_label' => 'x', 'event_date' => '1.1.2026']);
check(count($f['problems']) === 1 && $f['labels'] === [] && $f['event_date'] === '', 'validate: poll form limits, scalar item_label[], bad date dropped');

/* ------------------------------------------------------------------ data protection */
check(purge_old_polls(0) === 0, 'purge: disabled with 0 days');
$old = admin_create_poll('Old', '', '2020-01-01', 'none', 1, ['A']);
db()->prepare("UPDATE polls SET created_at = '2020-01-01 00:00:00' WHERE id = ?")->execute([$old['id']]);
$undated = admin_create_poll('Undated recent', '', null, 'none', 1, ['A']);
check(purge_old_polls(30, '2026-07-15') === 1 && admin_get_poll($old['id']) === null && admin_get_poll($undated['id']) !== null
    && admin_get_poll($p1['id']) !== null, 'purge: only polls older than the retention are deleted');
$cid = admin_contributions($p2['id'])[0]['id'];
check(admin_delete_contribution($p2['id'], (int) $cid) && !admin_delete_contribution($p1['id'], (int) $cid), 'admin: single entry can be deleted (scoped to its poll)');

send_mail('secret.person@example.org', 'Secret', 'Subj', 'Body');
$log = (string) @file_get_contents(mail_log_file());
check(str_contains($log, 's***@example.org') && !str_contains($log, 'secret.person@'), 'mail log: recipient redacted by default');
check(dirname(mail_log_file()) === $tmp, 'mail log: lives next to the database');
$GLOBALS['__cfg']['mail_log'] = 'off';
@unlink(mail_log_file());
send_mail('a@example.org', 'A', 'S', 'B');
check(!is_file(mail_log_file()), 'mail log: can be disabled');

/* ---------------------------------------------------------------------- migration */
$legacy = new PDO('sqlite:' . $tmp . '/legacy.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$legacy->exec("CREATE TABLE polls (id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT NOT NULL UNIQUE, title TEXT NOT NULL,
    description TEXT, event_date TEXT, visibility TEXT NOT NULL DEFAULT 'names_only', created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT);
  CREATE TABLE contributions (id INTEGER PRIMARY KEY AUTOINCREMENT, poll_id INTEGER NOT NULL, name TEXT NOT NULL,
    email_hash TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT, UNIQUE (poll_id, email_hash));
  INSERT INTO polls (token, title) VALUES ('tok', 'Legacy');
  INSERT INTO contributions (poll_id, name, email_hash) VALUES (1, 'Old entry', 'h');");
$legacy->exec((string) file_get_contents(__DIR__ . '/../schema.sql'));
db_migrate($legacy);
$pc = array_column($legacy->query('PRAGMA table_info(polls)')->fetchAll(), 'name');
$cc = array_column($legacy->query('PRAGMA table_info(contributions)')->fetchAll(), 'name');
check(in_array('email_required', $pc, true) && in_array('edit_token_hash', $cc, true), 'migration: missing columns added');
check((int) $legacy->query('PRAGMA user_version')->fetchColumn() === DB_SCHEMA_VERSION, 'migration: user_version set');
check($legacy->query('SELECT name FROM contributions')->fetchColumn() === 'Old entry', 'migration: existing data preserved');
db_migrate($legacy);
check(true, 'migration: idempotent');

/* ----------------------------------------------------------- i18n / templates / CSP */
$langs = [];
foreach (['de', 'en', 'es'] as $l) {
    $langs[$l] = array_keys(require __DIR__ . '/../inc/lang/' . $l . '.php');
    sort($langs[$l]);
}
check($langs['de'] === $langs['en'] && $langs['de'] === $langs['es'], 'i18n: de/en/es have identical keys');
$src = (string) file_get_contents(__DIR__ . '/../public/index.php');
check(!preg_match("/view\\([^;]*,\\s*'[A-ZÄÖÜa-z][^']*'\\s*,\\s*true\\)/", $src), 'i18n: no hard-coded page titles in the router');

$inline = [];
$files = array_merge(glob(__DIR__ . '/../templates/*.php'), glob(__DIR__ . '/../templates/*/*.php'), [__DIR__ . '/../inc/install.php']);
foreach ($files as $file) {
    $html = (string) file_get_contents($file);
    if (preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $html) || preg_match('/\son[a-z]+\s*=\s*["\']/i', $html) || stripos($html, 'javascript:') !== false) {
        $inline[] = basename($file);
    }
}
check($inline === [], 'csp: no inline scripts or event handlers in templates' . ($inline ? ' (' . implode(', ', $inline) . ')' : ''));
check(str_contains(csp_policy(), "script-src 'self'") && !preg_match("/script-src[^;]*unsafe/", csp_policy())
    && str_contains(csp_policy(), "frame-ancestors 'none'"), 'csp: strict script-src and frame-ancestors');

finish();
