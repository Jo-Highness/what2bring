<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = token_new(32);
    }
    return $_SESSION['csrf'];
}

/** Hidden input for POST forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Validate the token from a submitted form; abort on mismatch. */
function csrf_check(): void
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    $have = (string) ($_SESSION['csrf'] ?? '');
    if ($have === '' || !hash_equals($have, $sent)) {
        http_response_code(400);
        exit('Ungültiges oder abgelaufenes Formular (CSRF). Bitte die Seite neu laden und erneut versuchen.');
    }
}
