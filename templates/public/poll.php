<?php
/** @var array $poll; @var array $items; @var array $summary; @var array $names */
$token = $poll['token'];
?>
<div class="card card--pad-lg">
    <p class="eyebrow">Wer bringt was mit?</p>
    <h1><?= e($poll['title']) ?></h1>
    <?php if ($poll['event_date']): ?>
        <p class="muted" style="margin-top:-4px;">📅 Benötigt am <strong><?= e(fmt_date($poll['event_date'])) ?></strong></p>
    <?php endif; ?>
    <?php if ($poll['description']): ?>
        <p style="white-space:pre-line;"><?= e($poll['description']) ?></p>
    <?php endif; ?>
</div>

<?php if (!$items): ?>
    <div class="card"><p class="muted" style="margin:0;">Für diese Abfrage wurden noch keine Dinge hinterlegt.</p></div>
<?php else: ?>
<form method="post" action="<?= e(base_url()) ?>/index.php?<?= e(http_build_query(['r' => 'poll_submit', 'token' => $token])) ?>">
    <?= csrf_field() ?>

    <div class="card">
        <h2>Was möchtest du mitbringen?</h2>
        <p class="hint" style="margin-bottom:14px;">Mehrfachauswahl möglich. Du kannst zu jedem Ding noch Details angeben.</p>
        <ul class="bring">
            <?php foreach ($items as $it): $iid = (int) $it['id']; ?>
                <li class="bring__item" data-checked="0">
                    <div class="bring__top">
                        <input type="checkbox" id="item<?= $iid ?>" name="items[<?= $iid ?>]" value="1">
                        <label for="item<?= $iid ?>"><?= e($it['label']) ?></label>
                    </div>
                    <div class="bring__detail" hidden>
                        <input type="text" name="detail[<?= $iid ?>]" placeholder="Details, z. B. „2 Bleche Marmorkuchen“">
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card stack">
        <h2>Deine Angaben</h2>
        <div class="field">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" value="<?= e((string) old('name')) ?>" autocomplete="name" required>
        </div>
        <div class="field">
            <label for="email">E-Mail *</label>
            <input type="email" id="email" name="email" value="<?= e((string) old('email')) ?>" autocomplete="email" required>
            <p class="hint">Nur für die Orga (z. B. Erinnerungen) — sie wird niemandem sonst angezeigt.</p>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn--lg">Eintragen</button>
        </div>
    </div>
</form>
<?php endif; ?>

<?php if ($poll['visibility'] === 'who_and_what' && $items): ?>
    <div class="card">
        <h2>Schon dabei</h2>
        <ul class="summary">
            <?php foreach ($summary as $it): ?>
                <li class="summary__item">
                    <div class="summary__label"><?= e($it['label']) ?></div>
                    <?php if (!empty($it['entries'])): ?>
                        <div class="tag-people">
                            <?php foreach ($it['entries'] as $en): ?>
                                <span class="person"><?= e($en['name']) ?><?php if (trim((string) $en['detail']) !== ''): ?> · <?= e($en['detail']) ?><?php endif; ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="summary__names muted">Noch niemand — magst du?</div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif ($poll['visibility'] === 'names_only' && $names): ?>
    <div class="card">
        <h2>Schon dabei (<?= count($names) ?>)</h2>
        <div class="tag-people">
            <?php foreach ($names as $n): ?><span class="person"><?= e($n) ?></span><?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
    document.querySelectorAll('.bring__item').forEach(function (li) {
        var cb = li.querySelector('input[type=checkbox]');
        var detail = li.querySelector('.bring__detail');
        var sync = function () {
            li.setAttribute('data-checked', cb.checked ? '1' : '0');
            detail.hidden = !cb.checked;
        };
        cb.addEventListener('change', sync);
        sync();
    });
})();
</script>
