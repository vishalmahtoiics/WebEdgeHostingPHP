// Webmail: select-all, bulk "Move to" and nothing else (no inline scripts: CSP-friendly).
(function () {
    'use strict';
    var all = document.getElementById('wmAll');
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('#wmBulk input[name="uids[]"]').forEach(function (c) { c.checked = all.checked; });
        });
    }
    document.querySelectorAll('[data-move]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (!sel.value) return;
            var form = sel.form;
            var op = document.createElement('input');
            op.type = 'hidden'; op.name = 'op'; op.value = 'move';
            form.appendChild(op);
            form.submit();
        });
    });
    // Discard link with confirmation.
    document.querySelectorAll('a[data-confirm]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            if (!window.confirm(a.getAttribute('data-confirm'))) e.preventDefault();
        });
    });
})();
