@php
    /*
    |--------------------------------------------------------------------------
    | Table of Contents — frontend renderer
    |--------------------------------------------------------------------------
    | Presets and the anchor rule come from FalconCms\Core\Support\TocStyles. The
    | admin canvas receives the same arrays as JSON. See:
    |   resources/views/admin/falcon-builder/partials/components/elements/toc.blade.php
    |   resources/views/admin/falcon-builder/partials/scripts.blade.php  (fcToc* helpers)
    |
    | The list itself is built in the browser, and TocStyles explains at length why it
    | has to be: a table of contents lists the headings AROUND it, which live in
    | sibling elements this renderer never sees. The script below scans the finished
    | document, gives every heading an id using the same rule TocStyles::slug() uses in
    | PHP, and fills the empty <nav> this template ships.
    |
    | Nothing is drawn until it is filled, so a reader with no JavaScript gets no empty
    | box with a heading over it — they simply do not see the element.
    */
    use FalconCms\Core\Support\TocStyles;
    use FalconCms\Core\Support\Typography;

    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $preset = TocStyles::preset($s['preset'] ?? 'card');

    $g = function (string $key) use ($s, $preset) {
        $val = $s[$key] ?? null;

        return ($val === null || $val === '') ? ($preset[$key] ?? '') : $val;
    };

    $title = array_key_exists('title', $s) ? trim((string) $s['title']) : 'On this page';

    $minLevel = (int) ($s['minLevel'] ?? 2);
    $maxLevel = (int) ($s['maxLevel'] ?? 3);
    if (!in_array($minLevel, TocStyles::LEVELS, true)) { $minLevel = 2; }
    if (!in_array($maxLevel, TocStyles::LEVELS, true)) { $maxLevel = 3; }
    if ($maxLevel < $minLevel) { $maxLevel = $minLevel; }

    // Which part of the page to read headings from. Empty means "the content this
    // element is in", which the script works out by walking up from itself — the right
    // answer on a theme nobody has told it about.
    $scope   = TocStyles::safeSelector($s['scope'] ?? '');
    $exclude = TocStyles::safeSelector($s['exclude'] ?? '');

    $marker = $g('marker');                    // none | disc | decimal
    $guide  = (bool) $g('guide');
    $bg     = $g('bg');

    $collapsible = !empty($s['collapsible']);
    $openByDefault = ($s['openByDefault'] ?? true) !== false;
    $sticky = !empty($s['sticky']);
    $stickyTop = (int) ($s['stickyTop'] ?? 24);
    $maxHeight = (int) ($s['maxHeight'] ?? 0);
    $scrollSpy = ($s['scrollSpy'] ?? true) !== false;
    $smooth = ($s['smoothScroll'] ?? true) !== false;
    $scrollOffset = (int) ($s['scrollOffset'] ?? 80);
    $progress = !empty($s['progress']);
    $backToTop = !empty($s['backToTop']);
    $minHeadings = max(1, (int) ($s['minHeadings'] ?? 2));
    $numbered = $marker === 'decimal';

    // Typography, from the shared control every other element uses. Empty means
    // "leave it to the preset", so an element that has never been touched still follows
    // whichever preset is chosen. The unit rules live in Typography so that every
    // element applies them the same way.
    $typo = fn (string $prefix) => Typography::css($s, $prefix);
    $titleTypo = $typo('toc_title');
    $itemTypo  = $typo('toc_item');

    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');

    $uid    = 'fc-toc-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($el['id'] ?? uniqid('', false)));
    $elemId = !empty($s['cssId']) ? $s['cssId'] : null;

    $config = [
        'min' => $minLevel,
        'max' => $maxLevel,
        'scope' => $scope,
        'exclude' => $exclude,
        'spy' => $scrollSpy,
        'smooth' => $smooth,
        'offset' => $scrollOffset,
        'progress' => $progress,
        'minHeadings' => $minHeadings,
        'numbered' => $numbered,
    ];
@endphp

