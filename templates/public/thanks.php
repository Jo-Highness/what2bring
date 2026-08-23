<?php /** @var array $poll; @var string $name; @var string $token */ ?>
<div class="card card--pad-lg center stack">
    <div class="big-emoji" aria-hidden="true">🎉</div>
    <h1><?= $name !== '' ? e(t('thanks.hi', ['name' => $name])) : e(t('thanks.hi_noname')) ?></h1>
    <p class="muted"><?= e(t('thanks.saved', ['title' => $poll['title']])) ?></p>
    <p><?= e(t('thanks.update_hint')) ?></p>
    <p><a class="btn btn--ghost" href="<?= e(poll_link($token)) ?>"><?= e(t('thanks.back')) ?></a></p>
</div>
