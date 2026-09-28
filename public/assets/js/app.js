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


    // DNS record form: show the fields that apply to the chosen record type.
    var dnsType = document.querySelector('[data-dns-type]');
    if (dnsType) {
        var placeholders = { A: '203.0.113.10', AAAA: '2001:db8::1', CNAME: 'target.example.com', ALIAS: 'target.example.com',
            MX: 'mail.example.com', TXT: 'v=spf1 include:example.com ~all', NS: 'ns1.example.com', SRV: 'sip.example.com', CAA: 'letsencrypt.org' };
        var syncDns = function () {
            var t = dnsType.value;
            document.querySelectorAll('[data-dns-fields]').forEach(function (el) {
                el.hidden = el.getAttribute('data-dns-fields').split(' ').indexOf(t) === -1;
            });
            var help = document.querySelector('template[data-help-for="' + t + '"]');
            var target = document.querySelector('[data-dns-help]');
            if (help && target) target.textContent = help.innerHTML.replace(/&amp;/g, '&');
            var value = document.getElementById('value');
            if (value) value.placeholder = placeholders[t] || '';
        };
        dnsType.addEventListener('change', syncDns);
        syncDns();
    }

    // Copy-to-clipboard buttons: <button data-copy="text">
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(btn.getAttribute('data-copy')).then(function () {
                var old = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check2"></i>';
                window.setTimeout(function () { btn.innerHTML = old; }, 1200);
            });
        });
    });

    // Reveal secrets on demand: <button data-reveal="elementId">
    document.querySelectorAll('[data-reveal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = document.getElementById(btn.getAttribute('data-reveal'));
            if (!el) return;
            var hidden = el.getAttribute('data-hidden') !== 'false';
            el.textContent = hidden ? el.getAttribute('data-secret') : '••••••••••••';
            el.setAttribute('data-hidden', hidden ? 'false' : 'true');
            btn.querySelector('i').className = hidden ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

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
