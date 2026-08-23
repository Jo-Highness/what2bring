<?php
/** @var array $poll; @var array $recipients; @var string $defaultSubject; @var string $defaultBody */
?>
<div class="head-row" style="margin-bottom:16px;">
    <div>
        <p class="eyebrow"><?= e(t('rem.eyebrow')) ?></p>
        <h1><?= e($poll['title']) ?></h1>
    </div>
    <a class="btn btn--ghost" href="<?= e(url('admin.poll_view', ['id' => $poll['id']])) ?>"><?= e(t('rem.back')) ?></a>
</div>

<div class="card">
    <p style="margin-top:0;"><?= e(t('rem.intro', ['n' => count($recipients)])) ?></p>
    <?php if ($recipients): ?>
        <div class="tag-people">
            <?php foreach ($recipients as $r): ?>
                <span class="person"><?= e($r['name']) ?></span>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="muted" style="margin-bottom:0;"><?= e(t('rem.no_recipients')) ?></p>
    <?php endif; ?>
</div>

<form method="post" action="<?= e(url('admin.reminder_send', ['id' => $poll['id']])) ?>">
    <?= csrf_field() ?>
    <div class="card stack">
        <div class="field">
            <label for="subject"><?= e(t('rem.subject')) ?></label>
            <input type="text" id="subject" name="subject" value="<?= e($defaultSubject) ?>" required>
        </div>
        <div class="field">
            <label for="body"><?= e(t('rem.message')) ?></label>
            <textarea id="body" name="body" style="min-height:280px;" required><?= e($defaultBody) ?></textarea>
            <p class="hint">
                <?= e(t('rem.placeholders')) ?>
                <code>{name}</code>, <code>{ueberschrift}</code>, <code>{datum}</code>, <code>{was_ich_mitbringe}</code>
            </p>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn--lg" <?= $recipients ? '' : 'disabled' ?>
                    onclick="return confirm('<?= e(t('rem.send_confirm', ['n' => count($recipients)])) ?>');">
                ✉ <?= e(t('rem.send')) ?>
            </button>
        </div>
    </div>
</form>
