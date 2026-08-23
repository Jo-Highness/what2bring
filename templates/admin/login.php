<?php /** login form */ ?>
<div class="card card--pad-lg" style="max-width:420px;margin-inline:auto;">
    <p class="eyebrow"><?= e(t('login.eyebrow')) ?></p>
    <h1><?= e(t('login.title')) ?></h1>
    <p class="muted"><?= e(t('login.intro')) ?></p>
    <form method="post" action="<?= e(url('admin.login')) ?>" class="stack">
        <?= csrf_field() ?>
        <div class="field">
            <label for="password"><?= e(t('login.password')) ?></label>
            <input type="password" id="password" name="password" autocomplete="current-password" autofocus required>
        </div>
        <button type="submit" class="btn btn--lg"><?= e(t('login.submit')) ?></button>
    </form>
</div>
