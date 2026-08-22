<?php /** @var array $polls */ ?>
<div class="head-row" style="margin-bottom:20px;">
    <div>
        <p class="eyebrow">Übersicht</p>
        <h1>Deine Abfragen</h1>
    </div>
    <a class="btn" href="<?= e(url('admin.poll_new')) ?>">＋ Neue Abfrage</a>
</div>

<?php if (!$polls): ?>
    <div class="card center stack">
        <div class="big-emoji" aria-hidden="true">🧺</div>
        <h2>Noch keine Abfrage</h2>
        <p class="muted">Lege deine erste Abfrage an — z.&nbsp;B. „Sommerfest: Wer bringt was mit?“.</p>
        <p><a class="btn" href="<?= e(url('admin.poll_new')) ?>">Erste Abfrage anlegen</a></p>
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
                        <span>🧩 <?= (int) $p['item_count'] ?> Dinge</span>
                        <span>🙋 <?= (int) $p['contrib_count'] ?> Teilnehmende</span>
                        <?php
                        $visLabel = ['who_and_what' => 'zeigt wer &amp; was', 'names_only' => 'zeigt Namen', 'none' => 'privat'][$p['visibility']] ?? '';
                        ?>
                        <span class="chip <?= $p['visibility'] !== 'none' ? 'chip--on' : '' ?>">👁 <?= $visLabel ?></span>
                    </div>
                </div>
                <div class="btn-row">
                    <a class="btn btn--ghost" href="<?= e(url('admin.poll_view', ['id' => $p['id']])) ?>">Öffnen</a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
