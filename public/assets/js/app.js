/* WebEdge control panel behaviour (no inline scripts: CSP-friendly). */
(function () {
    'use strict';

    // Confirm destructive actions: <form data-confirm="Are you sure?">
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg = (e.submitter && e.submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
            return;
        }
        // Full-screen loading overlay for long actions: <form data-loading="Title" data-loading-text="Details">
        var loadingEl = e.submitter && e.submitter.hasAttribute('data-loading') ? e.submitter : (form.hasAttribute('data-loading') ? form : null);
        if (loadingEl) {
            showLoading(loadingEl.getAttribute('data-loading'), loadingEl.getAttribute('data-loading-text') || '');
        }
        // Prevent double submission.
        var btns = form.querySelectorAll('button[type=submit], button:not([type])');
        window.setTimeout(function () {
            btns.forEach(function (b) { b.disabled = true; });
        }, 0);
    });

    function showLoading(title, text) {
        if (document.getElementById('we-loading')) return;
        var steps = title.indexOf('Sync') === 0 ? ['Connecting to the provider', 'Fetching domains and websites', 'Fetching databases and email', 'Adding everything to the panel', 'Almost done'] : ['Working', 'Almost done'];
        var box = document.createElement('div');
        box.id = 'we-loading';
        box.className = 'we-loading';
        box.setAttribute('role', 'alert');
        box.setAttribute('aria-live', 'assertive');
        box.innerHTML = '<div class="we-loading-card"><div class="we-loading-spinner" aria-hidden="true"></div>' +
            '<div class="h5 mb-1 we-loading-title"></div><div class="small text-muted mb-3 we-loading-text"></div>' +
            '<div class="progress mb-2" style="height:6px"><div class="progress-bar progress-bar-striped progress-bar-animated we-loading-bar" style="width:5%"></div></div>' +
            '<div class="small fw-medium we-loading-step"></div><div class="small text-muted we-loading-time">0s</div></div>';
        box.querySelector('.we-loading-title').textContent = title;
        box.querySelector('.we-loading-text').textContent = text;
        document.body.appendChild(box);
        document.body.classList.add('we-loading-open');
        var started = Date.now(), bar = box.querySelector('.we-loading-bar'), step = box.querySelector('.we-loading-step'), time = box.querySelector('.we-loading-time');
        var tick = function () {
            var s = Math.floor((Date.now() - started) / 1000);
            // The server does not report progress, so ease towards 95% and let the page load finish it.
            bar.style.width = Math.min(95, 5 + 90 * (1 - Math.exp(-s / 25))) + '%';
            step.textContent = steps[Math.min(steps.length - 1, Math.floor(s / 6))] + '…';
            time.textContent = s < 60 ? s + 's' : Math.floor(s / 60) + 'm ' + (s % 60) + 's';
        };
        tick();
        window.setInterval(tick, 500);
    }
    // Returning with the back button restores the page from cache: drop a stale overlay.
    window.addEventListener('pageshow', function (e) {
        var el = document.getElementById('we-loading');
        if (e.persisted && el) { el.remove(); document.body.classList.remove('we-loading-open'); }
    });

    // Admin user form: show the domain picker for "Only selected domains"; filter long lists.
    document.querySelectorAll('[data-scope]').forEach(function (r) {
        r.addEventListener('change', function () {
            var picker = document.getElementById('domainPicker');
            if (picker) picker.hidden = !document.getElementById('ds_sel').checked;
        });
    });
    document.querySelectorAll('[data-filter]').forEach(function (input) {
        input.addEventListener('input', function () {
            var q = input.value.toLowerCase();
            document.querySelectorAll(input.getAttribute('data-filter')).forEach(function (el) {
                el.classList.toggle('d-none', q !== '' && el.textContent.toLowerCase().indexOf(q) === -1);
            });
        });
    });

    // Print buttons.
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    // Select / clear all visible checkboxes in a list: <button data-check-all="#list label">, count: <b data-check-count="#list">.
    var countChecks = function () {
        document.querySelectorAll('[data-check-count]').forEach(function (c) {
            c.textContent = document.querySelectorAll(c.getAttribute('data-check-count') + ' input[type=checkbox]:checked').length;
        });
    };
    ['data-check-all', 'data-check-none'].forEach(function (attr) {
        document.querySelectorAll('[' + attr + ']').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll(btn.getAttribute(attr)).forEach(function (el) {
                    if (el.hidden || el.classList.contains('d-none')) { return; }
                    el.querySelectorAll('input[type=checkbox]').forEach(function (cb) { cb.checked = attr === 'data-check-all'; });
                });
                countChecks();
            });
        });
    });
    document.addEventListener('change', countChecks);
    countChecks();

    // Checkbox selections with a bulk action bar: <form id="x" data-bulk-bar>, rows <input data-bulk-item form="x">,
    // header <input data-bulk-all="x">. The bar shows the count and only appears while something is selected.
    document.querySelectorAll('[data-bulk-bar]').forEach(function (bar) {
        var id = bar.id;
        var items = function () {
            return Array.prototype.filter.call(document.querySelectorAll('[data-bulk-item]'), function (el) {
                return el.form === bar;
            });
        };
        var alls = document.querySelectorAll('[data-bulk-all="' + id + '"]');
        var refresh = function () {
            var list = items();
            var n = list.filter(function (el) { return el.checked; }).length;
            bar.querySelectorAll('[data-bulk-count]').forEach(function (c) { c.textContent = n; });
            bar.classList.toggle('show', n > 0);
            bar.querySelectorAll('button[name="do"], button[data-bulk-needs]').forEach(function (b) { b.disabled = n === 0; });
            alls.forEach(function (a) {
                a.checked = n > 0 && n === list.length;
                a.indeterminate = n > 0 && n < list.length;
            });
        };
        alls.forEach(function (a) {
            a.addEventListener('change', function () {
                items().forEach(function (el) { el.checked = a.checked; });
                refresh();
            });
        });
        document.addEventListener('change', function (e) {
            if (e.target.hasAttribute && e.target.hasAttribute('data-bulk-item')) {
                refresh();
            }
        });
        // "All" for one group inside the bar: <input data-bulk-group="domains">, items carry data-group="domains".
        bar.querySelectorAll('[data-bulk-group]').forEach(function (g) {
            g.addEventListener('change', function () {
                items().forEach(function (el) {
                    if (el.getAttribute('data-group') === g.getAttribute('data-bulk-group')) { el.checked = g.checked; }
                });
                refresh();
            });
        });
        bar.querySelectorAll('[data-bulk-clear]').forEach(function (b) {
            b.addEventListener('click', function () {
                items().forEach(function (el) { el.checked = false; });
                refresh();
            });
        });
        refresh();
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


    // Row-action modals: the button that opens a modal can set the form action
    // (data-action), input values (data-set-<name>) and labels (data-text-<key>).
    document.addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        if (!btn || !btn.dataset) return;
        var form = e.target.querySelector('form') || (e.target.querySelector('.modal-content') && e.target.querySelector('.modal-content').tagName === 'FORM' ? e.target.querySelector('.modal-content') : null);
        if (form && btn.dataset.action) form.setAttribute('action', btn.dataset.action);
        Object.keys(btn.dataset).forEach(function (k) {
            var v = btn.dataset[k];
            if (k.indexOf('set') === 0 && k.length > 3) {
                var name = k.slice(3).replace(/[A-Z]/g, function (c) { return '_' + c.toLowerCase(); }).replace(/^_/, '');
                var input = e.target.querySelector('[name="' + name + '"]');
                if (input) input.value = v;
            } else if (k.indexOf('text') === 0 && k.length > 4) {
                var key = k.slice(4).toLowerCase();
                e.target.querySelectorAll('[data-text="' + key + '"]').forEach(function (el) { el.textContent = v; });
            }
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
