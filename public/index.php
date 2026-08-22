<?php
declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';

$r = (string) ($_GET['r'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isPost = ($method === 'POST');

/* --------------------------- helpers for handlers -------------------------- */

function post(string $key, $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function parse_items_from_post(): array
{
    // Parallel arrays item_id[] and item_label[]; item_id may be empty for new rows.
    $labels = $_POST['item_label'] ?? [];
    $ids = $_POST['item_id'] ?? [];
    $items = [];
    foreach ($labels as $i => $label) {
        $items[] = ['id' => isset($ids[$i]) ? (int) $ids[$i] : null, 'label' => (string) $label];
    }
    return $items;
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
            $locked = login_locked_seconds();
            if ($locked > 0) {
                flash("Zu viele Fehlversuche. Bitte $locked Sekunden warten.", 'error');
                redirect_route('admin.login');
            }
            if (admin_login(post('password'))) {
                redirect_route('admin');
            }
            flash('Falsches Passwort.', 'error');
            redirect_route('admin.login');
        }
        view('admin/login', [], 'Anmeldung');
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
        view('admin/dashboard', ['polls' => admin_list_polls()], 'Übersicht');
        break;

    /* ---- admin: create ---- */
    case 'admin.poll_new':
        require_admin();
        view('admin/poll_form', ['poll' => null, 'items' => []], 'Neue Abfrage');
        break;

    case 'admin.poll_create':
        require_admin();
        csrf_check();
        $title = post('title');
        if ($title === '') {
            flash('Bitte eine Überschrift angeben.', 'error');
            set_old($_POST);
            redirect_route('admin.poll_new');
        }
        $labels = array_values(array_filter(array_map('trim', $_POST['item_label'] ?? []), fn($l) => $l !== ''));
        if (!$labels) {
            flash('Bitte mindestens ein benötigtes Ding angeben.', 'error');
            set_old($_POST);
            redirect_route('admin.poll_new');
        }
        $res = admin_create_poll(
            $title,
            post('description'),
            post('event_date'),
            valid_visibility(post('visibility', 'names_only')),
            $labels
        );
        clear_old();
        flash('Abfrage angelegt. Der Teilnahme-Link steht unten bereit.', 'success');
        redirect_route('admin.poll_view', ['id' => $res['id']]);

    /* ---- admin: view one poll ---- */
    case 'admin.poll_view':
        require_admin();
        $poll = admin_get_poll((int) ($_GET['id'] ?? 0));
        if (!$poll) {
            http_response_code(404);
            flash('Abfrage nicht gefunden.', 'error');
            redirect_route('admin');
        }
        view('admin/poll_view', [
            'poll'          => $poll,
            'items'         => admin_get_items((int) $poll['id']),
            'contributions' => admin_contributions((int) $poll['id']),
        ], $poll['title']);
        break;

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
        ], 'Abfrage bearbeiten');
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
        $title = post('title');
        if ($title === '') {
            flash('Bitte eine Überschrift angeben.', 'error');
            redirect_route('admin.poll_edit', ['id' => $id]);
        }
        admin_update_poll(
            $id,
            $title,
            post('description'),
            post('event_date'),
            valid_visibility(post('visibility', 'names_only')),
            parse_items_from_post()
        );
        flash('Änderungen gespeichert.', 'success');
        redirect_route('admin.poll_view', ['id' => $id]);

    case 'admin.poll_delete':
        require_admin();
        csrf_check();
        admin_delete_poll((int) ($_GET['id'] ?? 0));
        flash('Abfrage gelöscht.', 'success');
        redirect_route('admin');

    case 'admin.poll_regen':
        require_admin();
        csrf_check();
        $id = (int) ($_GET['id'] ?? 0);
        admin_regenerate_token($id);
        flash('Neuer Link erzeugt — der alte Link funktioniert nicht mehr.', 'success');
        redirect_route('admin.poll_view', ['id' => $id]);

    /* ---- admin: reminder ---- */
    case 'admin.reminder':
        require_admin();
        $poll = admin_get_poll((int) ($_GET['id'] ?? 0));
        if (!$poll) {
            http_response_code(404);
            redirect_route('admin');
        }
        $defaultBody = (string) file_get_contents(__DIR__ . '/../templates/mail/reminder_default.txt');
        view('admin/reminder', [
            'poll'        => $poll,
            'recipients'  => admin_reminder_recipients((int) $poll['id']),
            'defaultSubject' => 'Erinnerung: ' . $poll['title'],
            'defaultBody' => $defaultBody,
        ], 'Erinnerung senden');
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
        $bodyTpl = (string) ($_POST['body'] ?? '');
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
            flash("$sent Erinnerung(en) versendet.", 'success');
        }
        if ($failed > 0) {
            flash("$failed Versand(e) fehlgeschlagen: " . implode(' | ', $errors), 'error');
        }
        if (!$recipients) {
            flash('Es gibt noch keine Teilnehmenden, die erinnert werden könnten.', 'info');
        }
        redirect_route('admin.reminder', ['id' => $id]);

    /* ---- public: poll form ---- */
    case 'poll':
        $poll = public_get_poll_by_token((string) ($_GET['token'] ?? ''));
        if (!$poll) {
            http_response_code(404);
            view('public/notfound', [], 'Nicht gefunden', true);
            break;
        }
        view('public/poll', [
            'poll'    => $poll,
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
            view('public/notfound', [], 'Nicht gefunden', true);
            break;
        }
        csrf_check();
        $name = post('name');
        $email = post('email');
        $checked = $_POST['items'] ?? [];
        $details = $_POST['detail'] ?? [];
        $selections = [];
        foreach ($checked as $itemId => $_v) {
            $selections[(int) $itemId] = (string) ($details[$itemId] ?? '');
        }

        $problems = [];
        if ($name === '') {
            $problems[] = 'Bitte den Namen angeben.';
        }
        if (!is_valid_email($email)) {
            $problems[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        if (!$selections) {
            $problems[] = 'Bitte mindestens ein Ding auswählen, das du mitbringst.';
        }
        if ($problems) {
            foreach ($problems as $p) {
                flash($p, 'error');
            }
            set_old(['name' => $name, 'email' => $email]);
            redirect(poll_link($token));
        }

        public_submit((int) $poll['id'], $name, $email, $selections);
        clear_old();
        $_SESSION['thanks_name'] = $name;
        redirect(base_url() . '/index.php?' . http_build_query(['r' => 'thanks', 'token' => $token]));

    case 'thanks':
        $poll = public_get_poll_by_token((string) ($_GET['token'] ?? ''));
        if (!$poll) {
            http_response_code(404);
            view('public/notfound', [], 'Nicht gefunden', true);
            break;
        }
        $name = (string) ($_SESSION['thanks_name'] ?? '');
        unset($_SESSION['thanks_name']);
        view('public/thanks', [
            'poll'  => $poll,
            'name'  => $name,
            'token' => $poll['token'],
        ], 'Danke', true);
        break;

    default:
        http_response_code(404);
        view('public/notfound', [], 'Nicht gefunden', true);
        break;
}
