<?php /** @var array $polls */ ?>
<div class="head-row" style="margin-bottom:20px;">
    <div>
        <p class="eyebrow"><?= e(t('dash.eyebrow')) ?></p>
        <h1><?= e(t('dash.title')) ?></h1>
    </div>
    <a class="btn" href="<?= e(url('admin.poll_new')) ?>">+ <?= e(t('nav.new_poll')) ?></a>
</div>

<?php if (!$polls): ?>
    <div class="card center stack">
        <div class="big-emoji" aria-hidden="true">🧺</div>
        <h2><?= e(t('dash.empty_title')) ?></h2>
        <p class="muted"><?= e(t('dash.empty_text')) ?></p>
        <p><a class="btn" href="<?= e(url('admin.poll_new')) ?>"><?= e(t('dash.empty_cta')) ?></a></p>
    </div>
<?php else: ?>
    <ul class="polls">
        <?php foreach ($polls as $p): ?>
            <li class="card poll-item">
                <div>
                    <h2 style="margin-bottom:2px;">
                        <a href="<?= e(url('admin.poll_view', ['id' => $p['id']])) ?>"><?= e($p['title']) ?></a>
                    </h2>
                    <div class="poll-item__meta">
                        <?php if ($p['event_date']): ?><span>📅 <?= e(fmt_date($p['event_date'])) ?></span><?php endif; ?>
                        <span>🧩 <?= e(t('dash.things', ['n' => (int) $p['item_count']])) ?></span>
                        <span>🙋 <?= e(t('dash.participants', ['n' => (int) $p['contrib_count']])) ?></span>
                        <span class="chip <?= $p['visibility'] !== 'none' ? 'chip--on' : '' ?>">👁 <?= e(t('vis.' . $p['visibility'] . '_short')) ?></span>
                    </div>
                </div>
                <div class="btn-row">
                    <a class="btn btn--ghost" href="<?= e(url('admin.poll_view', ['id' => $p['id']])) ?>"><?= e(t('dash.open')) ?></a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
