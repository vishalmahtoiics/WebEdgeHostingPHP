/* Blog editor: Markdown toolbar, slug from title, counters and a live Google preview. */
(function () {
    'use strict';
    var form = document.querySelector('[data-blog-editor]');
    if (!form) { return; }
    var body = form.querySelector('#body');
    var $ = function (id) { return form.querySelector('#' + id); };
    var slugify = function (s) { return s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120); };

    var replaceSel = function (fn) {
        var s = body.selectionStart, e = body.selectionEnd;
        var r = fn(body.value.slice(s, e));
        body.setRangeText(r.text, s, e, 'end');
        if (r.select) { body.setSelectionRange(s + r.select[0], s + r.select[1]); }
        body.focus();
        body.dispatchEvent(new Event('input'));
    };
    form.querySelectorAll('[data-md]').forEach(function (b) {
        b.addEventListener('click', function () {
            var prefix = b.getAttribute('data-md');
            replaceSel(function (sel) {
                var lines = (sel || 'Text').split('\n').map(function (l, i) { return (prefix === '1. ' ? (i + 1) + '. ' : prefix) + l; });
                var start = body.selectionStart > 0 && body.value[body.selectionStart - 1] !== '\n' ? '\n' : '';
                return { text: start + lines.join('\n') };
            });
        });
    });
    form.querySelectorAll('[data-md-wrap]').forEach(function (b) {
        b.addEventListener('click', function () {
            var w = b.getAttribute('data-md-wrap');
            replaceSel(function (sel) { var t = sel || 'text'; return { text: w + t + w, select: [w.length, w.length + t.length] }; });
        });
    });
    form.querySelectorAll('[data-md-link]').forEach(function (b) {
        b.addEventListener('click', function () {
            var url = window.prompt('Link address (e.g. /services/seo or https://…)', '/services/');
            if (!url) { return; }
            replaceSel(function (sel) { var t = sel || 'link text'; return { text: '[' + t + '](' + url + ')', select: [1, 1 + t.length] }; });
        });
    });

    var title = $('title'), slug = $('slug'), mt = $('meta_title'), md = $('meta_description'), ex = $('excerpt');
    var auto = slug.getAttribute('data-slug-auto') === '1' && slug.value === '';
    slug.addEventListener('input', function () { auto = false; });
    var update = function () {
        if (auto) { slug.value = slugify(title.value); }
        form.querySelectorAll('[data-count-for]').forEach(function (c) {
            var el = $(c.getAttribute('data-count-for'));
            var n = el.value.length;
            var max = c.getAttribute('data-count-for') === 'meta_title' ? 65 : 165;
            c.textContent = n;
            c.parentNode.className = 'small ' + (n === 0 ? 'text-muted' : (n > max ? 'text-danger' : 'text-success'));
        });
        var words = body.value.replace(/[#>*_`|\[\]()\-]/g, ' ').trim().split(/\s+/).filter(Boolean).length;
        form.querySelectorAll('[data-word-count]').forEach(function (w) { w.textContent = words; });
        var s = form.querySelector('[data-serp-slug]'); if (s) { s.textContent = slug.value; }
        var t = form.querySelector('[data-serp-title]'); if (t) { t.textContent = (mt.value || title.value || 'Your title').slice(0, 65); }
        var d = form.querySelector('[data-serp-desc]'); if (d) { d.textContent = (md.value || ex.value || 'Your meta description').slice(0, 165); }
    };
    [title, slug, mt, md, ex, body].forEach(function (el) { el.addEventListener('input', update); });
    update();
})();