<div class="falcon-toc{{ $visibilityClasses }} {{ $s['cssClass'] ?? '' }}"
     @if($elemId) id="{{ $elemId }}" @endif
     style="width:100%;margin-top:{{ $marginTop }};margin-bottom:{{ $marginBottom }};">

    <style>
        {{-- Hidden until the script has something to show. A table of contents with no
             entries is not an empty list, it is not a table of contents — and a reader
             with JavaScript off should see nothing at all rather than a heading over a
             blank box. --}}
        #{{ $uid }}:not([data-ready]) { display: none; }
        #{{ $uid }} {
            --fc-toc-active: {{ $g('activeColor') }};
            display: block;
            background: {{ $bg }};
            border-radius: {{ (int) $g('radius') }}px;
            padding: {{ (int) $g('padY') }}px {{ (int) $g('padX') }}px;
            @if((int) $g('borderWidth') > 0) border: {{ (int) $g('borderWidth') }}px solid {{ $g('borderColor') }}; @endif
            @if($sticky) position: sticky; top: {{ $stickyTop }}px; @endif
            @if($maxHeight > 0) max-height: {{ $maxHeight }}px; overflow-y: auto; @endif
        }
        #{{ $uid }} > summary::-webkit-details-marker { display: none; }
        #{{ $uid }} > summary { list-style: none; }
        #{{ $uid }} .fc-toc-head {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 10px;
        }
        #{{ $uid }} .fc-toc-title {
            margin: 0;
            color: {{ $g('titleColor') }};
            font-size: {{ $g('titleSize') }}px;
            font-weight: {{ $g('titleWeight') }};
            letter-spacing: .02em;
            text-transform: {{ in_array($s['preset'] ?? 'card', ['sidebar', 'minimal'], true) ? 'uppercase' : 'none' }};
            @if($titleTypo) {{ $titleTypo }} @endif
        }
        #{{ $uid }} .fc-toc-count {
            font-size: 11px; font-weight: 600;
            color: {{ $g('linkColor') }}; opacity: .6;
        }
        @if($collapsible)
        #{{ $uid }} > summary { cursor: pointer; }
        #{{ $uid }} .fc-toc-chev { margin-left: auto; opacity: .5; font-size: 11px; transition: transform .18s ease; }
        #{{ $uid }}[open] .fc-toc-chev { transform: rotate(90deg); }
        @endif

        @if($progress)
        #{{ $uid }} .fc-toc-progress {
            height: 3px; border-radius: 3px;
            background: {{ $g('borderColor') }};
            margin: 0 0 12px;
            overflow: hidden;
        }
        #{{ $uid }} .fc-toc-progress > span {
            display: block; height: 100%; width: 0;
            background: var(--fc-toc-active);
            transition: width .12s linear;
        }
        @endif

        #{{ $uid }} .fc-toc-list, #{{ $uid }} .fc-toc-list ul {
            list-style: {{ $marker === 'none' ? 'none' : $marker }};
            margin: 0;
            padding-left: {{ $marker === 'none' ? '0' : '1.15em' }};
        }
        #{{ $uid }} .fc-toc-list li { margin: 0; }
        #{{ $uid }} .fc-toc-list ul {
            padding-left: {{ $marker === 'none' ? (int) $g('indent').'px' : '1.15em' }};
            @if($guide && $marker === 'none') border-left: 1px solid {{ $g('borderColor') }}; margin-left: 3px; padding-left: {{ max(8, (int) $g('indent') - 3) }}px; @endif
        }
        #{{ $uid }} .fc-toc-list a {
            display: block;
            padding: {{ max(1, (int) round(((float) $g('itemGap')) / 2)) }}px 0;
            color: {{ $g('linkColor') }};
            font-size: {{ $g('fontSize') }}px;
            line-height: 1.45;
            text-decoration: none;
            transition: color .15s ease;
            @if($itemTypo) {{ $itemTypo }} @endif
        }
        #{{ $uid }} .fc-toc-list a:hover { color: {{ $g('hoverColor') }}; }
        {{-- Written after the hover rule so the section being read still reads as
             active while the pointer is elsewhere in the list. --}}
        #{{ $uid }} .fc-toc-list a.is-active { color: var(--fc-toc-active); font-weight: 600; }
        @if($guide && $marker === 'none')
        #{{ $uid }} .fc-toc-list ul a.is-active { box-shadow: inset 2px 0 0 var(--fc-toc-active); padding-left: 8px; margin-left: -{{ max(8, (int) $g('indent') - 3) }}px; padding-left: {{ max(8, (int) $g('indent') - 3) }}px; }
        @endif
        @if($numbered)
        #{{ $uid }} .fc-toc-list { counter-reset: fctoc; }
        @endif
        #{{ $uid }} .fc-toc-top {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 12px;
            color: {{ $g('linkColor') }};
            font-size: {{ max(11, (float) $g('fontSize') - 1.5) }}px;
            text-decoration: none;
            opacity: .75;
        }
        #{{ $uid }} .fc-toc-top:hover { opacity: 1; color: var(--fc-toc-active); }

        @media (prefers-reduced-motion: reduce) {
            #{{ $uid }} .fc-toc-list a, #{{ $uid }} .fc-toc-chev, #{{ $uid }} .fc-toc-progress > span { transition: none; }
        }
    </style>

    @php $tag = $collapsible ? 'details' : 'nav'; @endphp

    <{{ $tag }} id="{{ $uid }}" class="fc-toc"
        data-fc-toc="{{ json_encode($config, JSON_UNESCAPED_SLASHES) }}"
        @if($collapsible && $openByDefault) open @endif
        @if(!$collapsible) aria-label="{{ $title !== '' ? $title : 'Table of contents' }}" @endif>

        @if($title !== '')
            @if($collapsible)
            <summary class="fc-toc-head">
                <span class="fc-toc-title">{{ $title }}</span>
                <span class="fc-toc-count" data-fc-toc-count></span>
                <i class="fc-toc-chev fas fa-chevron-right" aria-hidden="true"></i>
            </summary>
            @else
            <div class="fc-toc-head">
                <p class="fc-toc-title">{{ $title }}</p>
                <span class="fc-toc-count" data-fc-toc-count></span>
            </div>
            @endif
        @endif

        @if($progress)
        <div class="fc-toc-progress" aria-hidden="true"><span data-fc-toc-bar></span></div>
        @endif

        <ul class="fc-toc-list" data-fc-toc-list></ul>

        @if($backToTop)
        <a class="fc-toc-top" href="#" data-fc-toc-top><i class="fas fa-arrow-up" aria-hidden="true"></i> Back to top</a>
        @endif
    </{{ $tag }}>
