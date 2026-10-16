<?php
// End-to-end tests against PHP's built-in web server: installer (setup token),
// login throttling, participant edit tokens, limits, headers.
// Run: php tests/http_test.php
declare(strict_types=1);

require __DIR__ . '/lib.php';

$tmp = tmpdir('w2b-http-');
$port = random_int(20000, 40000);
$base = "http://127.0.0.1:$port";
$env = [
    'W2B_DATA_DIR'          => $tmp,
    'W2B_CONFIG'            => $tmp . '/config.php',
    'W2B_SUBMIT_RATE_LIMIT' => '12',
    'PATH'                  => (string) getenv('PATH'),
];
$cmd = [PHP_BINARY, '-d', 'display_errors=stderr', '-S', "127.0.0.1:$port", '-t', dirname(__DIR__) . '/public'];
$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $tmp . '/server.out', 'a'], 2 => ['file', $tmp . '/server.err', 'a']], $pipes, null, $env);
register_shutdown_function(static function () use ($proc): void {
    proc_terminate($proc);
});
for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $port) === false; $i++) {
    usleep(100_000);
}

final class Client
{
    public array $cookies = [];
    public function __construct(private string $base)
    {
    }

    /** @return array{status:int, headers:array<string,string[]>, body:string} */
    public function req(string $method, string $path, array $form = [], array $extraHeaders = []): array
    {
        $h = $extraHeaders;
        if ($this->cookies) {
            $h[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        }
        $opts = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'header' => ''];
        if ($method === 'POST') {
            $h[] = 'Content-Type: application/x-www-form-urlencoded';
            $opts['content'] = http_build_query($form);
        }
        $opts['header'] = implode("\r\n", $h);
        $body = (string) @file_get_contents($this->base . $path, false, stream_context_create(['http' => $opts]));
        $headers = [];
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) {
                $status = (int) $m[1];
                continue;
            }
            [$k, $v] = array_map('trim', explode(':', $line, 2)) + [1 => ''];
            $headers[strtolower($k)][] = $v;
            if (strtolower($k) === 'set-cookie') {
                [$pair] = explode(';', $v, 2);
                [$ck, $cv] = explode('=', $pair, 2);
                if ($cv === '' || str_contains(strtolower($v), 'expires=thu, 01 jan 1970') || preg_match('/max-age=0/i', $v)) {
                    unset($this->cookies[$ck]);
                } else {
                    $this->cookies[$ck] = $cv;
                }
            }
        }
        return ['status' => $status, 'headers' => $headers, 'body' => $body];
    }

    public function get(string $path, array $extraHeaders = []): array
    {
        return $this->req('GET', $path, [], $extraHeaders);
    }

    /** GET a page, then POST to $postPath with its CSRF token. */
    public function submit(string $formPath, string $postPath, array $form): array
    {
        $page = $this->get($formPath);
        preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $m);
        return $this->req('POST', $postPath, $form + ['_csrf' => $m[1] ?? '']);
    }

    /** Follow one redirect and return the target page. */
    public function follow(array $res): array
    {
        $loc = $res['headers']['location'][0] ?? '';
        return $this->get((string) preg_replace('#^https?://[^/]+#', '', $loc));
    }
}

function hdr(array $res, string $name): string
{
    return implode(', ', $res['headers'][strtolower($name)] ?? []);
}

