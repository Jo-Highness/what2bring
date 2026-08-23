<?php
/** @var array $poll; @var array $items; @var array $contributions */
$link = poll_link($poll['token']);
?>
<div class="head-row" style="margin-bottom:16px;">
    <div>
        <p class="eyebrow"><?= e(t('view.eyebrow')) ?></p>
        <h1><?= e($poll['title']) ?></h1>
        <div class="poll-item__meta">
            <?php if ($poll['event_date']): ?><span>📅 <?= e(fmt_date($poll['event_date'])) ?></span><?php endif; ?>
            <span class="chip <?= $poll['visibility'] !== 'none' ? 'chip--on' : '' ?>">👁 <?= e(t('vis.' . $poll['visibility'] . '_status')) ?></span>
            <span class="chip">✉ <?= (int) $poll['email_required'] ? e(t('view.email_required_chip')) : e(t('view.email_optional_chip')) ?></span>
        </div>
    </div>
    <div class="btn-row">
        <a class="btn btn--ghost" href="<?= e(url('admin.poll_edit', ['id' => $poll['id']])) ?>"><?= e(t('view.edit')) ?></a>
        <a class="btn" href="<?= e(url('admin.reminder', ['id' => $poll['id']])) ?>">✉ <?= e(t('view.reminder')) ?></a>
    </div>
</div>

<?php if ($poll['description']): ?>
    <div class="card"><p style="margin:0;white-space:pre-line;"><?= e($poll['description']) ?></p></div>
<?php endif; ?>

<div class="card stack">
    <h2><?= e(t('view.link_title')) ?></h2>
    <p class="hint"><?= e(t('view.link_hint')) ?></p>
    <div class="linkbox">
        <input type="text" id="shareLink" value="<?= e($link) ?>" readonly onclick="this.select()">
        <button type="button" class="btn" id="copyBtn" data-link="<?= e($link) ?>" data-copied="<?= e(t('view.copied')) ?>"><?= e(t('view.copy')) ?></button>
        <a class="btn btn--ghost" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e(t('view.view')) ?> ↗</a>
    </div>
    <form method="post" action="<?= e(url('admin.poll_regen', ['id' => $poll['id']])) ?>"
          onsubmit="return confirm('<?= e(t('view.regen_confirm')) ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="linklike" style="color:var(--warn);">↻ <?= e(t('view.regen')) ?></button>
    </form>
</div>

<div class="card">
    <h2><?= e(t('view.things_title', ['n' => count($items)])) ?></h2>
    <div class="tag-people" style="margin-top:10px;">
        <?php foreach ($items as $it): ?>
            <span class="person"><?= e($it['label']) ?></span>
        <?php endforeach; ?>
        <?php if (!$items): ?><span class="muted"><?= e(t('view.no_things')) ?></span><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="head-row" style="margin-bottom:12px;">
        <h2 style="margin:0;"><?= e(t('view.contribs_title', ['n' => count($contributions)])) ?></h2>
        <?php if ($contributions): ?>
            <a class="btn btn--ghost" href="<?= e(url('admin.poll_export', ['id' => $poll['id']])) ?>">⬇ <?= e(t('view.csv_export')) ?></a>
        <?php endif; ?>
    </div>
    <?php if (!$contributions): ?>
        <p class="muted"><?= e(t('view.no_contribs')) ?></p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th><?= e(t('view.col_name')) ?></th><th><?= e(t('view.col_email')) ?></th><th><?= e(t('view.col_brings')) ?></th><th><?= e(t('view.col_updated')) ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($contributions as $c): ?>
                        <tr>
                            <td><?= e($c['name']) ?></td>
                            <td><?php if (!empty($c['email'])): ?><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?php else: ?><span class="muted"><?= e(t('view.no_email')) ?></span><?php endif; ?></td>
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
        <p class="hint"><?= e(t('view.email_hint')) ?></p>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?= e(t('view.delete_title')) ?></h2>
    <p class="hint"><?= e(t('view.delete_hint')) ?></p>
    <form method="post" action="<?= e(url('admin.poll_delete', ['id' => $poll['id']])) ?>"
          onsubmit="return confirm('<?= e(t('view.delete_confirm')) ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--danger"><?= e(t('view.delete')) ?></button>
    </form>
</div>

<script>
(function () {
    var btn = document.getElementById('copyBtn');
    btn && btn.addEventListener('click', function () {
        var link = btn.getAttribute('data-link');
        var done = function () { var o = btn.textContent; btn.textContent = '✓ ' + btn.getAttribute('data-copied'); setTimeout(function(){ btn.textContent = o; }, 1600); };
        if (navigator.clipboard) { navigator.clipboard.writeText(link).then(done, done); }
        else { var i = document.getElementById('shareLink'); i.select(); document.execCommand('copy'); done(); }
    });
})();
</script>
