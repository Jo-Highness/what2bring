<?php
declare(strict_types=1);

/** Server-side input limits (mirrored as maxlength attributes in the templates). */
const LIMIT_NAME = 100;
const LIMIT_EMAIL = 254;
const LIMIT_DETAIL = 200;
const LIMIT_SELECTIONS = 100;
const LIMIT_TITLE = 200;
const LIMIT_DESCRIPTION = 5000;
const LIMIT_ITEM_LABEL = 200;
const LIMIT_ITEMS = 200;
const LIMIT_SUBJECT = 200;
const LIMIT_MAIL_BODY = 20000;
const LIMIT_LEGAL_TEXT = 50000;

/** Public submission rate limit window (seconds). */
const SUBMIT_WINDOW = 600;

function str_too_long(string $s, int $max): bool
{
    return mb_strlen($s, 'UTF-8') > $max;
}

/** A POST field that must be a scalar string ('' for arrays/missing). */
function post_str(array $src, string $key): string
{
    $v = $src[$key] ?? '';
    return is_scalar($v) ? trim((string) $v) : '';
}

/** A POST field that must be an array ([] for scalars/missing). */
function post_arr(array $src, string $key): array
{
    $v = $src[$key] ?? [];
    return is_array($v) ? $v : [];
}

/**
 * Validate a public poll submission.
 * Returns ['name', 'email', 'selections' => [item_id => detail], 'problems' => string[]].
 */
function validate_submission(array $post, array $poll, array $validItemIds): array
{
    $name = post_str($post, 'name');
    $email = post_str($post, 'email');
    $checked = post_arr($post, 'items');
    $details = post_arr($post, 'detail');
    $problems = [];

    $valid = array_flip(array_map('intval', $validItemIds));
    $selections = [];
    foreach ($checked as $itemId => $_v) {
        $itemId = (int) $itemId;
        if (!isset($valid[$itemId])) {
            continue;
        }
        $d = $details[$itemId] ?? '';
        $selections[$itemId] = is_scalar($d) ? trim((string) $d) : '';
    }

    if ($name === '') {
        $problems[] = t('msg.need_name');
    } elseif (str_too_long($name, LIMIT_NAME)) {
        $problems[] = t('msg.too_long', ['field' => t('poll.name'), 'max' => LIMIT_NAME]);
    }
    if (str_too_long($email, LIMIT_EMAIL)) {
        $problems[] = t('msg.too_long', ['field' => t('poll.email'), 'max' => LIMIT_EMAIL]);
    } elseif ((int) ($poll['email_required'] ?? 1) === 1) {
        if (!is_valid_email($email)) {
            $problems[] = t('msg.need_email');
        }
    } elseif ($email !== '' && !is_valid_email($email)) {
        $problems[] = t('msg.email_invalid');
    }
    if (!$selections) {
        $problems[] = t('msg.need_selection');
    } elseif (count($checked) > LIMIT_SELECTIONS) {
        $problems[] = t('msg.too_many_items', ['max' => LIMIT_SELECTIONS]);
    }
    foreach ($selections as $d) {
        if (str_too_long($d, LIMIT_DETAIL)) {
            $problems[] = t('msg.too_long', ['field' => t('poll.detail'), 'max' => LIMIT_DETAIL]);
            break;
        }
    }

    return ['name' => $name, 'email' => $email, 'selections' => $selections, 'problems' => $problems];
}

/**
 * Validate the admin poll form. Returns ['title','description','event_date','labels','problems'].
 * $labels are the raw item label strings (may contain empties, filtered by the caller).
 */
function validate_poll_form(array $post): array
{
    $title = post_str($post, 'title');
    $description = post_str($post, 'description');
    $date = post_str($post, 'event_date');
    $labels = array_map(static fn($l) => is_scalar($l) ? trim((string) $l) : '', post_arr($post, 'item_label'));
    $problems = [];

    if ($title === '') {
        $problems[] = t('msg.need_title');
    } elseif (str_too_long($title, LIMIT_TITLE)) {
        $problems[] = t('msg.too_long', ['field' => t('form.title_label'), 'max' => LIMIT_TITLE]);
    }
    if (str_too_long($description, LIMIT_DESCRIPTION)) {
        $problems[] = t('msg.too_long', ['field' => t('form.desc_label'), 'max' => LIMIT_DESCRIPTION]);
    }
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = '';
    }
    if (count(array_filter($labels, 'strlen')) > LIMIT_ITEMS) {
        $problems[] = t('msg.too_many_items', ['max' => LIMIT_ITEMS]);
    }
    foreach ($labels as $l) {
        if (str_too_long($l, LIMIT_ITEM_LABEL)) {
            $problems[] = t('msg.too_long', ['field' => t('form.thing_label'), 'max' => LIMIT_ITEM_LABEL]);
            break;
        }
    }
    return ['title' => $title, 'description' => $description, 'event_date' => $date, 'labels' => $labels, 'problems' => $problems];
}
