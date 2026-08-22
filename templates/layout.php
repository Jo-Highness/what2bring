<?php /** @var string $title, $content; @var array $flashes; @var bool $public */ ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · What2Bring</title>
    <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🧺</text></svg>') ?>">
    <link rel="stylesheet" href="<?= e(base_url()) ?>/assets/style.css">
</head>
<body class="<?= ($public ?? false) ? 'is-public' : 'is-admin' ?>">
<header class="topbar">
    <div class="wrap topbar__inner">
        <a class="brand" href="<?= e(($public ?? false) ? '#' : url('admin')) ?>">
            <span class="brand__mark" aria-hidden="true">🧺</span>
            <span class="brand__name">What2Bring</span>
        </a>
        <?php if (!($public ?? false) && is_admin()): ?>
            <nav class="topnav">
                <a href="<?= e(url('admin')) ?>">Übersicht</a>
                <a href="<?= e(url('admin.legal')) ?>">Rechtliches</a>
                <a class="btn btn--ghost" href="<?= e(url('admin.poll_new')) ?>">＋ Neue Abfrage</a>
                <form method="post" action="<?= e(url('admin.logout')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="linklike">Abmelden</button>
                </form>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="wrap main">
    <?php foreach (($flashes ?? []) as $f): ?>
        <div class="flash flash--<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="footer">
    <div class="wrap footer__inner">
        <p>Wer bringt was mit? · <span class="muted">Vereinsfeste ganz einfach organisieren</span></p>
        <p class="footer__links"><a href="<?= e(url('impressum')) ?>">Impressum</a> · <a href="<?= e(url('datenschutz')) ?>">Datenschutz</a></p>
    </div>
</footer>
</body>
</html>
