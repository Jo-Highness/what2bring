<?php /** @var string $impressum; @var string $datenschutz */ ?>
<div class="head-row" style="margin-bottom:16px;">
    <div>
        <p class="eyebrow"><?= e(t('legal.eyebrow')) ?></p>
        <h1><?= e(t('legal.title')) ?></h1>
    </div>
    <a class="btn btn--ghost" href="<?= e(url('admin')) ?>"><?= e(t('legal.back')) ?></a>
</div>

<div class="card">
    <p style="margin-top:0;"><?= e(t('legal.intro', ['impressum' => t('legal.impressum'), 'datenschutz' => t('legal.datenschutz')])) ?></p>
    <p class="hint" style="margin-bottom:0;">
        <a href="<?= e(url('impressum')) ?>" target="_blank" rel="noopener"><?= e(t('nav.impressum')) ?> ↗</a> ·
        <a href="<?= e(url('datenschutz')) ?>" target="_blank" rel="noopener"><?= e(t('nav.datenschutz')) ?> ↗</a>
    </p>
    <p class="hint" style="margin-bottom:0;"><?= e(t('legal.disclaimer')) ?></p>
</div>

<form method="post" action="<?= e(url('admin.legal_save')) ?>">
    <?= csrf_field() ?>
    <div class="card stack">
        <div class="field">
            <label for="impressum"><?= e(t('legal.impressum')) ?></label>
            <textarea id="impressum" name="impressum" style="min-height:260px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.92rem;"><?= e($impressum) ?></textarea>
        </div>
        <div class="field">
            <label for="datenschutz"><?= e(t('legal.datenschutz')) ?></label>
            <textarea id="datenschutz" name="datenschutz" style="min-height:420px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.92rem;"><?= e($datenschutz) ?></textarea>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn--lg"><?= e(t('legal.save')) ?></button>
        </div>
    </div>
</form>
