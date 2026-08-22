<?php
/** @var array $poll; @var array $recipients; @var string $defaultSubject; @var string $defaultBody */
?>
<div class="head-row" style="margin-bottom:16px;">
    <div>
        <p class="eyebrow">Erinnerung</p>
        <h1><?= e($poll['title']) ?></h1>
    </div>
    <a class="btn btn--ghost" href="<?= e(url('admin.poll_view', ['id' => $poll['id']])) ?>">Zurück</a>
</div>

<div class="card">
    <p style="margin-top:0;">Die Erinnerung geht <strong>einzeln</strong> an jede teilnehmende Person
        (<?= count($recipients) ?> Empfänger) — niemand sieht die Adressen der anderen.</p>
    <?php if ($recipients): ?>
        <div class="tag-people">
            <?php foreach ($recipients as $r): ?>
                <span class="person"><?= e($r['name']) ?></span>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="muted" style="margin-bottom:0;">Es gibt noch keine Teilnehmenden, die erinnert werden könnten.</p>
    <?php endif; ?>
</div>

<form method="post" action="<?= e(url('admin.reminder_send', ['id' => $poll['id']])) ?>">
    <?= csrf_field() ?>
    <div class="card stack">
        <div class="field">
            <label for="subject">Betreff</label>
            <input type="text" id="subject" name="subject" value="<?= e($defaultSubject) ?>" required>
        </div>
        <div class="field">
            <label for="body">Nachricht</label>
            <textarea id="body" name="body" style="min-height:280px;" required><?= e($defaultBody) ?></textarea>
            <p class="hint">
                Platzhalter (werden pro Person ersetzt):
                <code>{name}</code>, <code>{ueberschrift}</code>, <code>{datum}</code>, <code>{was_ich_mitbringe}</code>
            </p>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn--lg" <?= $recipients ? '' : 'disabled' ?>
                    onsubmit="return true;"
                    onclick="return confirm('Erinnerung jetzt an <?= count($recipients) ?> Person(en) senden?');">
                ✉ Jetzt senden
            </button>
        </div>
    </div>
</form>
