@once
@php
    // Gives rich-editor (TinyMCE) HTML its browser-default rendering back.
    //
    // Tailwind's Preflight — injected by the Play CDN build into <head> AFTER the theme's
    // inline <style> — flattens `ul/ol` to `list-style:none;margin:0;padding:0`, zeroes the
    // margins on p/headings/blockquote/pre/figure/dl, sets `border-width:0` on *everything*
    // (which also erases the `border="1"` TinyMCE writes on tables) and resets `a` to
    // `color:inherit;text-decoration:inherit`. That is exactly right for the utility-class
    // markup the theme and the page builder emit, and exactly wrong for hand-written HTML
    // typed into a rich editor — the classic symptom being <ul><li> with no bullets.
    //
    // Every rule below is scoped to a rich-text container class (specificity 0,1,x), so it
    // beats Preflight whatever the source order turns out to be, and never reaches page-builder
    // element markup, nav menus or theme templates that live outside those containers.
    $rtBases = ['.falcon-rich-text', '.entry-content', '.lazy-rich-content'];

    // Emits "base child, base child, …". Plain descendant selectors only — no :is()/:where() —
    // so one unsupported selector can never invalidate a whole rule.
    $rt = function (array $children) use ($rtBases) {
        $out = [];
        foreach ($rtBases as $base) {
            foreach ($children as $child) {
                $out[] = $base . ' ' . $child;
            }
        }
        return implode(",\n", $out);
    };

    // Shortcodes render *inside* the content ([falcon_lang_dropdown] emits <ul class="list-none">,
    // forms emit classed links). Honouring Tailwind's own list utility and skipping classed
    // links keeps that injected markup looking exactly as it does today.
    $rtUl = 'ul:not(.list-none)';
    $rtOl = 'ol:not(.list-none)';

    $rtLink      = get_cms_option('theme_link_color', '#0091ea');
    $rtLinkHover = get_cms_option('theme_link_hover_color', '#007ac1');

    // Heading size/weight stay the Customizer's job — Appearance → Customize → Typography
    // emits `body h1…h6`. Only tags it has no entry for get a fallback here, so there is no
    // cascade race with the Customizer to lose.
    $rtHeadingFallback = [];
    foreach (['h1' => ['2em', 700], 'h2' => ['1.5em', 700], 'h3' => ['1.25em', 600],
              'h4' => ['1.1em', 600], 'h5' => ['1em', 600], 'h6' => ['0.9em', 600]] as $tag => $def) {
        if (!json_decode((string) get_cms_option("theme_typography_{$tag}"), true)) {
            $rtHeadingFallback[$tag] = $def;
        }
    }
@endphp
<style>
    /* ── Lists ─────────────────────────────────────────────────────────────── */
    {!! $rt([$rtUl, $rtOl]) !!} {
        list-style-position: outside;
        margin: 0 0 1em;
        padding-left: 1.5em;
    }
    {!! $rt([$rtUl]) !!} { list-style-type: disc; }
    {!! $rt([$rtOl]) !!} { list-style-type: decimal; }
    {!! $rt(["{$rtUl} {$rtUl}"]) !!} { list-style-type: circle; }
    {!! $rt(["{$rtUl} {$rtUl} {$rtUl}"]) !!} { list-style-type: square; }
    {!! $rt(["{$rtOl} {$rtOl}"]) !!} { list-style-type: lower-alpha; }
    {!! $rt(["{$rtOl} {$rtOl} {$rtOl}"]) !!} { list-style-type: lower-roman; }
    {!! $rt(["{$rtUl} > li", "{$rtOl} > li"]) !!} { margin-bottom: 0.35em; }
    {!! $rt(["li > {$rtUl}", "li > {$rtOl}"]) !!} { margin: 0.35em 0 0; }

    /* ── Block rhythm ──────────────────────────────────────────────────────── */
    {!! $rt(['p', 'pre', 'figure', 'dl', 'address', 'table']) !!} { margin: 0 0 1em; }
    {!! $rt(['h1', 'h2', 'h3', 'h4', 'h5', 'h6']) !!} { margin: 1.5em 0 0.6em; }
    {!! $rt(['dt']) !!} { font-weight: 600; }
    {!! $rt(['dd']) !!} { margin: 0 0 0.5em 1.5em; }
@foreach($rtHeadingFallback as $rtTag => [$rtSize, $rtWeight])
    {!! $rt([$rtTag]) !!} { font-size: {{ $rtSize }}; font-weight: {{ $rtWeight }}; line-height: 1.3; }
@endforeach

    /* The container keeps its own padding — content shouldn't add to it at the edges. */
    {!! $rt(['> :first-child']) !!} { margin-top: 0; }
    {!! $rt(['> :last-child']) !!} { margin-bottom: 0; }

    /* ── Links (Preflight resets `a` to color:inherit;text-decoration:inherit) ── */
    {!! $rt(['a:not([class])']) !!} {
        color: {{ $rtLink }};
        text-decoration: underline;
        text-underline-offset: 2px;
    }
    {!! $rt(['a:not([class]):hover']) !!} { color: {{ $rtLinkHover }}; }

    /* ── Quotes, rules, code ───────────────────────────────────────────────── */
    {!! $rt(['blockquote']) !!} {
        margin: 1.5em 0;
        padding-left: 1em;
        border-left: 4px solid var(--primary, #0091ea);
        color: var(--text-muted, #666666);
        font-style: italic;
    }
    {!! $rt(['hr']) !!} {
        margin: 2em 0;
        border-top: 1px solid var(--border-color, #e5e7eb);
    }
    {!! $rt(['code', 'kbd', 'samp']) !!} {
        background: rgba(0, 0, 0, 0.05);
        padding: 0.15em 0.4em;
        border-radius: 4px;
        font-size: 0.9em;
    }
    {!! $rt(['pre']) !!} {
        background: rgba(0, 0, 0, 0.05);
        padding: 1em;
        border-radius: 6px;
        overflow-x: auto;
    }
    {!! $rt(['pre code']) !!} { background: none; padding: 0; font-size: inherit; }

    /* ── Tables ────────────────────────────────────────────────────────────── */
    {!! $rt(['th', 'td']) !!} { padding: 0.5em 0.75em; }
    {!! $rt(['th']) !!} { font-weight: 600; text-align: left; }
    {{-- Preflight's `*{border-width:0}` also kills the border="1" attribute TinyMCE writes,
         so borders are restored only for tables that actually asked for them. --}}
    {!! $rt(['table[border]:not([border="0"])',
             'table[border]:not([border="0"]) th',
             'table[border]:not([border="0"]) td']) !!} {
        border-width: 1px;
        border-style: solid;
        border-color: var(--border-color, #e5e7eb);
    }

    /* ── Media ─────────────────────────────────────────────────────────────── */
    {!! $rt(['img', 'video', 'iframe', 'embed', 'object']) !!} { max-width: 100%; }
    {!! $rt(['figcaption']) !!} {
        margin-top: 0.5em;
        font-size: 0.9em;
        color: var(--text-muted, #666666);
    }
</style>
@endonce