function db_open(string $tmp): PDO
{
    return new PDO('sqlite:' . $tmp . '/what2bring.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}

$c = new Client($base);

/* --------------------------------------------------------------------- installer */
$r = $c->get('/index.php?r=health');
check($r['status'] === 200 && trim($r['body']) === 'ok', 'health endpoint answers without configuration');

$r = $c->get('/');
check($r['status'] === 200 && str_contains($r['body'], 'name="setup_token"'), 'installer shown and asks for the setup token');
check(str_contains(hdr($r, 'content-security-policy'), "script-src 'self'"), 'installer sends CSP');
check(str_contains(hdr($r, 'set-cookie'), 'HttpOnly') && str_contains(hdr($r, 'set-cookie'), 'SameSite=Strict'), 'installer cookie is HttpOnly + SameSite=Strict');
$tokenFile = $tmp . '/.setup_token';
check(is_file($tokenFile), 'setup token file created in the data dir');
$token = trim((string) @file_get_contents($tokenFile));
check(str_contains((string) @file_get_contents($tmp . '/server.err'), $token), 'setup token written to the server log');

$install = [
    'admin_pw' => 'super secret pw', 'admin_pw2' => 'super secret pw', 'base_url' => $base,
    'default_lang' => 'en', 'mail_dry_run' => '1', 'smtp_port' => '587', 'smtp_secure' => 'starttls',
];
$r = $c->submit('/', '/index.php', $install + ['setup_token' => 'wrong-token']);
check($r['status'] === 403 && !is_file($tmp . '/config.php'), 'installer rejects a wrong setup token (nothing written)');
$r = $c->submit('/', '/index.php', $install);
check(!is_file($tmp . '/config.php'), 'installer rejects a missing setup token');
$r = $c->submit('/', '/index.php', $install + ['setup_token' => $token]);
check($r['status'] === 200 && is_file($tmp . '/config.php'), 'installer writes config with the correct token');
check(!is_file($tokenFile), 'setup token deleted after installation');
check((fileperms($tmp . '/config.php') & 0007) === 0, 'config file not world-readable');

$r = $c->get('/');
check($r['status'] === 302 && str_contains(hdr($r, 'location'), 'admin.login'), 'installer locked after setup');
$r = $c->submit('/', '/index.php', $install + ['setup_token' => $token, 'admin_pw' => 'attacker pw!', 'admin_pw2' => 'attacker pw!']);
check(password_verify('super secret pw', (require $tmp . '/config.php')['admin_password_hash']), 'config cannot be overwritten afterwards');

/* -------------------------------------------------------------------- headers */
$r = $c->get('/index.php?r=admin.login', ['X-Forwarded-Proto: https']);
check(hdr($r, 'strict-transport-security') === '', 'X-Forwarded-Proto from an untrusted peer does not enable HSTS');
check(isset($c->cookies['w2b_sess']) && !isset($c->cookies['fmn_sess']), 'session cookie renamed to w2b_sess');
check(!preg_match('/<script(?![^>]*src=)/', $r['body']) && str_contains($r['body'], 'assets/app.js'), 'pages load JS only from app.js');
$r = $c->get('/index.php?r=does-not-exist');
check($r['status'] === 404 && str_contains($r['body'], '<title>Not found'), 'unknown route: 404 with translated title');

/* -------------------------------------------------------------- login throttle */
for ($i = 0; $i < 5; $i++) {
    $c->submit('/index.php?r=admin.login', '/index.php?r=admin.login', ['password' => 'nope']);
}
$fresh = new Client($base);                    // new session, no cookies
$r = $fresh->follow($fresh->submit('/index.php?r=admin.login', '/index.php?r=admin.login', ['password' => 'super secret pw']));
check(str_contains($r['body'], 'Too many failed attempts'), 'login lockout survives a fresh session');
$r = $fresh->get('/index.php?r=admin');
check($r['status'] === 302, 'correct password ignored while locked');
db_open($tmp)->exec("DELETE FROM rate_events WHERE bucket = 'login_fail'");

$admin = new Client($base);
$r = $admin->submit('/index.php?r=admin.login', '/index.php?r=admin.login', ['password' => 'super secret pw']);
check($r['status'] === 302 && str_contains(hdr($r, 'location'), 'r=admin'), 'admin login works');
$r = $admin->get('/index.php?r=admin');
check($r['status'] === 200 && str_contains($r['body'], 'nav'), 'dashboard reachable');

/* ----------------------------------------------------------------- create poll */
$r = $admin->submit('/index.php?r=admin.poll_new', '/index.php?r=admin.poll_create', [
    'title' => 'Summer party', 'description' => 'Bring stuff', 'event_date' => '2026-07-01',
    'visibility' => 'who_and_what', 'email_required' => '1', 'item_label' => ['Cake', 'Salad', ''],
]);
check($r['status'] === 302, 'poll created');
$pdo = db_open($tmp);
$poll = $pdo->query('SELECT * FROM polls')->fetch();
$items = $pdo->query('SELECT id FROM items ORDER BY sort_order')->fetchAll(PDO::FETCH_COLUMN);
check($poll && count($items) === 2, 'poll stored with 2 items');
$r = $admin->submit('/index.php?r=admin.poll_new', '/index.php?r=admin.poll_create', [
    'title' => str_repeat('x', 201), 'item_label' => ['A'],
]);
check((int) $pdo->query('SELECT COUNT(*) FROM polls')->fetchColumn() === 1, 'over-long poll title rejected');
$r = $admin->submit('/index.php?r=admin.poll_new', '/index.php?r=admin.poll_create', ['title' => 'T', 'item_label' => 'scalar']);
check($r['status'] === 302, 'scalar item_label[] does not cause a 500');

/* --------------------------------------------------------------- participants */
$pollPath = '/index.php?r=poll&token=' . $poll['token'];
$submitPath = '/index.php?r=poll_submit&token=' . $poll['token'];
$anna = new Client($base);
$r = $anna->submit($pollPath, $submitPath, [
    'name' => 'Anna', 'email' => 'anna@example.org', 'items' => [$items[0] => '1'], 'detail' => [$items[0] => '2 trays'],
]);
$cookieName = 'w2b_edit_' . $poll['id'];
check(isset($anna->cookies[$cookieName]), 'edit-token cookie set after submitting');
$thanks = $anna->follow($r);
check(preg_match('/value="([^"]*edit=[^"]+)"/', $thanks['body'], $m) === 1, 'thanks page shows the personal edit link');
$editLink = html_entity_decode($m[1] ?? '');

$mallory = new Client($base);
$r = $mallory->follow($mallory->submit($pollPath, $submitPath, [
    'name' => 'Mallory', 'email' => 'ANNA@example.org', 'items' => [$items[1] => '1'],
]));
check(str_contains($r['body'], 'already an entry with this email'), 'same e-mail without edit token rejected with a clear message');
$row = $pdo->query('SELECT c.name, COUNT(ci.id) n FROM contributions c JOIN contribution_items ci ON ci.contribution_id = c.id')->fetch();
check($row['name'] === 'Anna' && (int) $row['n'] === 1, 'victim entry unchanged');
check(!str_contains($r['body'], 'anna@example.org'), 'e-mail not leaked to another visitor');

$r = $anna->get($pollPath);
check(str_contains($r['body'], 'value="anna@example.org"') && str_contains($r['body'], '2 trays'), 'own entry pre-filled for the token holder');
$r = $anna->follow($anna->submit($pollPath, $submitPath, [
    'name' => 'Anna B.', 'email' => 'anna@example.org', 'items' => [$items[1] => '1'],
]));
$row = $pdo->query('SELECT name FROM contributions')->fetchAll(PDO::FETCH_COLUMN);
check($row === ['Anna B.'], 'token holder can update the entry');

$other = new Client($base);
$r = $other->get((string) preg_replace('#^https?://[^/]+#', '', $editLink));
check($r['status'] === 302 && isset($other->cookies[$cookieName]), 'personal link works on another device (sets cookie, strips token from URL)');
check(!str_contains(hdr($r, 'location'), 'edit='), 'edit token removed from the URL after opening the personal link');

$r = $mallory->submit($pollPath, $submitPath, ['name' => 'X', 'email' => 'x@example.org', 'items' => 'oops']);
check($r['status'] === 302, 'scalar items[] does not cause a 500');
$r = $mallory->follow($mallory->submit($pollPath, $submitPath, [
    'name' => str_repeat('n', 101), 'email' => 'long@example.org', 'items' => [$items[0] => '1'],
]));
check(str_contains($r['body'], 'too long') && (int) $pdo->query('SELECT COUNT(*) FROM contributions')->fetchColumn() === 1, 'over-long name rejected');
check(str_contains($r['body'], 'maxlength="100"'), 'name field has maxlength');

$r = $anna->follow($anna->submit($pollPath, $submitPath, ['withdraw' => '1']));
check((int) $pdo->query('SELECT COUNT(*) FROM contributions')->fetchColumn() === 0 && !isset($anna->cookies[$cookieName]), 'participant can withdraw the own entry');

for ($i = 0; $i < 14; $i++) {
    $last = $mallory->submit($pollPath, $submitPath, ['name' => "Bot $i", 'email' => "bot$i@example.org", 'items' => [$items[0] => '1']]);
}
$n = (int) $pdo->query('SELECT COUNT(*) FROM contributions')->fetchColumn();
check($n < 12 && str_contains($mallory->follow($last)['body'], 'Too many entries'), "per-IP submission rate limit ($n stored)");

/* --------------------------------------------------------------- admin entries */
$cid = (int) $pdo->query('SELECT id FROM contributions LIMIT 1')->fetchColumn();
$admin->submit('/index.php?r=admin.poll_view&id=' . $poll['id'], '/index.php?r=admin.contribution_delete&id=' . $poll['id'], ['contribution_id' => $cid]);
check((int) $pdo->query('SELECT COUNT(*) FROM contributions WHERE id = ' . $cid)->fetchColumn() === 0, 'admin can delete a single entry');
$r = $admin->req('POST', '/index.php?r=admin.contribution_delete&id=' . $poll['id'], ['contribution_id' => 1, '_csrf' => 'x']);
check($r['status'] === 400, 'CSRF still enforced');

$err = (string) @file_get_contents($tmp . '/server.err');
check(!preg_match('/PHP (Fatal|Warning|Notice|Deprecated)/', $err), 'no PHP errors/warnings in the server log');
if (preg_match_all('/PHP (Fatal|Warning|Notice|Deprecated).*/', $err, $mm)) {
    echo implode(PHP_EOL, array_slice(array_unique($mm[0]), 0, 5)), PHP_EOL;
}

finish();
