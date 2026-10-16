<?php
declare(strict_types=1);

require __DIR__ . '/../inc/phpcheck.php';

// Liveness probe for container healthchecks: no session, no DB, no config needed.
if (($_GET['r'] ?? '') === 'health') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo "ok\n";
    exit;
}

require_once __DIR__ . '/../inc/config.php';

// First run: not configured yet → web installer (requires the setup token, self-locks once written).
if (!w2b_is_configured()) {
    require __DIR__ . '/../inc/install.php';
    exit;
}

require __DIR__ . '/../inc/bootstrap.php';

if (admin_session_check()) {
    flash(t('msg.session_expired'), 'info');
}

$r = (string) ($_GET['r'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isPost = ($method === 'POST');

/* --------------------------- helpers for handlers -------------------------- */

function post(string $key, string $default = ''): string
{
    $v = post_str($_POST, $key);
    return $v !== '' ? $v : $default;
}

function parse_items_from_post(): array
{
    // Parallel arrays item_id[] and item_label[]; item_id may be empty for new rows.
    $labels = post_arr($_POST, 'item_label');
    $ids = post_arr($_POST, 'item_id');
    $items = [];
    foreach ($labels as $i => $label) {
        $id = $ids[$i] ?? null;
        $items[] = ['id' => is_scalar($id) ? (int) $id : null, 'label' => is_scalar($label) ? (string) $label : ''];
    }
    return $items;
}

/** Name of the cookie holding a participant's edit token for one poll. */
function edit_cookie_name(int $pollId): string
{
    return 'w2b_edit_' . $pollId;
}

function set_edit_cookie(int $pollId, string $token): void
{
    setcookie(edit_cookie_name($pollId), $token, [
        'expires'  => time() + 180 * 86400,
        'path'     => '/',
        'secure'   => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_edit_cookie(int $pollId): void
{
    setcookie(edit_cookie_name($pollId), '', [
        'expires' => time() - 3600, 'path' => '/', 'secure' => request_is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

/** Edit token from the personal link (?edit=) or the per-poll cookie. */
function current_edit_token(int $pollId): string
{
    $t = $_GET['edit'] ?? ($_COOKIE[edit_cookie_name($pollId)] ?? '');
    return is_string($t) ? trim($t) : '';
}

function personal_edit_link(string $pollToken, string $editToken): string
{
    return base_url() . '/index.php?' . http_build_query(['r' => 'poll', 'token' => $pollToken, 'edit' => $editToken]);
}

function valid_visibility(string $v): string
{
    return in_array($v, ['who_and_what', 'names_only', 'none'], true) ? $v : 'names_only';
}

/* --------------------------------- routing --------------------------------- */

switch ($r) {

    /* ---- home ---- */
    case '':
    case 'home':
        redirect_route(is_admin() ? 'admin' : 'admin.login');

    /* ---- admin: login ---- */
    case 'admin.login':
        if (is_admin()) {
            redirect_route('admin');
        }
        if ($isPost) {
            csrf_check();
            $pw = $_POST['password'] ?? '';
            $res = admin_login(is_string($pw) ? $pw : '');
            if ($res === 'ok') {
                redirect_route('admin');
            }
            if ($res === 'locked') {
                flash(t('msg.too_many', ['s' => login_locked_seconds()]), 'error');
            } else {
                flash(t('msg.wrong_password'), 'error');
            }
            redirect_route('admin.login');
        }
        view('admin/login', [], t('title.login'));
        break;

    case 'admin.logout':
        if ($isPost) {
            csrf_check();
            admin_logout();
        }
        redirect_route('admin.login');

    /* ---- admin: dashboard ---- */
    case 'admin':
        require_admin();
        $purged = maybe_auto_purge();
        if ($purged > 0) {
            flash(t('msg.purged', ['n' => $purged]), 'info');
        }
        view('admin/dashboard', ['polls' => admin_list_polls()], t('title.overview'));
        break;

    /* ---- admin: create ---- */
    case 'admin.poll_new':
        require_admin();
        view('admin/poll_form', ['poll' => null, 'items' => []], t('title.new_poll'));
        break;

    case 'admin.poll_create':
        require_admin();
        csrf_check();
        $form = validate_poll_form($_POST);
        $labels = array_values(array_filter($form['labels'], fn($l) => $l !== ''));
        if (!$labels && !$form['problems']) {
            $form['problems'][] = t('msg.need_thing');
        }
        if ($form['problems']) {
            foreach ($form['problems'] as $p) {
                flash($p, 'error');
            }
            set_old([
                'title' => $form['title'], 'description' => $form['description'], 'event_date' => $form['event_date'],
                'visibility' => post('visibility'), 'email_required' => post('email_required', '1'), 'item_label' => $labels,
            ]);
            redirect_route('admin.poll_new');
        }
        $res = admin_create_poll(
            $form['title'],
            $form['description'],
            $form['event_date'],
            valid_visibility(post('visibility', 'names_only')),
            (int) ($_POST['email_required'] ?? 1) ? 1 : 0,
            $labels
        );
        clear_old();
        flash(t('msg.poll_created'), 'success');
        redirect_route('admin.poll_view', ['id' => $res['id']]);

    /* ---- admin: view one poll ---- */
    case 'admin.poll_view':
        require_admin();
        $poll = admin_get_poll((int) ($_GET['id'] ?? 0));
        if (!$poll) {
            http_response_code(404);
            flash(t('msg.poll_not_found'), 'error');
            redirect_route('admin');
        }
        view('admin/poll_view', [
            'poll'          => $poll,
            'items'         => admin_get_items((int) $poll['id']),
            'contributions' => admin_contributions((int) $poll['id']),
        ], $poll['title']);
        break;

    /* ---- admin: CSV-Export der Eintraege ---- */
    case 'admin.poll_export':
        require_admin();
        $poll = admin_get_poll((int) ($_GET['id'] ?? 0));
        if (!$poll) {
            http_response_code(404);
            redirect_route('admin');
        }
        $rows = admin_contributions((int) $poll['id']);
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $poll['title']);
        $slug = trim((string) $slug, '-') ?: ('abfrage-' . $poll['id']);
        $filename = 'what2bring_' . $slug . '_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows umlauts correctly
        fputcsv($out, [t('view.col_name'), t('view.col_email'), t('view.col_brings'), t('view.col_updated')], ';');
        foreach ($rows as $c) {
            $parts = [];
            foreach ($c['items'] as $it) {
                $d = trim((string) ($it['detail'] ?? ''));
                $parts[] = $d !== '' ? $it['label'] . ' (' . $d . ')' : $it['label'];
            }
            fputcsv($out, array_map('csv_safe', [
                (string) $c['name'],
                (string) ($c['email'] ?? ''),
                implode('; ', $parts),
                (string) ($c['updated_at'] ?: $c['created_at']),
            ]), ';');
        }
        fclose($out);
        exit;

    /* ---- admin: edit ---- */
    case 'admin.poll_edit':
        require_admin();
        $poll = admin_get_poll((int) ($_GET['id'] ?? 0));
        if (!$poll) {
            http_response_code(404);
            redirect_route('admin');
        }
        view('admin/poll_form', [
            'poll'  => $poll,
            'items' => admin_get_items((int) $poll['id']),
        ], t('title.edit_poll'));
        break;

    case 'admin.poll_update':
        require_admin();
        csrf_check();
        $id = (int) ($_GET['id'] ?? 0);
        $poll = admin_get_poll($id);
        if (!$poll) {
            http_response_code(404);
            redirect_route('admin');
        }
        $form = validate_poll_form($_POST);
        if ($form['problems']) {
            foreach ($form['problems'] as $p) {
                flash($p, 'error');
            }
            redirect_route('admin.poll_edit', ['id' => $id]);
        }
        admin_update_poll(
            $id,
            $form['title'],
            $form['description'],
            $form['event_date'],
            valid_visibility(post('visibility', 'names_only')),
            (int) ($_POST['email_required'] ?? 1) ? 1 : 0,
            parse_items_from_post()
        );
        flash(t('msg.saved'), 'success');
        redirect_route('admin.poll_view', ['id' => $id]);

    case 'admin.poll_delete':
        require_admin();
        csrf_check();
        admin_delete_poll((int) ($_GET['id'] ?? 0));
        flash(t('msg.poll_deleted'), 'success');
        redirect_route('admin');

    case 'admin.contribution_delete':
        require_admin();
        csrf_check();
        $id = (int) ($_GET['id'] ?? 0);
        if (admin_delete_contribution($id, (int) ($_POST['contribution_id'] ?? 0))) {
            flash(t('msg.entry_deleted'), 'success');
        }
        redirect_route('admin.poll_view', ['id' => $id]);

    case 'admin.poll_regen':
        require_admin();
        csrf_check();
        $id = (int) ($_GET['id'] ?? 0);
        admin_regenerate_token($id);
        flash(t('msg.link_regenerated'), 'success');
        redirect_route('admin.poll_view', ['id' => $id]);

    /* ---- admin: reminder ---- */
    case 'admin.reminder':
        require_admin();
        $poll = admin_get_poll((int) ($_GET['id'] ?? 0));
        if (!$poll) {
            http_response_code(404);
            redirect_route('admin');
        }
        $rbFile = __DIR__ . '/../templates/mail/reminder_default_' . current_lang() . '.txt';
        if (!is_file($rbFile)) {
            $rbFile = __DIR__ . '/../templates/mail/reminder_default.txt';
        }
        $defaultBody = (string) file_get_contents($rbFile);
        view('admin/reminder', [
            'poll'        => $poll,
            'recipients'  => admin_reminder_recipients((int) $poll['id']),
            'defaultSubject' => t('rem.default_subject', ['title' => $poll['title']]),
            'defaultBody' => $defaultBody,
        ], t('title.reminder'));
        break;

    case 'admin.reminder_send':
        require_admin();
        csrf_check();
        $id = (int) ($_GET['id'] ?? 0);
        $poll = admin_get_poll($id);
        if (!$poll) {
            http_response_code(404);
            redirect_route('admin');
        }
        $subjectTpl = post('subject');
        $bodyTpl = is_string($_POST['body'] ?? null) ? $_POST['body'] : '';
        if (str_too_long($subjectTpl, LIMIT_SUBJECT) || str_too_long($bodyTpl, LIMIT_MAIL_BODY)) {
            flash(t('msg.too_long', ['field' => t('rem.message'), 'max' => LIMIT_MAIL_BODY]), 'error');
            redirect_route('admin.reminder', ['id' => $id]);
        }
        $recipients = admin_reminder_recipients($id);
        $sent = 0;
        $failed = 0;
        $errors = [];
        foreach ($recipients as $rcpt) {
            $repl = [
                '{name}'              => $rcpt['name'],
                '{ueberschrift}'      => $poll['title'],
                '{datum}'             => fmt_date($poll['event_date']),
                '{was_ich_mitbringe}' => $rcpt['items_text'],
            ];
            $subject = strtr($subjectTpl, $repl);
            $body = strtr($bodyTpl, $repl);
            try {
                send_mail($rcpt['email'], $rcpt['name'], $subject, $body);
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                if (count($errors) < 3) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        if ($sent > 0) {
            flash(t('msg.reminders_sent', ['n' => $sent]), 'success');
        }
        if ($failed > 0) {
            flash(t('msg.reminders_failed', ['n' => $failed, 'errors' => implode(' | ', $errors)]), 'error');
        }
        if (!$recipients) {
            flash(t('msg.no_recipients'), 'info');
        }
        redirect_route('admin.reminder', ['id' => $id]);

    /* ---- public: poll form ---- */
    case 'poll':
        $poll = public_get_poll_by_token((string) ($_GET['token'] ?? ''));
        if (!$poll) {
            http_response_code(404);
            view('public/notfound', [], t('title.notfound'), true);
            break;
        }
        $own = participant_own_entry((int) $poll['id'], current_edit_token((int) $poll['id']));
        if ($own !== null && isset($_GET['edit']) && is_string($_GET['edit'])) {
            // Personal link opened (e.g. on another device): remember it, then drop the token from the URL.
            set_edit_cookie((int) $poll['id'], $_GET['edit']);
            redirect(poll_link($poll['token']));
        }
        header('Cache-Control: no-store');
        view('public/poll', [
            'poll'    => $poll,
            'own'     => $own,
            'items'   => public_get_items((int) $poll['id']),
            'summary' => $poll['visibility'] === 'who_and_what' ? public_summary_by_item((int) $poll['id']) : [],
            'names'   => $poll['visibility'] === 'names_only' ? public_contributor_names((int) $poll['id']) : [],
        ], $poll['title'], true);
        break;

    case 'poll_submit':
        $token = (string) ($_GET['token'] ?? '');
        $poll = public_get_poll_by_token($token);
        if (!$poll) {
            http_response_code(404);
            view('public/notfound', [], t('title.notfound'), true);
            break;
        }
        csrf_check();
        $pollId = (int) $poll['id'];
        $editToken = current_edit_token($pollId);

        // Per-IP rate limit for public submissions (persisted, IP stored only as HMAC).
        $limit = (int) cfg('submit_rate_limit', 20);
        $rlKey = client_ip() . '|' . $pollId;
        if ($limit > 0 && rl_count('submit', $rlKey, SUBMIT_WINDOW) >= $limit) {
            flash(t('msg.rate_limited'), 'error');
            redirect(poll_link($token));
        }
        rl_hit('submit', $rlKey);

        if (post('withdraw') === '1') {
            if (public_withdraw($pollId, $editToken)) {
                clear_edit_cookie($pollId);
                clear_old();
                flash(t('msg.withdrawn'), 'success');
            }
            redirect(poll_link($token));
        }

        $itemIds = array_map('intval', array_column(public_get_items($pollId), 'id'));
        $in = validate_submission($_POST, $poll, $itemIds);
        if ($in['problems']) {
            foreach ($in['problems'] as $p) {
                flash($p, 'error');
            }
            set_old(['name' => mb_substr($in['name'], 0, LIMIT_NAME), 'email' => mb_substr($in['email'], 0, LIMIT_EMAIL)]);
            redirect(poll_link($token));
        }

        $res = public_submit($pollId, $in['name'], $in['email'], $in['selections'], $editToken !== '' ? $editToken : null);
        if ($res['status'] === 'invalid_token') {
            // Stale cookie/link (entry deleted): forget it and let the person submit anew.
            clear_edit_cookie($pollId);
            $res = public_submit($pollId, $in['name'], $in['email'], $in['selections'], null);
        }
        if ($res['status'] === 'email_taken') {
            flash(t('msg.email_taken'), 'error');
            set_old(['name' => $in['name'], 'email' => $in['email']]);
            redirect(poll_link($token));
        }

        set_edit_cookie($pollId, $res['token']);
        $editLink = personal_edit_link($token, $res['token']);
        if ($res['status'] === 'created' && $in['email'] !== '' && cfg('confirmation_mail', false)) {
            try {
                send_mail($in['email'], $in['name'], t('confirm.subject', ['title' => $poll['title']]),
                    t('confirm.body', ['name' => $in['name'], 'title' => $poll['title'], 'link' => $editLink]));
            } catch (Throwable $e) {
                error_log('What2Bring: confirmation mail failed: ' . $e->getMessage());
            }
        }
        clear_old();
        $_SESSION['thanks'] = ['name' => $in['name'], 'link' => $editLink, 'updated' => $res['status'] === 'updated'];
        redirect(base_url() . '/index.php?' . http_build_query(['r' => 'thanks', 'token' => $token]));

    case 'thanks':
        $poll = public_get_poll_by_token((string) ($_GET['token'] ?? ''));
        if (!$poll) {
            http_response_code(404);
            view('public/notfound', [], t('title.notfound'), true);
            break;
        }
        $thanks = $_SESSION['thanks'] ?? ['name' => '', 'link' => '', 'updated' => false];
        unset($_SESSION['thanks']);
        header('Cache-Control: no-store');
        view('public/thanks', [
            'poll'    => $poll,
            'name'    => (string) $thanks['name'],
            'link'    => (string) $thanks['link'],
            'updated' => (bool) $thanks['updated'],
            'token'   => $poll['token'],
        ], t('title.thanks'), true);
        break;

    /* ---- public: Impressum / Datenschutz (frei erreichbar, kein Login/Token) ---- */
    case 'impressum':
        view('public/legal', ['heading' => t('nav.impressum'), 'text' => get_setting('impressum')], t('title.impressum'), true);
        break;

    case 'datenschutz':
        view('public/legal', ['heading' => t('legal.datenschutz'), 'text' => get_setting('datenschutz')], t('title.datenschutz'), true);
        break;

    /* ---- admin: Rechtliches (Impressum + Datenschutz pflegen) ---- */
    case 'admin.legal':
        require_admin();
        $imp = get_setting('impressum');
        $dat = get_setting('datenschutz');
        view('admin/legal', [
            'impressum'   => $imp !== '' ? $imp : legal_default('impressum'),
            'datenschutz' => $dat !== '' ? $dat : legal_default('datenschutz'),
        ], t('title.legal'));
        break;

    case 'admin.legal_save':
        require_admin();
        csrf_check();
        $imp = post('impressum');
        $dat = post('datenschutz');
        if (str_too_long($imp, LIMIT_LEGAL_TEXT) || str_too_long($dat, LIMIT_LEGAL_TEXT)) {
            flash(t('msg.too_long', ['field' => t('legal.title'), 'max' => LIMIT_LEGAL_TEXT]), 'error');
            redirect_route('admin.legal');
        }
        set_setting('impressum', $imp);
        set_setting('datenschutz', $dat);
        flash(t('msg.legal_saved'), 'success');
        redirect_route('admin.legal');

    default:
        http_response_code(404);
        view('public/notfound', [], t('title.notfound'), true);
        break;
}
