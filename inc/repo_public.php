<?php
declare(strict_types=1);

/**
 * Public (user-facing) data access. By construction these functions NEVER touch
 * contribution_contacts, so an e-mail address can never leak onto a public page.
 */

function public_get_poll_by_token(string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $st = db()->prepare('SELECT id, token, title, description, event_date, visibility, email_required FROM polls WHERE token = ?');
    $st->execute([$token]);
    $row = $st->fetch();
    return $row ?: null;
}

function public_get_items(int $pollId): array
{
    $st = db()->prepare('SELECT id, label FROM items WHERE poll_id = ? ORDER BY sort_order, id');
    $st->execute([$pollId]);
    return $st->fetchAll();
}

/** Just the participant names (for visibility = names_only). */
function public_contributor_names(int $pollId): array
{
    $st = db()->prepare('SELECT name FROM contributions WHERE poll_id = ? ORDER BY name COLLATE NOCASE');
    $st->execute([$pollId]);
    return array_column($st->fetchAll(), 'name');
}

/**
 * Per-item breakdown of who brings what (for visibility = who_and_what).
 * Returns items => [['label', 'entries' => [['name','detail'], ...]], ...]. Never e-mail.
 */
function public_summary_by_item(int $pollId): array
{
    $items = public_get_items($pollId);
    $st = db()->prepare(
        'SELECT c.name, ci.detail
         FROM contribution_items ci
         JOIN contributions c ON c.id = ci.contribution_id
         WHERE ci.item_id = ?
         ORDER BY c.name COLLATE NOCASE'
    );
    foreach ($items as &$it) {
        $st->execute([$it['id']]);
        $it['entries'] = $st->fetchAll();
    }
    unset($it);
    return $items;
}

/**
 * Upsert one participant's contribution, keyed by (poll, e-mail).
 * $selections is [item_id => detail-string]. E-mail plaintext is written
 * only to contribution_contacts.
 */
function public_submit(int $pollId, string $name, string $email, array $selections): void
{
    $db = db();
    $name = trim($name);
    $email = trim($email);
    $hasEmail = $email !== '';

    $db->beginTransaction();
    try {
        if ($hasEmail) {
            // Dedup/upsert per (poll, e-mail); e-mail plaintext only in contribution_contacts.
            $emailHash = email_hash($email);
            $find = $db->prepare('SELECT id FROM contributions WHERE poll_id = ? AND email_hash = ?');
            $find->execute([$pollId, $emailHash]);
            $cid = $find->fetchColumn();

            if ($cid === false) {
                $db->prepare('INSERT INTO contributions (poll_id, name, email_hash) VALUES (?, ?, ?)')
                   ->execute([$pollId, $name, $emailHash]);
                $cid = (int) $db->lastInsertId();
                $db->prepare('INSERT INTO contribution_contacts (contribution_id, email) VALUES (?, ?)')
                   ->execute([$cid, $email]);
            } else {
                $cid = (int) $cid;
                $db->prepare('UPDATE contributions SET name = ?, updated_at = datetime(\'now\') WHERE id = ?')
                   ->execute([$name, $cid]);
                $db->prepare('UPDATE contribution_contacts SET email = ? WHERE contribution_id = ?')
                   ->execute([$email, $cid]);
            }
        } else {
            // No e-mail (optional-email poll): each submission is a distinct entry.
            // A random email_hash satisfies UNIQUE(poll_id, email_hash); no contact row.
            $db->prepare('INSERT INTO contributions (poll_id, name, email_hash) VALUES (?, ?, ?)')
               ->execute([$pollId, $name, token_new(16)]);
            $cid = (int) $db->lastInsertId();
        }

        // Replace the selection wholesale.
        $db->prepare('DELETE FROM contribution_items WHERE contribution_id = ?')->execute([$cid]);

        $valid = $db->prepare('SELECT id FROM items WHERE poll_id = ?');
        $valid->execute([$pollId]);
        $validIds = array_map('intval', array_column($valid->fetchAll(), 'id'));

        $insItem = $db->prepare(
            'INSERT INTO contribution_items (contribution_id, item_id, detail) VALUES (?, ?, ?)'
        );
        foreach ($selections as $itemId => $detail) {
            $itemId = (int) $itemId;
            if (!in_array($itemId, $validIds, true)) {
                continue;
            }
            $detail = trim((string) $detail);
            $insItem->execute([$cid, $itemId, $detail !== '' ? $detail : null]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
