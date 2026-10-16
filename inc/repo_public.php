<?php
declare(strict_types=1);

/**
 * Public (user-facing) data access. By construction these functions NEVER read
 * contribution_contacts — with the single, token-gated exception of
 * participant_own_entry(), which returns a participant's own address to them.
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

/** Hash under which a participant's personal edit token is stored. */
function edit_token_hash(string $token): string
{
    return hash('sha256', $token);
}

/**
 * The participant's OWN entry, located via their secret edit token (link or cookie).
 * This is the only public-side function that reads contribution_contacts — and only
 * for the holder of the token, to pre-fill their own form. Returns null if the token
 * does not match an entry of this poll.
 * Shape: ['id','name','email','selections' => [item_id => detail]]
 */
function participant_own_entry(int $pollId, string $token): ?array
{
    if ($token === '' || strlen($token) > 128) {
        return null;
    }
    $st = db()->prepare(
        'SELECT c.id, c.name, cc.email
         FROM contributions c
         LEFT JOIN contribution_contacts cc ON cc.contribution_id = c.id
         WHERE c.poll_id = ? AND c.edit_token_hash = ?'
    );
    $st->execute([$pollId, edit_token_hash($token)]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    $it = db()->prepare('SELECT item_id, detail FROM contribution_items WHERE contribution_id = ?');
    $it->execute([$row['id']]);
    $row['selections'] = [];
    foreach ($it->fetchAll() as $r) {
        $row['selections'][(int) $r['item_id']] = (string) ($r['detail'] ?? '');
    }
    $row['email'] = (string) ($row['email'] ?? '');
    return $row;
}

/**
 * Create or update one participant's entry.
 *
 * - Without $editToken a NEW entry is created. If the e-mail address is already
 *   registered for this poll, nothing is changed and 'email_taken' is returned —
 *   entries can no longer be overwritten just by knowing someone's address.
 * - With a valid $editToken (personal link / cookie) that entry is updated.
 *
 * $selections is [item_id => detail]. E-mail plaintext is written only to
 * contribution_contacts. Returns ['status' => created|updated|email_taken|invalid_token,
 * 'token' => edit token (created/updated), 'id' => contribution id].
 */
function public_submit(int $pollId, string $name, string $email, array $selections, ?string $editToken = null): array
{
    $db = db();
    $name = trim($name);
    $email = trim($email);
    $hasEmail = $email !== '';
    $emailHash = $hasEmail ? email_hash($email) : null;

    $db->beginTransaction();
    try {
        $existing = null;
        if ($editToken !== null && $editToken !== '') {
            $find = $db->prepare('SELECT id, email_hash FROM contributions WHERE poll_id = ? AND edit_token_hash = ?');
            $find->execute([$pollId, edit_token_hash($editToken)]);
            $existing = $find->fetch() ?: null;
            if ($existing === null) {
                $db->rollBack();
                return ['status' => 'invalid_token'];
            }
        }

        if ($hasEmail) {
            $dup = $db->prepare('SELECT id FROM contributions WHERE poll_id = ? AND email_hash = ?');
            $dup->execute([$pollId, $emailHash]);
            $dupId = $dup->fetchColumn();
            if ($dupId !== false && ($existing === null || (int) $dupId !== (int) $existing['id'])) {
                $db->rollBack();
                return ['status' => 'email_taken'];
            }
        }

        if ($existing === null) {
            $token = token_new(32);
            // No e-mail (optional-email poll): a random email_hash satisfies UNIQUE(poll_id, email_hash).
            $db->prepare('INSERT INTO contributions (poll_id, name, email_hash, edit_token_hash) VALUES (?, ?, ?, ?)')
               ->execute([$pollId, $name, $emailHash ?? token_new(16), edit_token_hash($token)]);
            $cid = (int) $db->lastInsertId();
            if ($hasEmail) {
                $db->prepare('INSERT INTO contribution_contacts (contribution_id, email) VALUES (?, ?)')
                   ->execute([$cid, $email]);
            }
            $status = 'created';
        } else {
            $token = (string) $editToken;
            $cid = (int) $existing['id'];
            $db->prepare('UPDATE contributions SET name = ?, email_hash = ?, updated_at = datetime(\'now\') WHERE id = ?')
               ->execute([$name, $emailHash ?? token_new(16), $cid]);
            $db->prepare('DELETE FROM contribution_contacts WHERE contribution_id = ?')->execute([$cid]);
            if ($hasEmail) {
                $db->prepare('INSERT INTO contribution_contacts (contribution_id, email) VALUES (?, ?)')
                   ->execute([$cid, $email]);
            }
            $status = 'updated';
        }

        // Replace the selection wholesale (only items that belong to this poll).
        $db->prepare('DELETE FROM contribution_items WHERE contribution_id = ?')->execute([$cid]);
        $valid = $db->prepare('SELECT id FROM items WHERE poll_id = ?');
        $valid->execute([$pollId]);
        $validIds = array_flip(array_map('intval', array_column($valid->fetchAll(), 'id')));
        $insItem = $db->prepare('INSERT INTO contribution_items (contribution_id, item_id, detail) VALUES (?, ?, ?)');
        foreach ($selections as $itemId => $detail) {
            $itemId = (int) $itemId;
            if (!isset($validIds[$itemId])) {
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
    return ['status' => $status, 'token' => $token, 'id' => $cid];
}

/** Participant withdraws (deletes) their own entry via the edit token. */
function public_withdraw(int $pollId, string $editToken): bool
{
    if ($editToken === '') {
        return false;
    }
    $st = db()->prepare('DELETE FROM contributions WHERE poll_id = ? AND edit_token_hash = ?');
    $st->execute([$pollId, edit_token_hash($editToken)]);
    return $st->rowCount() > 0;
}
