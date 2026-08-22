<?php
/** @var array|null $poll; @var array $items */
$isEdit = $poll !== null;
$action = $isEdit ? url('admin.poll_update', ['id' => $poll['id']]) : url('admin.poll_create');
$vTitle = $isEdit ? $poll['title'] : old('title');
$vDesc  = $isEdit ? (string) $poll['description'] : old('description');
$vDate  = $isEdit ? (string) $poll['event_date'] : old('event_date');
$vVis   = $isEdit ? $poll['visibility'] : (old('visibility') ?: 'who_and_what');
$vEmailReq = $isEdit ? (int) $poll['email_required'] : (int) old('email_required', '1');
// rows to prefill
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
$visOptions = [
    'who_and_what' => ['Wer &amp; was anzeigen', 'Teilnehmende sehen, welche Dinge schon von wem mitgebracht werden (mit Details, ohne E-Mail).'],
    'names_only'   => ['Nur Namen anzeigen', 'Teilnehmende sehen die Namen der bisher Teilnehmenden, aber nicht wer was bringt.'],
    'none'         => ['Nichts anzeigen', 'Teilnehmende sehen nicht, was andere schon mitbringen.'],
];
?>
<div class="head-row" style="margin-bottom:16px;">
    <div>
        <p class="eyebrow"><?= $isEdit ? 'Bearbeiten' : 'Neu' ?></p>
        <h1><?= $isEdit ? e($poll['title']) : 'Neue Abfrage' ?></h1>
    </div>
    <a class="btn btn--ghost" href="<?= e($isEdit ? url('admin.poll_view', ['id' => $poll['id']]) : url('admin')) ?>">Abbrechen</a>
</div>

<form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>

    <div class="card stack">
        <div class="field">
            <label for="title">Überschrift *</label>
            <input type="text" id="title" name="title" value="<?= e($vTitle) ?>" placeholder="z. B. Sommerfest des TSV — Wer bringt was mit?" required>
        </div>
        <div class="field">
            <label for="description">Beschreibungstext</label>
            <textarea id="description" name="description" placeholder="Ein paar Sätze für die Teilnehmenden …"><?= e($vDesc) ?></textarea>
            <p class="hint">Erscheint oben auf der Teilnahme-Seite.</p>
        </div>
        <div class="field">
            <label for="event_date">Benötigt am</label>
            <input type="date" id="event_date" name="event_date" value="<?= e($vDate) ?>">
            <p class="hint">Wann werden die Sachen gebraucht? (optional)</p>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;font-weight:500;">
                <input type="hidden" name="email_required" value="0">
                <input type="checkbox" name="email_required" value="1" <?= $vEmailReq ? 'checked' : '' ?> style="margin-top:3px;accent-color:var(--accent);width:20px;height:20px;flex:0 0 auto;">
                <span><strong>E-Mail-Adresse ist Pflicht</strong><br>
                    <span class="muted" style="font-size:.9rem;">Wenn deaktiviert, können Teilnehmende auch ohne E-Mail mitmachen — sie erhalten dann aber keine Erinnerung.</span></span>
            </label>
        </div>
    </div>

    <div class="card">
        <h2>Benötigte Dinge</h2>
        <p class="hint" style="margin-bottom:14px;">z. B. Kuchen, Salat, Obst … — Teilnehmende können daraus auswählen.</p>
        <div class="item-rows" id="itemRows">
            <?php foreach ($rows as $row): ?>
                <div class="item-row">
                    <input type="hidden" name="item_id[]" value="<?= e((string) $row['id']) ?>">
                    <input type="text" name="item_label[]" value="<?= e($row['label']) ?>" placeholder="Ding, z. B. Kuchen">
                    <button type="button" class="btn btn--muted remove" aria-label="Zeile entfernen">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="spacer-s"></div>
        <button type="button" class="btn btn--ghost" id="addItem">＋ Ding hinzufügen</button>
    </div>

    <div class="card">
        <h2>Sichtbarkeit für Teilnehmende</h2>
        <p class="hint" style="margin-bottom:14px;">Was sehen Teilnehmende über die Beiträge der anderen? E-Mail-Adressen werden nie angezeigt.</p>
        <fieldset class="stack">
            <?php foreach ($visOptions as $val => [$lab, $desc]): ?>
                <label style="display:flex;gap:10px;align-items:flex-start;font-weight:500;cursor:pointer;">
                    <input type="radio" name="visibility" value="<?= e($val) ?>" <?= $vVis === $val ? 'checked' : '' ?> style="margin-top:5px;accent-color:var(--accent);">
                    <span><strong><?= $lab ?></strong><br><span class="muted" style="font-size:.9rem;"><?= $desc ?></span></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
    </div>

    <div class="btn-row">
        <button type="submit" class="btn btn--lg"><?= $isEdit ? 'Änderungen speichern' : 'Abfrage anlegen' ?></button>
    </div>
</form>

<template id="rowTpl">
    <div class="item-row">
        <input type="hidden" name="item_id[]" value="">
        <input type="text" name="item_label[]" value="" placeholder="Ding, z. B. Kuchen">
        <button type="button" class="btn btn--muted remove" aria-label="Zeile entfernen">✕</button>
    </div>
</template>

<script>
(function () {
    var rows = document.getElementById('itemRows');
    var tpl = document.getElementById('rowTpl');
    document.getElementById('addItem').addEventListener('click', function () {
        var node = tpl.content.cloneNode(true);
        rows.appendChild(node);
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
