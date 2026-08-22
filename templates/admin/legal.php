<?php /** @var string $impressum; @var string $datenschutz */ ?>
<div class="head-row" style="margin-bottom:16px;">
    <div>
        <p class="eyebrow">Rechtliches</p>
        <h1>Impressum &amp; Datenschutz</h1>
    </div>
    <a class="btn btn--ghost" href="<?= e(url('admin')) ?>">Zurück</a>
</div>

<div class="card">
    <p style="margin-top:0;">Diese Texte erscheinen öffentlich unter den Footer-Links
        <a href="<?= e(url('impressum')) ?>" target="_blank" rel="noopener">Impressum</a> und
        <a href="<?= e(url('datenschutz')) ?>" target="_blank" rel="noopener">Datenschutz</a>.
        Ersetze die Platzhalter in eckigen Klammern (z.&nbsp;B. <code>[Vor- und Nachname]</code>) durch deine Angaben.</p>
    <p class="hint" style="margin-bottom:0;">Hinweis: Vorlagen sind ein Entwurf und keine Rechtsberatung – bitte vor dem Live-Gang prüfen (ggf. anwaltlich).</p>
</div>

<form method="post" action="<?= e(url('admin.legal_save')) ?>">
    <?= csrf_field() ?>
    <div class="card stack">
        <div class="field">
            <label for="impressum">Impressum</label>
            <textarea id="impressum" name="impressum" style="min-height:260px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.92rem;"><?= e($impressum) ?></textarea>
        </div>
        <div class="field">
            <label for="datenschutz">Datenschutzerklärung</label>
            <textarea id="datenschutz" name="datenschutz" style="min-height:420px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.92rem;"><?= e($datenschutz) ?></textarea>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn--lg">Speichern</button>
        </div>
    </div>
</form>
