/* What2Bring — all client-side behaviour (no inline scripts, so the CSP can be script-src 'self'). */
(function () {
    'use strict';

    // Participant page: show the detail field for ticked items.
    document.querySelectorAll('.bring__item').forEach(function (li) {
        var cb = li.querySelector('input[type=checkbox]');
        var detail = li.querySelector('.bring__detail');
        if (!cb || !detail) { return; }
        var sync = function () {
            li.setAttribute('data-checked', cb.checked ? '1' : '0');
            detail.hidden = !cb.checked;
        };
        cb.addEventListener('change', sync);
        sync();
    });

    // Admin poll form: add / remove item rows.
    var rows = document.getElementById('itemRows');
    var tpl = document.getElementById('rowTpl');
    var add = document.getElementById('addItem');
    if (rows && tpl && add) {
        add.addEventListener('click', function () {
            rows.appendChild(tpl.content.cloneNode(true));
            rows.lastElementChild.querySelector('input[type=text]').focus();
        });
        rows.addEventListener('click', function (e) {
            if (!e.target.classList.contains('remove')) { return; }
            if (rows.querySelectorAll('.item-row').length > 1) {
                e.target.closest('.item-row').remove();
            } else {
                var t = e.target.closest('.item-row').querySelector('input[type=text]');
                t.value = '';
                t.focus();
            }
        });
    }

    // Confirmation dialogs: <form data-confirm="Question?">
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) { e.preventDefault(); }
        });
    });

    // Read-only link fields: select all on click.
    document.querySelectorAll('[data-select-on-click]').forEach(function (input) {
        input.addEventListener('click', function () { input.select(); });
    });

    // Copy buttons: <button data-copy-target="inputId" data-copied="Copied">
    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-copy-target'));
            if (!input) { return; }
            var original = btn.textContent;
            var done = function () {
                btn.textContent = '✓ ' + btn.getAttribute('data-copied');
                setTimeout(function () { btn.textContent = original; }, 1600);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(done, done);
            } else {
                input.select();
                document.execCommand('copy');
                done();
            }
        });
    });

    // "Back" links: use the browser history when there is one.
    document.querySelectorAll('[data-history-back]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            if (window.history.length > 1) {
                e.preventDefault();
                window.history.back();
            }
        });
    });
})();
