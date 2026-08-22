<?php
/** @var array $poll; @var array $items; @var array $contributions */
$link = poll_link($poll['token']);
$visLabel = ['who_and_what' => 'Wer &amp; was sichtbar', 'names_only' => 'Nur Namen sichtbar', 'none' => 'Für Teilnehmende privat'][$poll['visibility']] ?? '';
?>
<div class="head-row" style="margin-bottom:16px;">
    <div>
        <p class="eyebrow">Abfrage</p>
        <h1><?= e($poll['title']) ?></h1>
        <div class="poll-item__meta">
            <?php if ($poll['event_date']): ?><span>📅 <?= e(fmt_date($poll['event_date'])) ?></span><?php endif; ?>
            <span class="chip <?= $poll['visibility'] !== 'none' ? 'chip--on' : '' ?>">👁 <?= $visLabel ?></span>
        </div>
    </div>
    <div class="btn-row">
        <a class="btn btn--ghost" href="<?= e(url('admin.poll_edit', ['id' => $poll['id']])) ?>">Bearbeiten</a>
        <a class="btn" href="<?= e(url('admin.reminder', ['id' => $poll['id']])) ?>">✉ Erinnerung</a>
    </div>
</div>

<?php if ($poll['description']): ?>
    <div class="card"><p style="margin:0;white-space:pre-line;"><?= e($poll['description']) ?></p></div>
<?php endif; ?>

<div class="card stack">
    <h2>Teilnahme-Link</h2>
    <p class="hint">Diesen Link an die Teilnehmenden verschicken. Er enthält einen geheimen Schlüssel — nur wer ihn hat, kommt zur Abfrage.</p>
    <div class="linkbox">
        <input type="text" id="shareLink" value="<?= e($link) ?>" readonly onclick="this.select()">
        <button type="button" class="btn" id="copyBtn" data-link="<?= e($link) ?>">Kopieren</button>
        <a class="btn btn--ghost" href="<?= e($link) ?>" target="_blank" rel="noopener">Ansehen ↗</a>
    </div>
    <form method="post" action="<?= e(url('admin.poll_regen', ['id' => $poll['id']])) ?>"
          onsubmit="return confirm('Neuen Link erzeugen? Der bisherige Link wird ungültig.');">
        <?= csrf_field() ?>
        <button type="submit" class="linklike" style="color:var(--warn);">↻ Neuen Link erzeugen (alten ungültig machen)</button>
    </form>
</div>

<div class="card">
    <h2>Benötigte Dinge (<?= count($items) ?>)</h2>
    <div class="tag-people" style="margin-top:10px;">
        <?php foreach ($items as $it): ?>
            <span class="person"><?= e($it['label']) ?></span>
        <?php endforeach; ?>
        <?php if (!$items): ?><span class="muted">Keine Dinge definiert.</span><?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Wer bringt was mit? (<?= count($contributions) ?>)</h2>
    <?php if (!$contributions): ?>
        <p class="muted">Noch keine Rückmeldungen.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th>Name</th><th>E-Mail</th><th>Bringt mit</th><th>Aktualisiert</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($contributions as $c): ?>
                        <tr>
                            <td><?= e($c['name']) ?></td>
                            <td><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></td>
                            <td>
                                <?php foreach ($c['items'] as $it): ?>
                                    <div><strong><?= e($it['label']) ?></strong><?php if (trim((string) $it['detail']) !== ''): ?> — <?= e($it['detail']) ?><?php endif; ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td class="muted"><?= e(date('d.m.Y H:i', strtotime((string) ($c['updated_at'] ?: $c['created_at'])))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="hint">E-Mail-Adressen sind nur hier in der Verwaltung sichtbar — nie auf der Teilnahme-Seite.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Abfrage löschen</h2>
    <p class="hint">Entfernt die Abfrage samt aller Rückmeldungen. Das lässt sich nicht rückgängig machen.</p>
    <form method="post" action="<?= e(url('admin.poll_delete', ['id' => $poll['id']])) ?>"
          onsubmit="return confirm('Diese Abfrage und alle Rückmeldungen wirklich löschen?');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--danger">Abfrage löschen</button>
    </form>
</div>

<script>
(function () {
    var btn = document.getElementById('copyBtn');
    btn && btn.addEventListener('click', function () {
        var link = btn.getAttribute('data-link');
        var done = function () { var t = btn.textContent; btn.textContent = '✓ Kopiert'; setTimeout(function(){ btn.textContent = t; }, 1600); };
        if (navigator.clipboard) { navigator.clipboard.writeText(link).then(done, done); }
        else { var i = document.getElementById('shareLink'); i.select(); document.execCommand('copy'); done(); }
    });
})();
</script>
