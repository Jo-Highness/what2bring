<?php /** @var string $heading; @var string $text */ ?>
<div class="card card--pad-lg stack">
    <p class="eyebrow">Rechtliches</p>
    <h1><?= e($heading) ?></h1>
    <?php if (trim($text) !== ''): ?>
        <div class="legal-text"><?= nl2br(e($text)) ?></div>
    <?php else: ?>
        <p class="muted">Diese Angaben werden noch ergänzt.</p>
    <?php endif; ?>
    <p><a class="btn btn--ghost" href="#" onclick="history.back();return false;">Zurück</a></p>
</div>