</div>

{{-- One script for every table of contents on the page, guarded by a window flag
     rather than @once.

     @once is not reliable here: the theme layout renders builder content twice per
     request — once to scan it for icon libraries — so the directive has already fired
     by the time the visible pass runs, and the script never reaches the page. The Code
     Block element hit exactly this. A window flag is checked at run time by the
     browser, which cannot be fooled by a template being rendered twice. --}}
<script>
(function () {
    if (window.__falconToc) return;
    window.__falconToc = true;

    // The same rule as TocStyles::slug(). If these two disagree the links point at
    // nothing, so PHP's version and this one are checked against each other in TocTest.
    function slug(text) {
        var s = String(text == null ? '' : text).trim().toLowerCase();
        try {
            s = s.replace(/[^\p{L}\p{N}\p{M}]+/gu, '-');
        } catch (e) {
            // A browser without Unicode property escapes. ASCII still slugs correctly,
            // which is the case the fallback is for.
            s = s.replace(/[^a-z0-9]+/g, '-');
        }
        return s.replace(/^-+|-+$/g, '');
    }

    function anchorId(s, seen, index) {
        var base = s === '' ? 'section-' + (index + 1) : 'h-' + s;
        return seen > 0 ? base + '-' + (seen + 1) : base;
    }

    // Where to read headings from, in the order worth trying.
    //
    // An explicit selector is an instruction, so if it matches something that is the
    // only candidate — an author who scoped the list to one region meant it, and
    // quietly widening past an empty region would list headings they had excluded on
    // purpose. A selector matching nothing at all is a typo, and falls through.
    //
    // Otherwise the element walks up from itself to the nearest thing that looks like
    // the page's content. Every step up is kept rather than only the first, because the
    // nearest match is not always the right one: a table of contents in its own column
    // beside the article, or in a row above it, has an ancestor that contains the list
    // and none of the headings. Taking the first and stopping there is how the element
    // renders nothing on exactly the layout it is most often used in. build() walks
    // this list until one of them actually holds enough headings.
    function scopeCandidates(nav, cfg) {
        if (cfg.scope) {
            var picked = document.querySelector(cfg.scope);
            if (picked) return [picked];
        }

        var out = [];
        ['article', 'main', '.falcon-builder-content', '.entry-content', '.post-content', '.content'].forEach(function (sel) {
            var found = nav.closest(sel);
            if (found && out.indexOf(found) === -1) out.push(found);
        });
        if (document.body && out.indexOf(document.body) === -1) out.push(document.body);

        return out;
    }

    function build(nav) {
        var cfg;
        try { cfg = JSON.parse(nav.getAttribute('data-fc-toc') || '{}'); } catch (e) { cfg = {}; }

        var list = nav.querySelector('[data-fc-toc-list]');
        if (!list) return;

        var min = cfg.min || 2, max = cfg.max || 3;
        var sel = [];
        for (var lv = min; lv <= max; lv++) sel.push('h' + lv);

        // Any table of contents' own entries are headings too on some themes, and one
        // that lists itself is a bug the reader sees.
        var usable = function (h) {
            if (nav.contains(h) || h.closest('.falcon-toc')) return false;
            if (cfg.exclude) {
                try { if (h.closest(cfg.exclude) || h.matches(cfg.exclude)) return false; } catch (e) {}
            }
            return (h.textContent || '').trim() !== '';
        };

        var need = cfg.minHeadings || 1;
        var candidates = scopeCandidates(nav, cfg);
        var scope = null;
        var headings = [];

        for (var c = 0; c < candidates.length; c++) {
            var found = Array.prototype.slice.call(candidates[c].querySelectorAll(sel.join(','))).filter(usable);
            if (found.length >= need) { scope = candidates[c]; headings = found; break; }
        }

        if (!scope) return;

        var seen = {};
        var items = headings.map(function (h, i) {
            var text = (h.textContent || '').trim();
            var s = slug(text);
            var count = seen[s] || 0;
            seen[s] = count + 1;

            // A heading that already has an id keeps it: it may be linked to from
            // elsewhere, and taking it away would break those links.
            if (!h.id) h.id = anchorId(s, count, i);

            return { el: h, id: h.id, text: text, level: parseInt(h.tagName.substring(1), 10) };
        });

        // Nest by level. A jump from h2 straight to h4 is common in real documents, so
        // depth is tracked against the levels actually seen rather than assumed to step
        // by one; otherwise one skipped level throws the rest of the list out.
        var stack = [{ level: min - 1, ul: list }];
        items.forEach(function (item) {
            while (stack.length > 1 && item.level <= stack[stack.length - 1].level) stack.pop();

            var parent = stack[stack.length - 1];
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.href = '#' + item.id;
            a.textContent = item.text;
            a.setAttribute('data-fc-toc-link', item.id);
            li.appendChild(a);
            parent.ul.appendChild(li);

            var child = document.createElement('ul');
            li.appendChild(child);
            stack.push({ level: item.level, ul: child, li: li });
        });

        // Empty nested lists are left behind by every leaf item above.
        Array.prototype.slice.call(list.querySelectorAll('ul')).forEach(function (ul) {
            if (!ul.children.length) ul.parentNode.removeChild(ul);
        });

        var count = nav.querySelector('[data-fc-toc-count]');
        if (count) count.textContent = items.length;

        nav.setAttribute('data-ready', '');

        wireScroll(nav, cfg);
        wireScrollState(nav, items, scope, cfg);
    }

    // Smooth scrolling with an offset, because a sticky site header would otherwise
    // cover the heading the reader just asked for. The URL is updated without a jump so
    // the link is still copyable and the back button still works.
    function wireScroll(nav, cfg) {
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        nav.addEventListener('click', function (ev) {
            var a = ev.target.closest ? ev.target.closest('a[href^="#"]') : null;
            if (!a || !nav.contains(a)) return;

            var top = a.hasAttribute('data-fc-toc-top');
            var target = top ? null : document.getElementById(a.getAttribute('href').slice(1));
            if (!top && !target) return;

            ev.preventDefault();
            var y = top ? 0 : target.getBoundingClientRect().top + window.pageYOffset - (cfg.offset || 0);
            window.scrollTo({ top: y < 0 ? 0 : y, behavior: (cfg.smooth && !reduced) ? 'smooth' : 'auto' });

            if (!top && window.history && history.replaceState) {
                history.replaceState(null, '', a.getAttribute('href'));
            }
        });
    }

    // Which section is being read, and how far through it the reader is.
    //
    // Both from one scroll handler, throttled to a frame. This was an
    // IntersectionObserver watching a band a little way down the viewport, which is the
    // cheaper design and the wrong one: a heading only counts while it is inside the
    // band, so the moment a reader clicked a link — which puts that heading at the very
    // top, above the band — the section they had just jumped to was not the highlighted
    // one, and at the top of a page the highlight sat on the SECOND heading because the
    // first was above the band too. Nothing was highlighted at all while the reader was
    // in the middle of a section longer than the band.
    //
    // Position answers the question directly: the current section is the last heading
    // the reader has scrolled past, which is true whether they got there by scrolling,
    // by clicking a link, or by loading the page on an anchor. The cost is a loop over
    // the headings once per frame of scrolling, which for a page with tens of them is
    // nothing.
    function wireScrollState(nav, items, scope, cfg) {
        var links = {};
        items.forEach(function (it) {
            var a = nav.querySelector('[data-fc-toc-link="' + (window.CSS && CSS.escape ? CSS.escape(it.id) : it.id) + '"]');
            if (a) links[it.id] = a;
        });

        var bar = cfg.progress ? nav.querySelector('[data-fc-toc-bar]') : null;
        var spy = !!cfg.spy;
        if (!spy && !bar) return;

        // A heading counts as reached once its top is at or above the line the reader
        // lands on when they click a link, so a click and a scroll agree about where
        // they are. The few pixels of slack absorb fractional scroll positions.
        var line = function () { return (cfg.offset || 0) + 8; };

        var ticking = false;

        var update = function () {
            ticking = false;

            if (spy) {
                var current = items.length ? items[0].id : null;
                for (var i = 0; i < items.length; i++) {
                    if (items[i].el.getBoundingClientRect().top <= line()) {
                        current = items[i].id;
                    } else {
                        break;
                    }
                }

                // Past the end of the content there is no next section, so the last one
                // stays lit rather than the list going blank at the foot of the page.
                for (var id in links) {
                    if (Object.prototype.hasOwnProperty.call(links, id)) {
                        links[id].classList.toggle('is-active', id === current);
                    }
                }
            }

            if (bar) {
                var box = scope.getBoundingClientRect();
                var total = box.height - window.innerHeight;
                var done = total <= 0 ? 1 : (-box.top) / total;
                bar.style.width = Math.max(0, Math.min(1, done)) * 100 + '%';
            }
        };

        // Throttled with a timer rather than a frame. requestAnimationFrame is the usual
        // choice and is the better one while the browser is painting — but it does not
        // run where frames are not produced, which is a background tab, a reader who has
        // switched away mid-article, and every headless browser this is tested in. A
        // sixteen-millisecond timer is a frame's worth of throttling that runs wherever
        // the scroll event itself does, which is the whole point of listening for it.
        var onScroll = function () {
            if (ticking) return;
            ticking = true;
            setTimeout(update, 16);
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        update();
    }

    function boot() {
        Array.prototype.slice.call(document.querySelectorAll('.fc-toc[data-fc-toc]')).forEach(function (nav) {
            if (nav.hasAttribute('data-ready')) return;
            try { build(nav); } catch (e) { /* one broken table of contents must not stop the others */ }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
