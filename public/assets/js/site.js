/* Public website: header on scroll, reveal animations and mobile menu. Loaded in <head>. */
(function () {
    'use strict';
    var root = document.documentElement;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduce && 'IntersectionObserver' in window) {
        root.classList.add('ws-anim');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var header = document.querySelector('.ws-header');
        var onScroll = function () {
            if (header) {
                header.classList.toggle('scrolled', window.scrollY > 8);
            }
            if (window.scrollY < 200) {
                document.querySelectorAll('.ws-nav .nav-link.active').forEach(function (a) { a.classList.remove('active'); });
            }
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        // Reveal sections as they scroll into view.
        var items = document.querySelectorAll('.reveal');
        if (root.classList.contains('ws-anim')) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) {
                        en.target.classList.add('in');
                        io.unobserve(en.target);
                    }
                });
            }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
            items.forEach(function (el) { io.observe(el); });
            // Safety net: never leave content hidden.
            setTimeout(function () { items.forEach(function (el) { el.classList.add('in'); }); }, 4000);
        }

        // Close the mobile menu after choosing a section.
        var nav = document.getElementById('wsNav');
        document.querySelectorAll('#wsNav .nav-link[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function () {
                if (nav && nav.classList.contains('show') && window.bootstrap) {
                    window.bootstrap.Collapse.getOrCreateInstance(nav).hide();
                }
            });
        });

        // Highlight the menu item for the section in view.
        var links = {};
        document.querySelectorAll('.ws-nav .nav-link[href^="#"]').forEach(function (a) { links[a.getAttribute('href').slice(1)] = a; });
        if ('IntersectionObserver' in window) {
            var spy = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    var a = links[en.target.id];
                    if (a && en.isIntersecting) {
                        Object.keys(links).forEach(function (k) { links[k].classList.remove('active'); });
                        a.classList.add('active');
                    }
                });
            }, { rootMargin: '-45% 0px -50% 0px' });
            Object.keys(links).forEach(function (id) {
                var s = document.getElementById(id);
                if (s) { spy.observe(s); }
            });
        }

        // Keep only one FAQ item open at a time.
        var faqs = document.querySelectorAll('.ws-faq details');
        faqs.forEach(function (d) {
            d.addEventListener('toggle', function () {
                if (d.open) {
                    faqs.forEach(function (o) { if (o !== d) { o.open = false; } });
                }
            });
        });
    });
})();
