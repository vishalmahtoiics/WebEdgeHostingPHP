/* WebEdge control panel behaviour (no inline scripts: CSP-friendly). */
(function () {
    'use strict';

    // Confirm destructive actions: <form data-confirm="Are you sure?">
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
            return;
        }
        // Prevent double submission.
        var btns = form.querySelectorAll('button[type=submit], button:not([type])');
        window.setTimeout(function () {
            btns.forEach(function (b) { b.disabled = true; });
        }, 0);
    });

    // Print buttons.
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    // Auto-submit filter selects: <select data-autosubmit>
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { el.form.submit(); });
    });

    // Show/hide password fields.
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-password'));
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.querySelector('i').className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    });

    // Invoice line items editor.
    var items = document.getElementById('invoice-items');
    if (items) {
        var template = document.getElementById('invoice-item-template');
        var parse = function (v) {
            v = String(v || '').replace(/[,\s₹]/g, '');
            return /^\d+(\.\d{1,2})?$/.test(v) ? Math.round(parseFloat(v) * 100) : 0;
        };
        var fmt = function (p) { return (p / 100).toFixed(2); };
        var recalc = function () {
            var subtotal = 0;
            items.querySelectorAll('.invoice-item').forEach(function (row) {
                var qty = parseInt(row.querySelector('[name="qty[]"]').value, 10) || 0;
                var price = parse(row.querySelector('[name="price[]"]').value);
                var amount = qty * price;
                row.querySelector('.item-amount').textContent = fmt(amount);
                subtotal += amount;
            });
            var discount = parse((document.getElementById('discount') || {}).value);
            var out = document.getElementById('invoice-subtotal');
            if (out) out.textContent = fmt(subtotal) + (discount ? ' − ' + fmt(Math.min(discount, subtotal)) + ' discount' : '');
        };
        document.getElementById('add-item').addEventListener('click', function () {
            items.appendChild(template.content.cloneNode(true));
            recalc();
        });
        items.addEventListener('click', function (e) {
            var btn = e.target.closest('.remove-item');
            if (btn && items.querySelectorAll('.invoice-item').length > 1) {
                btn.closest('.invoice-item').remove();
                recalc();
            }
        });
        document.addEventListener('input', recalc);
        recalc();
    }

    // Keep the active settings tab in the URL hash.
    var hash = window.location.hash;
    if (hash && document.querySelector('[data-bs-target="' + hash + '"]') && window.bootstrap) {
        bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="' + hash + '"]')).show();
    }
    document.querySelectorAll('[data-bs-toggle="pill"][data-bs-target]').forEach(function (el) {
        el.addEventListener('shown.bs.tab', function () {
            history.replaceState(null, '', el.getAttribute('data-bs-target'));
            var field = document.getElementById('settings-tab');
            if (field) field.value = el.getAttribute('data-bs-target').replace('#tab-', '');
        });
    });
})();
