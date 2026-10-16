<?php /** @var array $poll; @var string $name; @var string $link; @var bool $updated; @var string $token */ ?>
<div class="card card--pad-lg center stack">
    <div class="big-emoji" aria-hidden="true">🎉</div>
    <h1><?= $name !== '' ? e(t('thanks.hi', ['name' => $name])) : e(t('thanks.hi_noname')) ?></h1>
    <p class="muted"><?= e(t($updated ? 'thanks.updated' : 'thanks.saved', ['title' => $poll['title']])) ?></p>
    <?php if ($link !== ''): ?>
        <p><?= e(t('thanks.update_hint')) ?></p>
        <div class="linkbox">
            <input type="text" id="editLink" value="<?= e($link) ?>" readonly data-select-on-click>
            <button type="button" class="btn" data-copy-target="editLink" data-copied="<?= e(t('view.copied')) ?>"><?= e(t('view.copy')) ?></button>
        </div>
        <p class="hint"><?= e(t('thanks.link_secret')) ?></p>
    <?php endif; ?>
    <p><a class="btn btn--ghost" href="<?= e(poll_link($token)) ?>"><?= e(t('thanks.back')) ?></a></p>
</div>
