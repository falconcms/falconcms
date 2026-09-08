{{--
    Landing-page section menus: smooth scrolling, and marking the item you are looking at.

    A menu built for a landing page is mostly section links — #pricing, #features — and for
    those "the current page" is the wrong question: every one of them points at the page you
    are already on. Answering it on the server is what marked the whole menu active at once,
    so anchor items are never active server-side. Which one is current is a property of where
    the reader has scrolled to, and only the browser knows that.

    Ordinary links to other pages are left completely alone — this only ever touches links
    that point at a section of THIS page, so a genuinely-current page item keeps the mark the
    server gave it.

    Two menus can be on one page (the theme header and a Layout builder header), and they do
    not agree on what "active" looks like: the builder styles `.falcon-menu-link.active`, the
    theme uses Tailwind's `text-primary`. Each container names its own class in
    `data-falcon-scrollspy`, and the script is written once and guards against running twice.

    This partial is included from inside the header, which is the top of the document, so
    everything waits for DOMContentLoaded: at the moment the tag is parsed the sections it
    needs to find are still further down the page and do not exist yet.
--}}
<script>
(function () {
    if (window.__falconMenuScrollSpy) return;
    window.__falconMenuScrollSpy = true;

    var norm = function (p) { return p.replace(/\/+$/, '') || '/'; };

    /**
     * How much of the top of the page is covered by a sticky header.
     *
     * Used twice: a section is "current" once it reaches the bottom edge of the header
     * rather than the top of the window, and scrolling to a section has to stop that far
     * short or the heading lands underneath the header and cannot be read.
     */
    function headerOffset() {
        var h = 0;
        document.querySelectorAll('.main-header, .falcon-builder-header, [data-falcon-sticky-header]').forEach(function (el) {
            var pos = window.getComputedStyle(el).position;
            if (pos === 'sticky' || pos === 'fixed') h = Math.max(h, el.getBoundingClientRect().height);
        });
        return h;
    }

    function targetOf(a) {
        var url;
        try { url = new URL(a.getAttribute('href'), window.location.href); } catch (e) { return null; }

        // Only a link to a section of this very page. Another origin or another path means
        // normal navigation, not a section link.
        if (url.origin !== window.location.origin) return null;
        if (norm(url.pathname) !== norm(window.location.pathname)) return null;
        if (url.hash.length < 2) return null;

        var raw = url.hash.slice(1);
        try { raw = decodeURIComponent(raw); } catch (e) { /* keep it as written */ }

        // Trimmed as well as exact: a menu URL saved as "# agencies" carries a stray space
        // that no element id will ever have, and the link is dead in the browser too. Being
        // forgiving here makes such an item work rather than silently do nothing.
        return document.getElementById(raw) || document.getElementById(raw.trim());
    }

    function start() {
        var groups = [];

        document.querySelectorAll('[data-falcon-scrollspy]').forEach(function (container) {
            var cls = (container.getAttribute('data-falcon-scrollspy') || '').trim();
            if (!cls) return;

            var items = [];
            // Links to this page WITHOUT a fragment — the "Home" item of a landing-page
            // menu. The server marks that one current, correctly, because you are on it.
            // But once a section is reached, leaving it lit means two items are current at
            // once. It steps aside while a section is active and comes back at the top of
            // the page. Only ever this link, and only if the server lit it.
            var pageLinks = [];

            container.querySelectorAll('a[href]').forEach(function (a) {
                var target = targetOf(a);
                if (target) { items.push({ link: a, target: target }); return; }

                var url;
                try { url = new URL(a.getAttribute('href'), window.location.href); } catch (e) { return; }
                if (url.origin === window.location.origin
                    && norm(url.pathname) === norm(window.location.pathname)
                    && url.hash.length < 2
                    && a.classList.contains(cls)) {
                    pageLinks.push(a);
                }
            });

            if (items.length) groups.push({ cls: cls, items: items, pageLinks: pageLinks });
        });

        if (!groups.length) return;

        function mark(group, chosen) {
            group.items.forEach(function (item) {
                item.link.classList.toggle(group.cls, item.link === chosen);
            });
            group.pageLinks.forEach(function (a) {
                a.classList.toggle(group.cls, !chosen);
            });
        }

        function update() {
            var at = window.scrollY + headerOffset() + 8;
            var atBottom = (window.innerHeight + window.scrollY) >= (document.documentElement.scrollHeight - 2);

            groups.forEach(function (group) {
                // Ordered by where the sections actually sit, not by their order in the menu.
                var sorted = group.items.slice().sort(function (a, b) {
                    return a.target.getBoundingClientRect().top - b.target.getBoundingClientRect().top;
                });

                var chosen = null;
                if (atBottom) {
                    // The last section can be shorter than the viewport, so scrolling to the
                    // end may never bring its top past the line. Reaching the bottom means it
                    // is the one being read.
                    chosen = sorted[sorted.length - 1].link;
                } else {
                    sorted.forEach(function (item) {
                        if (item.target.getBoundingClientRect().top + window.scrollY <= at) chosen = item.link;
                    });
                }

                mark(group, chosen);
            });
        }

        /**
         * Tell the browser how far a sticky header reaches, so its own scrolling stops short
         * of it. Doing this instead of correcting the position afterwards is what makes
         * arriving on /#features land correctly: the browser scrolls to a fragment before
         * any of this runs, and a correction after the fact is a fight it wins as often as
         * not. scroll-margin-top is the same number, stated where the browser will use it.
         */
        function applyScrollMargin() {
            var h = headerOffset();
            groups.forEach(function (group) {
                group.items.forEach(function (item) {
                    item.target.style.scrollMarginTop = h + 'px';
                });
            });
        }

        function scrollToTarget(target, smooth) {
            target.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
        }

        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        groups.forEach(function (group) {
            group.items.forEach(function (item) {
                item.link.addEventListener('click', function (e) {
                    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

                    // Scrolled here rather than left to the browser. The default jump is
                    // instant, and it puts the section's top at the top of the window, which
                    // a sticky header is already covering.
                    e.preventDefault();
                    scrollToTarget(item.target, !reduced);

                    // The address bar should still say which section this is, without the
                    // jump that assigning location.hash would cause.
                    var hash = '#' + item.target.id;
                    if (window.history && window.history.pushState) window.history.pushState(null, '', hash);

                    // Marked at once: the scroll is smooth and takes a moment, and a menu
                    // that only catches up when it stops feels broken.
                    mark(group, item.link);
                });
            });
        });

        var queued = false;
        function schedule() {
            if (queued) return;
            queued = true;
            window.requestAnimationFrame(function () { queued = false; update(); });
        }

        applyScrollMargin();

        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', function () { applyScrollMargin(); schedule(); });
        window.addEventListener('hashchange', schedule);
        // Sections move as images and fonts finish loading, so the first answer is re-checked.
        window.addEventListener('load', schedule);

        // Arriving on /#features: the browser has already jumped, with the section's top
        // under the sticky header. Put it where a click would have put it, without animating
        // a position the reader is already looking at.
        if (window.location.hash.length > 1) {
            var landed = null;
            groups.forEach(function (group) {
                group.items.forEach(function (item) {
                    if ('#' + item.target.id === window.location.hash) landed = item.target;
                });
            });
            if (landed) {
                // The browser has already jumped, before scroll-margin-top existed on the
                // element. Repeat the jump now that it does, and again once images and fonts
                // have finished moving things around.
                scrollToTarget(landed, false);
                window.addEventListener('load', function () {
                    scrollToTarget(landed, false);
                    update();
                });
            }
        }

        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>
