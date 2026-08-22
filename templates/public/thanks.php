<?php /** @var array $poll; @var string $name; @var string $token */ ?>
<div class="card card--pad-lg center stack">
    <div class="big-emoji" aria-hidden="true">🎉</div>
    <h1>Danke<?= $name !== '' ? ', ' . e($name) : '' ?>!</h1>
    <p class="muted">Deine Angabe für „<?= e($poll['title']) ?>“ wurde gespeichert.</p>
    <p>Du kannst deine Auswahl jederzeit über deinen persönlichen Link anpassen — trag dich einfach mit derselben E-Mail-Adresse erneut ein.</p>
    <p><a class="btn btn--ghost" href="<?= e(poll_link($token)) ?>">Zur Abfrage zurück</a></p>
</div>
