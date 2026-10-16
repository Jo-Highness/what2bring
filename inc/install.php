<?php
declare(strict_types=1);

/**
 * Web installer — runs ONLY while the app is not configured (no config file and no
 * W2B_ADMIN_PASSWORD_HASH/W2B_APP_PEPPER in the environment); dispatched from
 * public/index.php. Requires the setup token (see inc/setup.php) so nobody else can
 * claim a fresh installation. Writes the config file to W2B_CONFIG (default:
 * config.php one level above the document root) and locks itself afterwards.
 */

require_once __DIR__ . '/app.php';

$https = request_is_https();
start_secure_session('w2b_install', $https, 'Strict');
send_security_headers($https);
header('Cache-Control: no-store');

$cfgFile = w2b_config_file();
$errors = [];
$done = false;
$localBypass = setup_local_bypass();
$expectedToken = $localBypass ? '' : setup_ensure_token();
$tokenUnavailable = !$localBypass && $expectedToken === null;
$givenToken = trim((string) ($_POST['setup_token'] ?? ($_GET['setup_token'] ?? '')));
$fwdHttps = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
$vals = [
    // Prefill only — the admin confirms/edits the base URL in the form.
    'base_url'   => (($https || $fwdHttps) ? 'https' : 'http') . '://'
                    . preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')),
    'smtp_host'  => '', 'smtp_port' => 587, 'smtp_secure' => 'starttls',
    'smtp_user'  => '', 'smtp_from' => '', 'smtp_from_name' => '', 'dry_run' => true,
    'trusted_proxies' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    if ($tokenUnavailable) {
        $errors[] = t('install.err_token_unavailable', ['path' => setup_token_file()]);
    } elseif (!$localBypass && !setup_token_valid($givenToken)) {
        http_response_code(403);
        $errors[] = t('install.err_token');
    }
    $pw  = (string) ($_POST['admin_pw'] ?? '');
    $pw2 = (string) ($_POST['admin_pw2'] ?? '');
    $vals['base_url']       = rtrim(post_str($_POST, 'base_url'), '/');
    $lang = post_str($_POST, 'default_lang');
    $lang = isset(available_langs()[$lang]) ? $lang : 'de';
    $vals['smtp_host']      = post_str($_POST, 'smtp_host');
    $vals['smtp_port']      = (int) post_str($_POST, 'smtp_port') ?: 587;
    $secure                 = post_str($_POST, 'smtp_secure');
    $vals['smtp_secure']    = in_array($secure, ['starttls', 'ssl', ''], true) ? $secure : 'starttls';
    $vals['smtp_user']      = post_str($_POST, 'smtp_user');
    $smtpPass               = is_string($_POST['smtp_pass'] ?? null) ? $_POST['smtp_pass'] : '';
    $vals['smtp_from']      = post_str($_POST, 'smtp_from');
    $vals['smtp_from_name'] = post_str($_POST, 'smtp_from_name');
    $vals['dry_run']        = isset($_POST['mail_dry_run']);
    $vals['trusted_proxies'] = post_str($_POST, 'trusted_proxies');
    $proxies = array_values(array_filter(array_map('trim', explode(',', $vals['trusted_proxies'])), 'strlen'));

    if (!$errors) {
        if (strlen($pw) < 10) {
            $errors[] = t('install.err_pw_short');
        }
        if ($pw !== $pw2) {
            $errors[] = t('install.err_pw_match');
        }
        if (!preg_match('#^https?://[^\s/]+(/[^\s]*)?$#i', $vals['base_url'])) {
            $errors[] = t('install.err_base_url');
        }
        foreach ($proxies as $p) {
            $ipPart = explode('/', $p, 2)[0];
            if (@inet_pton($ipPart) === false) {
                $errors[] = t('install.err_proxy', ['value' => $p]);
                break;
            }
        }
    }

    if (!$errors) {
        $c = setup_render_config([
            'hash'            => password_hash($pw, PASSWORD_DEFAULT),
            'pepper'          => bin2hex(random_bytes(32)),
            'base_url'        => $vals['base_url'],
            'lang'            => $lang,
            'dry_run'         => $vals['dry_run'],
            'trusted_proxies' => $proxies,
            'smtp'            => [
                'host' => $vals['smtp_host'], 'port' => $vals['smtp_port'], 'secure' => $vals['smtp_secure'],
                'user' => $vals['smtp_user'], 'pass' => $smtpPass, 'from_email' => $vals['smtp_from'],
                'from_name' => $vals['smtp_from_name'],
            ],
        ], $cfgFile);

        $old = umask(0027);
        $written = @file_put_contents($cfgFile, $c, LOCK_EX);
        umask($old);
        if ($written === false) {
            $errors[] = t('install.err_write', ['path' => $cfgFile]);
        } else {
            @chmod($cfgFile, 0640);
            setup_finish();
            $_SESSION = [];
            $done = true;
        }
    }
}

$secureOpts = ['starttls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', '' => t('install.secure_none')];
?>
<!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(t('title.install')) ?> · What2Bring</title>
    <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🧺</text></svg>') ?>">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="is-public">
<header class="topbar"><div class="wrap topbar__inner">
    <span class="brand"><span class="brand__mark" aria-hidden="true">🧺</span><span class="brand__name">What2Bring</span></span>
    <span class="lang-switch">
        <?php foreach (available_langs() as $code => $label): ?>
            <?php if ($code === current_lang()): ?><strong><?= e(strtoupper($code)) ?></strong><?php else: ?><a href="?lang=<?= e($code) ?>"><?= e(strtoupper($code)) ?></a><?php endif; ?>
        <?php endforeach; ?>
    </span>
</div></header>

