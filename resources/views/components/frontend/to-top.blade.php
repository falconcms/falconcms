@php
    /*
    |--------------------------------------------------------------------------
    | Back to top
    |--------------------------------------------------------------------------
    | Rendered on the falcon_footer action rather than baked into a template, so it
    | reaches every theme — parent, child, generated or hand-written — that calls the
    | hook, and a theme update can never drop it. Settings live in Customizer →
    | Performance.
    |
    | Off means nothing at all reaches the page: no markup, no stylesheet and no
    | script, rather than a hidden element still costing a request's worth of bytes.
    */
    $enabled = get_cms_option('to_top_enabled', '1') === '1';
@endphp

@if($enabled)
@php
    $after   = max(0, (int) get_cms_option('to_top_offset', 300));
    $side    = get_cms_option('to_top_position', 'right') === 'left' ? 'left' : 'right';
    $gap     = max(0, (int) get_cms_option('to_top_side_gap', 24));
    $size    = max(24, (int) get_cms_option('to_top_size', 44));
    // Stored as a percentage of the button, so a circle stays a circle at any size.
    $radius  = max(0, min(50, (int) get_cms_option('to_top_radius', 50)));
    $icon    = get_cms_option('to_top_icon', 'arrow');

    // Empty means "the theme's own colour", which is what a site that has never opened
    // these settings should get — not a colour we picked for it.
    $bg      = trim((string) get_cms_option('to_top_bg_color', '')) ?: 'var(--primary-color, #2271b1)';
    $fg      = trim((string) get_cms_option('to_top_icon_color', '')) ?: '#ffffff';
    $hoverBg = trim((string) get_cms_option('to_top_hover_bg_color', ''));

    $progress   = get_cms_option('to_top_show_progress', '0') === '1';
    $hideMobile = get_cms_option('to_top_hide_mobile', '0') === '1';
@endphp
<style>
    #fc-to-top {
        position: fixed;
        bottom: {{ $gap }}px;
        {{ $side }}: {{ $gap }}px;
        z-index: 998;
        width: {{ $size }}px;
        height: {{ $size }}px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 0;
        cursor: pointer;
        border-radius: {{ $radius }}%;
        background: {{ $bg }};
        color: {{ $fg }};
        box-shadow: 0 4px 14px rgba(16, 24, 40, .18);
        /* Out of the way until it is wanted: not just invisible but unclickable, so it
           cannot swallow a tap on whatever is underneath it. */
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: opacity .2s ease, transform .2s ease, visibility .2s, background-color .2s ease;
    }
    #fc-to-top.is-visible { opacity: 1; visibility: visible; transform: none; }
    #fc-to-top:hover { background: {{ $hoverBg ?: 'rgba(0,0,0,.85)' }}; }
    #fc-to-top:focus-visible { outline: 2px solid {{ $fg }}; outline-offset: 3px; }
    #fc-to-top svg { width: {{ max(12, (int) round($size * 0.42)) }}px; height: {{ max(12, (int) round($size * 0.42)) }}px; display: block; }

    @if($progress)
    /* One conic gradient behind the button, redrawn from a single custom property the
       script sets — no second element and nothing to keep in step with the button. */
    #fc-to-top::before {
        content: "";
        position: absolute;
        inset: -4px;
        border-radius: {{ $radius }}%;
        background: conic-gradient({{ $fg }} calc(var(--fc-top-progress, 0) * 1%), transparent 0);
        opacity: .45;
        z-index: -1;
    }
    @endif

    @if($hideMobile)
    @media (max-width: 640px) { #fc-to-top { display: none; } }
    @endif

    @media (prefers-reduced-motion: reduce) {
        #fc-to-top { transition: opacity .2s ease, visibility .2s; transform: none; }
    }
</style>

<button id="fc-to-top" type="button" aria-label="Back to top" hidden
        data-after="{{ $after }}"@if($progress) data-progress="1"@endif>
    @if($icon === 'chevron')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>
    @elseif($icon === 'caret')
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 8l7 9H5z"/></svg>
    @else
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="19" x2="12" y2="6"/><polyline points="5 12 12 5 19 12"/></svg>
    @endif
</button>

<script>
(function () {
    /* Guarded by a window flag rather than by Blade's render-once directive. The theme
       layout renders the footer twice on some requests — once to scan it — so that
       directive has already fired by the time the visible pass runs, and the script never
       reaches the page. A flag is checked by the browser at run time, which a second
       render cannot fool.

       The directive is not named here either: Blade compiles its own directives wherever
       they appear, comments included, and an unclosed one stops the file compiling. */
    if (window.__falconToTop) return;
    window.__falconToTop = true;

    function start() {
        var btn = document.getElementById('fc-to-top');
        if (!btn) return;

        /* Hidden by the attribute until the script is running, so a reader with no
           JavaScript never gets a button that cannot do anything. */
        btn.hidden = false;

        var after = parseInt(btn.getAttribute('data-after'), 10) || 0;
        var showProgress = btn.getAttribute('data-progress') === '1';
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var pending = false;

        function update() {
            pending = false;
            var y = window.pageYOffset || document.documentElement.scrollTop || 0;
            btn.classList.toggle('is-visible', y > after);

            if (showProgress) {
                var total = document.documentElement.scrollHeight - window.innerHeight;
                var done = total > 0 ? (y / total) * 100 : 0;
                btn.style.setProperty('--fc-top-progress', Math.max(0, Math.min(100, done)).toFixed(1));
            }
        }

        /* Throttled with a timer rather than a frame: requestAnimationFrame does not run
           where frames are not produced — a background tab, and every headless browser
           this is tested in — and a button left showing at the top of the page is the
           one state it must never be in. */
        function onScroll() {
            if (pending) return;
            pending = true;
            setTimeout(update, 16);
        }

        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
            /* Focus goes back to the top of the document, so a keyboard or screen-reader
               user carries on from where the page now is rather than from a button that
               has just faded out from under them. */
            var first = document.querySelector('h1, [tabindex], a[href], main');
            if (first && typeof first.focus === 'function') {
                first.setAttribute('tabindex', first.getAttribute('tabindex') || '-1');
                first.focus({ preventScroll: true });
            }
        });

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>
@endif
