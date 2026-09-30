/* Node.js app page: live build log, failure analysis, runtime logs, copy buttons and upload size check. */
(function () {
    'use strict';

    var getJSON = function (url) {
        return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) { throw new Error(data.error || ('Request failed (' + r.status + ')')); }
                return data;
            });
        });
    };
    var badge = function (state) {
        var map = { pending: ['warning', 'Queued'], running: ['info', 'Building'], completed: ['success', 'Live'], failed: ['danger', 'Failed'] };
        var m = map[state] || ['secondary', state];
        var s = document.createElement('span');
        s.className = 'badge rounded-pill text-bg-' + m[0] + ' badge-status';
        s.textContent = m[1];
        return s;
    };

    // Follow a running build.
    document.querySelectorAll('[data-build-poll]').forEach(function (pre) {
        var url = pre.getAttribute('data-build-poll');
        var from = 0;
        var started = false;
        var stateEl = document.querySelector('[data-build-state]');
        var note = document.querySelector('[data-build-note]');
        var tick = function () {
            getJSON(url + '?from=' + from).then(function (d) {
                if (d.logs) {
                    if (!started) { pre.textContent = ''; started = true; }
                    pre.textContent += (pre.textContent ? '\n' : '') + d.logs;
                    pre.scrollTop = pre.scrollHeight;
                }
                from = d.lines || from;
                if (stateEl) { stateEl.replaceChildren(badge(d.state)); }
                if (d.state === 'completed' || d.state === 'failed') {
                    if (note) {
                        note.className = 'small fw-medium ' + (d.state === 'completed' ? 'text-success' : 'text-danger');
                        note.textContent = d.state === 'completed' ? 'Deployed! Your app is live. Refreshing…' : 'The build failed. See the log above. Refreshing…';
                    }
                    setTimeout(function () { window.location.reload(); }, 2500);
                    return;
                }
                setTimeout(tick, 2500);
            }).catch(function (e) {
                if (note) { note.textContent = e.message + ' — retrying…'; }
                setTimeout(tick, 5000);
            });
        };
        tick();
    });

    // Why did a build fail?
    document.querySelectorAll('[data-analysis]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var out = btn.parentNode.querySelector('[data-analysis-out]');
            out.hidden = false;
            out.className = 'mt-2 small text-muted';
            out.textContent = 'Analysing the build log…';
            getJSON(btn.getAttribute('data-analysis')).then(function (d) {
                out.className = 'mt-2 small p-2 rounded bg-light';
                out.replaceChildren();
                if (!d.analysis) { out.textContent = 'No automatic explanation is available. Check the build log.'; return; }
                [['Why', d.analysis], ['How to fix', d.solution]].forEach(function (p) {
                    if (!p[1]) { return; }
                    var div = document.createElement('div');
                    var b = document.createElement('b');
                    b.textContent = p[0] + ': ';
                    div.appendChild(b);
                    div.appendChild(document.createTextNode(p[1]));
                    out.appendChild(div);
                });
            }).catch(function (e) { out.className = 'mt-2 small text-danger'; out.textContent = e.message; });
        });
    });

    // Full log of a past build.
    document.querySelectorAll('[data-build-log]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var out = btn.parentNode.querySelector('[data-analysis-out]');
            out.hidden = false;
            out.className = 'mt-2';
            out.textContent = 'Loading…';
            getJSON(btn.getAttribute('data-build-log') + '?from=0').then(function (d) {
                var pre = document.createElement('pre');
                pre.className = 'we-console we-console-sm';
                pre.textContent = d.logs || '(empty)';
                out.replaceChildren(pre);
            }).catch(function (e) { out.className = 'mt-2 small text-danger'; out.textContent = e.message; });
        });
    });

    // Runtime logs.
    document.querySelectorAll('[data-runtime-logs]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var out = document.querySelector('[data-logs-output]');
            var period = document.querySelector('[data-logs-period]');
            out.textContent = 'Loading…';
            getJSON(btn.getAttribute('data-runtime-logs') + '?period=' + encodeURIComponent(period ? period.value : '1d')).then(function (d) {
                out.replaceChildren();
                if (!d.logs.length) { out.textContent = 'Nothing logged in this period.'; return; }
                d.logs.forEach(function (l) {
                    var line = document.createElement('div');
                    var lvl = document.createElement('span');
                    lvl.className = 'we-log-' + l.level.toLowerCase();
                    lvl.textContent = l.level.padEnd(5) + ' ';
                    var t = document.createElement('span');
                    t.className = 'we-log-time';
                    t.textContent = (l.time || '').replace('T', ' ').replace(/\.\d+|Z$/g, '') + '  ';
                    line.appendChild(t);
                    line.appendChild(lvl);
                    line.appendChild(document.createTextNode(l.message));
                    out.appendChild(line);
                });
                out.scrollTop = out.scrollHeight;
            }).catch(function (e) { out.textContent = e.message; });
        });
    });

    // Copy buttons.
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = document.querySelector(btn.getAttribute('data-copy'));
            if (!el) { return; }
            el.select();
            (navigator.clipboard ? navigator.clipboard.writeText(el.value) : Promise.resolve(document.execCommand('copy'))).then(function () {
                var i = btn.querySelector('i');
                i.className = 'bi bi-clipboard-check';
                setTimeout(function () { i.className = 'bi bi-clipboard'; }, 1500);
            });
        });
    });

    // Refuse files over the limit before uploading them.
    document.querySelectorAll('input[type=file][data-max-bytes]').forEach(function (input) {
        input.form.addEventListener('submit', function (e) {
            var f = input.files && input.files[0];
            var max = parseInt(input.getAttribute('data-max-bytes'), 10);
            if (f && f.size > max) {
                e.preventDefault();
                e.stopImmediatePropagation();
                window.alert('This zip is ' + (f.size / 1048576).toFixed(1) + ' MB; the limit is ' + Math.round(max / 1048576) + ' MB. Leave out node_modules and build folders.');
            }
        }, true);
    });

    // Entry file is required for server frameworks.
    var fw = document.querySelector('[data-needs-entry]');
    if (fw) {
        var needs = fw.getAttribute('data-needs-entry').split(',');
        var mark = function () {
            var req = needs.indexOf(fw.value) !== -1;
            document.querySelectorAll('[data-entry-required]').forEach(function (s) { s.classList.toggle('d-none', !req); });
            var input = document.getElementById('entry_file');
            if (input) { input.required = req; }
        };
        fw.addEventListener('change', mark);
        mark();
    }
})();
