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
    // Highlight selected rows.
    document.querySelectorAll('#wmBulk .wm-row input[type=checkbox]').forEach(function (c) {
        c.addEventListener('change', function () { c.closest('.wm-row').classList.toggle('selected', c.checked); });
    });
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('#wmBulk .wm-row').forEach(function (r) { r.classList.toggle('selected', all.checked); });
        });
    }
    // "Cc Bcc" link in compose reveals the fields.
    document.querySelectorAll('[data-show]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var el = document.getElementById(a.getAttribute('data-show'));
            if (el) { el.hidden = false; a.remove(); var f = el.querySelector('input'); if (f) f.focus(); }
        });
    });
    // Discard link with confirmation.
    document.querySelectorAll('a[data-confirm]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            if (!window.confirm(a.getAttribute('data-confirm'))) e.preventDefault();
        });
    });
    // Settings: switch between plain-text and HTML signature, with a live preview.
    var modes = document.querySelectorAll('[data-sig-mode]');
    modes.forEach(function (r) {
        r.addEventListener('change', function () {
            document.querySelectorAll('[data-sig-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-sig-panel') !== r.value; });
        });
    });
    var src = document.querySelector('[data-sig-source]');
    var frame = document.querySelector('[data-sig-preview]');
    if (src && frame) {
        var timer;
        src.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                // The preview frame is sandboxed (no scripts); the server cleans the HTML again on save.
                frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><meta name="referrer" content="no-referrer"><style>body{margin:0;padding:12px;font:14px/1.5 system-ui,sans-serif;color:#1f2937}img{max-width:100%;height:auto}</style></head><body>' + src.value + '</body></html>';
            }, 250);
        });
    }
})();
