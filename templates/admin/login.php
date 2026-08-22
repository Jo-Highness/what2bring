<?php /** login form */ ?>
<div class="card card--pad-lg" style="max-width:420px;margin-inline:auto;">
    <p class="eyebrow">Administration</p>
    <h1>Anmelden</h1>
    <p class="muted">Bitte das Admin-Passwort eingeben, um Abfragen zu verwalten.</p>
    <form method="post" action="<?= e(url('admin.login')) ?>" class="stack">
        <?= csrf_field() ?>
        <div class="field">
            <label for="password">Passwort</label>
            <input type="password" id="password" name="password" autocomplete="current-password" autofocus required>
        </div>
        <button type="submit" class="btn btn--lg">Anmelden</button>
    </form>
</div>
