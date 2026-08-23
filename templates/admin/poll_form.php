<?php
/** @var array|null $poll; @var array $items */
$isEdit = $poll !== null;
$action = $isEdit ? url('admin.poll_update', ['id' => $poll['id']]) : url('admin.poll_create');
$vTitle = $isEdit ? $poll['title'] : old('title');
$vDesc  = $isEdit ? (string) $poll['description'] : old('description');
$vDate  = $isEdit ? (string) $poll['event_date'] : old('event_date');
$vVis   = $isEdit ? $poll['visibility'] : (old('visibility') ?: 'who_and_what');
$vEmailReq = $isEdit ? (int) $poll['email_required'] : (int) old('email_required', '1');
$rows = [];
if ($isEdit) {
    foreach ($items as $it) {
        $rows[] = ['id' => $it['id'], 'label' => $it['label']];
    }
} else {
    foreach ((array) old('item_label', []) as $l) {
        if (trim((string) $l) !== '') {
            $rows[] = ['id' => '', 'label' => $l];
        }
    }
}
if (!$rows) {
    $rows = [['id' => '', 'label' => '']];
}
$visKeys = ['who_and_what', 'names_only', 'none'];
?>
<div class="head-row" style="margin-bottom:16px;">
    <div>
        <p class="eyebrow"><?= $isEdit ? e(t('form.edit_eyebrow')) : e(t('form.new_eyebrow')) ?></p>
        <h1><?= $isEdit ? e($poll['title']) : e(t('form.new_title')) ?></h1>
    </div>
    <a class="btn btn--ghost" href="<?= e($isEdit ? url('admin.poll_view', ['id' => $poll['id']]) : url('admin')) ?>"><?= e(t('form.cancel')) ?></a>
</div>

<form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>

    <div class="card stack">
        <div class="field">
            <label for="title"><?= e(t('form.title_label')) ?> *</label>
            <input type="text" id="title" name="title" value="<?= e($vTitle) ?>" placeholder="<?= e(t('form.title_ph')) ?>" required>
        </div>
        <div class="field">
            <label for="description"><?= e(t('form.desc_label')) ?></label>
            <textarea id="description" name="description" placeholder="<?= e(t('form.desc_ph')) ?>"><?= e($vDesc) ?></textarea>
            <p class="hint"><?= e(t('form.desc_hint')) ?></p>
        </div>
        <div class="field">
            <label for="event_date"><?= e(t('form.date_label')) ?></label>
            <input type="date" id="event_date" name="event_date" value="<?= e($vDate) ?>">
            <p class="hint"><?= e(t('form.date_hint')) ?></p>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;font-weight:500;">
                <input type="hidden" name="email_required" value="0">
                <input type="checkbox" name="email_required" value="1" <?= $vEmailReq ? 'checked' : '' ?> style="margin-top:3px;accent-color:var(--accent);width:20px;height:20px;flex:0 0 auto;">
                <span><strong><?= e(t('form.email_required_label')) ?></strong><br>
                    <span class="muted" style="font-size:.9rem;"><?= e(t('form.email_required_hint')) ?></span></span>
            </label>
        </div>
    </div>

    <div class="card">
        <h2><?= e(t('form.things_title')) ?></h2>
        <p class="hint" style="margin-bottom:14px;"><?= e(t('form.things_hint')) ?></p>
        <div class="item-rows" id="itemRows">
            <?php foreach ($rows as $row): ?>
                <div class="item-row">
                    <input type="hidden" name="item_id[]" value="<?= e((string) $row['id']) ?>">
                    <input type="text" name="item_label[]" value="<?= e($row['label']) ?>" placeholder="<?= e(t('form.thing_ph')) ?>">
                    <button type="button" class="btn btn--muted remove" aria-label="<?= e(t('form.remove')) ?>">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="spacer-s"></div>
        <button type="button" class="btn btn--ghost" id="addItem">＋ <?= e(t('form.add_thing')) ?></button>
    </div>

    <div class="card">
        <h2><?= e(t('form.visibility_title')) ?></h2>
        <p class="hint" style="margin-bottom:14px;"><?= e(t('form.visibility_hint')) ?></p>
        <fieldset class="stack">
            <?php foreach ($visKeys as $val): ?>
                <label style="display:flex;gap:10px;align-items:flex-start;font-weight:500;cursor:pointer;">
                    <input type="radio" name="visibility" value="<?= e($val) ?>" <?= $vVis === $val ? 'checked' : '' ?> style="margin-top:5px;accent-color:var(--accent);">
                    <span><strong><?= e(t('vis.' . $val)) ?></strong><br><span class="muted" style="font-size:.9rem;"><?= e(t('vis.' . $val . '_desc')) ?></span></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
    </div>

    <div class="btn-row">
        <button type="submit" class="btn btn--lg"><?= $isEdit ? e(t('form.save_changes')) : e(t('form.create')) ?></button>
    </div>
</form>

<template id="rowTpl">
    <div class="item-row">
        <input type="hidden" name="item_id[]" value="">
        <input type="text" name="item_label[]" value="" placeholder="<?= e(t('form.thing_ph')) ?>">
        <button type="button" class="btn btn--muted remove" aria-label="<?= e(t('form.remove')) ?>">✕</button>
    </div>
</template>

<script>
(function () {
    var rows = document.getElementById('itemRows');
    var tpl = document.getElementById('rowTpl');
    document.getElementById('addItem').addEventListener('click', function () {
        rows.appendChild(tpl.content.cloneNode(true));
        rows.lastElementChild.querySelector('input[type=text]').focus();
    });
    rows.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove')) {
            if (rows.querySelectorAll('.item-row').length > 1) {
                e.target.closest('.item-row').remove();
            } else {
                var t = e.target.closest('.item-row').querySelector('input[type=text]');
                t.value = '';
                t.focus();
            }
        }
    });
})();
</script>
