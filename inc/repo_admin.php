<?php
declare(strict_types=1);

/**
 * Admin-side data access. These queries MAY read e-mail plaintext
 * (from contribution_contacts) because they are only reached behind require_admin().
 */

function admin_list_polls(): array
{
    $sql = 'SELECT p.*,
              (SELECT COUNT(*) FROM items i WHERE i.poll_id = p.id)          AS item_count,
              (SELECT COUNT(*) FROM contributions c WHERE c.poll_id = p.id)  AS contrib_count
            FROM polls p
            ORDER BY p.created_at DESC, p.id DESC';
    return db()->query($sql)->fetchAll();
}

function admin_get_poll(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM polls WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function admin_get_items(int $pollId): array
{
    $st = db()->prepare('SELECT * FROM items WHERE poll_id = ? ORDER BY sort_order, id');
    $st->execute([$pollId]);
    return $st->fetchAll();
}

/** @param string[] $labels  create a poll with its items; returns ['id','token']. */
function admin_create_poll(string $title, ?string $description, ?string $eventDate, string $visibility, array $labels): array
{
    $db = db();
    $token = admin_unique_token($db);
    $db->beginTransaction();
    try {
        $st = $db->prepare('INSERT INTO polls (token, title, description, event_date, visibility)
                            VALUES (?, ?, ?, ?, ?)');
        $st->execute([$token, $title, $description ?: null, $eventDate ?: null, $visibility]);
        $pollId = (int) $db->lastInsertId();
        admin_save_items($db, $pollId, array_map(fn($l) => ['id' => null, 'label' => $l], $labels));
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return ['id' => $pollId, 'token' => $token];
}

/** @param array $items list of ['id'=>int|null,'label'=>string] */
function admin_update_poll(int $id, string $title, ?string $description, ?string $eventDate, string $visibility, array $items): void
{
    $db = db();
    $db->beginTransaction();
    try {
        $st = $db->prepare('UPDATE polls
                            SET title = ?, description = ?, event_date = ?, visibility = ?, updated_at = datetime(\'now\')
                            WHERE id = ?');
        $st->execute([$title, $description ?: null, $eventDate ?: null, $visibility, $id]);
        admin_save_items($db, $id, $items);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function admin_save_items(PDO $db, int $pollId, array $items): void
{
    $st = $db->prepare('SELECT id FROM items WHERE poll_id = ?');
    $st->execute([$pollId]);
    $existingIds = array_map('intval', array_column($st->fetchAll(), 'id'));

    $keptIds = [];
    $sort = 0;
    foreach ($items as $it) {
        $label = trim((string) ($it['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $itemId = isset($it['id']) ? (int) $it['id'] : 0;
        if ($itemId > 0 && in_array($itemId, $existingIds, true)) {
            $u = $db->prepare('UPDATE items SET label = ?, sort_order = ? WHERE id = ? AND poll_id = ?');
            $u->execute([$label, $sort, $itemId, $pollId]);
            $keptIds[] = $itemId;
        } else {
            $i = $db->prepare('INSERT INTO items (poll_id, label, sort_order) VALUES (?, ?, ?)');
            $i->execute([$pollId, $label, $sort]);
            $keptIds[] = (int) $db->lastInsertId();
        }
        $sort++;
    }

    $toDelete = array_values(array_diff($existingIds, $keptIds));
    if ($toDelete) {
        $in = implode(',', array_fill(0, count($toDelete), '?'));
        $d = $db->prepare("DELETE FROM items WHERE poll_id = ? AND id IN ($in)");
        $d->execute(array_merge([$pollId], $toDelete));
    }
}

function admin_delete_poll(int $id): void
{
    $st = db()->prepare('DELETE FROM polls WHERE id = ?'); // cascades to items/contributions/...
    $st->execute([$id]);
}

function admin_regenerate_token(int $id): string
{
    $db = db();
    $token = admin_unique_token($db);
    $st = $db->prepare('UPDATE polls SET token = ?, updated_at = datetime(\'now\') WHERE id = ?');
    $st->execute([$token, $id]);
    return $token;
}

function admin_unique_token(PDO $db): string
{
    do {
        $token = token_new(16);
        $c = $db->prepare('SELECT 1 FROM polls WHERE token = ?');
        $c->execute([$token]);
    } while ($c->fetchColumn() !== false);
    return $token;
}

/**
 * Full contribution list for the admin poll view — INCLUDING e-mail.
 * Returns rows: name, email, updated_at/created_at, items => [['label','detail'], ...]
 */
function admin_contributions(int $pollId): array
{
    $st = db()->prepare(
        'SELECT c.id, c.name, cc.email, c.created_at, c.updated_at
         FROM contributions c
         JOIN contribution_contacts cc ON cc.contribution_id = c.id
         WHERE c.poll_id = ?
         ORDER BY c.name COLLATE NOCASE'
    );
    $st->execute([$pollId]);
    $rows = $st->fetchAll();

    $itemsStmt = db()->prepare(
        'SELECT i.label, ci.detail
         FROM contribution_items ci
         JOIN items i ON i.id = ci.item_id
         WHERE ci.contribution_id = ?
         ORDER BY i.sort_order, i.id'
    );
    foreach ($rows as &$r) {
        $itemsStmt->execute([$r['id']]);
        $r['items'] = $itemsStmt->fetchAll();
    }
    return $rows;
}

/**
 * Recipients + per-recipient item text for the reminder mailing.
 * Returns rows: name, email, items_text (e.g. "Kuchen (2 Bleche), Salat").
 */
function admin_reminder_recipients(int $pollId): array
{
    $rows = admin_contributions($pollId);
    $out = [];
    foreach ($rows as $r) {
        $parts = [];
        foreach ($r['items'] as $it) {
            $label = $it['label'];
            $detail = trim((string) ($it['detail'] ?? ''));
            $parts[] = $detail !== '' ? "$label ($detail)" : $label;
        }
        $out[] = [
            'name'       => $r['name'],
            'email'      => $r['email'],
            'items_text' => implode(', ', $parts),
        ];
    }
    return $out;
}