<main class="wrap main">
<?php if ($done): ?>
    <div class="card card--pad-lg center stack">
        <div class="big-emoji" aria-hidden="true">🎉</div>
        <h1><?= e(t('install.done_title')) ?></h1>
        <p class="muted"><?= e(t('install.done_text')) ?></p>
        <p><a class="btn btn--lg" href="index.php?r=admin.login"><?= e(t('install.done_cta')) ?></a></p>
    </div>
<?php else: ?>
    <div style="max-width:640px;margin-inline:auto;">
        <p class="eyebrow"><?= e(t('install.eyebrow')) ?></p>
        <h1><?= e(t('install.title')) ?></h1>
        <p class="muted"><?= e(t('install.intro')) ?></p>
        <?php if ($tokenUnavailable): ?>
            <div class="flash flash--error"><?= e(t('install.err_token_unavailable', ['path' => setup_token_file()])) ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $er): ?>
            <div class="flash flash--error"><?= e($er) ?></div>
        <?php endforeach; ?>

        <form method="post" action="index.php" autocomplete="off">
            <?= csrf_field() ?>
            <div class="card stack">
                <?php if (!$localBypass): ?>
                <div class="field">
                    <label for="setup_token"><?= e(t('install.token')) ?> *</label>
                    <input type="text" id="setup_token" name="setup_token" value="<?= e($givenToken) ?>" autocomplete="off" spellcheck="false" maxlength="128" required>
                    <p class="hint"><?= e(t('install.token_hint', ['path' => setup_token_file()])) ?></p>
                </div>
                <?php endif; ?>
                <div class="field">
                    <label for="admin_pw"><?= e(t('install.admin_pw')) ?> *</label>
                    <input type="password" id="admin_pw" name="admin_pw" autocomplete="new-password" minlength="10" maxlength="1024" required>
                    <p class="hint"><?= e(t('install.admin_pw_hint')) ?></p>
                </div>
                <div class="field">
                    <label for="admin_pw2"><?= e(t('install.admin_pw2')) ?> *</label>
                    <input type="password" id="admin_pw2" name="admin_pw2" autocomplete="new-password" required>
                </div>
                <div class="field">
                    <label for="base_url"><?= e(t('install.base_url')) ?> *</label>
                    <input type="text" id="base_url" name="base_url" value="<?= e($vals['base_url']) ?>" maxlength="500" required>
                    <p class="hint"><?= e(t('install.base_url_hint')) ?></p>
                </div>
                <div class="field">
                    <label for="trusted_proxies"><?= e(t('install.proxies')) ?></label>
                    <input type="text" id="trusted_proxies" name="trusted_proxies" value="<?= e($vals['trusted_proxies']) ?>" placeholder="172.16.0.0/12" maxlength="500">
                    <p class="hint"><?= e(t('install.proxies_hint')) ?></p>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="default_lang"><?= e(t('install.lang')) ?></label>
                    <select id="default_lang" name="default_lang">
                        <?php foreach (available_langs() as $code => $label): ?>
                            <option value="<?= e($code) ?>" <?= $code === current_lang() ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="card stack">
                <h2><?= e(t('install.smtp_title')) ?></h2>
                <p class="hint" style="margin-top:-6px;"><?= e(t('install.smtp_hint')) ?></p>
                <div class="field">
                    <label for="smtp_host"><?= e(t('install.smtp_host')) ?></label>
                    <input type="text" id="smtp_host" name="smtp_host" value="<?= e((string) $vals['smtp_host']) ?>" placeholder="smtp.example.com">
                </div>
                <div class="btn-row" style="gap:14px;">
                    <div class="field" style="flex:1;min-width:120px;margin-bottom:0;">
                        <label for="smtp_port"><?= e(t('install.smtp_port')) ?></label>
                        <input type="number" id="smtp_port" name="smtp_port" value="<?= e((string) $vals['smtp_port']) ?>">
                    </div>
                    <div class="field" style="flex:2;min-width:160px;margin-bottom:0;">
                        <label for="smtp_secure"><?= e(t('install.smtp_secure')) ?></label>
                        <select id="smtp_secure" name="smtp_secure">
                            <?php foreach ($secureOpts as $val => $label): ?>
                                <option value="<?= e($val) ?>" <?= $vals['smtp_secure'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="smtp_user"><?= e(t('install.smtp_user')) ?></label>
                    <input type="text" id="smtp_user" name="smtp_user" value="<?= e((string) $vals['smtp_user']) ?>" autocomplete="off">
                </div>
                <div class="field">
                    <label for="smtp_pass"><?= e(t('install.smtp_pass')) ?></label>
                    <input type="password" id="smtp_pass" name="smtp_pass" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="smtp_from"><?= e(t('install.smtp_from')) ?></label>
                    <input type="email" id="smtp_from" name="smtp_from" value="<?= e((string) $vals['smtp_from']) ?>" placeholder="feste@example.com">
                </div>
                <div class="field">
                    <label for="smtp_from_name"><?= e(t('install.smtp_from_name')) ?></label>
                    <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?= e((string) $vals['smtp_from_name']) ?>">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;font-weight:500;">
                        <input type="checkbox" name="mail_dry_run" value="1" <?= $vals['dry_run'] ? 'checked' : '' ?> style="margin-top:3px;accent-color:var(--accent);width:20px;height:20px;flex:0 0 auto;">
                        <span><?= e(t('install.dry_run')) ?></span>
                    </label>
                </div>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn--lg"><?= e(t('install.submit')) ?></button>
            </div>
        </form>
    </div>
<?php endif; ?>
</main>

<footer class="footer"><div class="wrap"><p><?= e(t('footer.tagline')) ?> · <span class="muted"><?= e(t('footer.subtitle')) ?></span></p></div></footer>
</body>
</html>
