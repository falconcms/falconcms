@php
    /*
    |--------------------------------------------------------------------------
    | Code Block — frontend renderer
    |--------------------------------------------------------------------------
    | Highlighting, the language rules and the colour themes all come from
    | FalconCms\Core\Support\CodeHighlighter. The admin canvas receives the very
    | same arrays as JSON and runs a scanner that mirrors this one, so the two
    | renderers cannot drift. See:
    |   resources/views/admin/falcon-builder/partials/components/elements/code-block.blade.php
    |   resources/views/admin/falcon-builder/partials/scripts.blade.php  (fcCodeXxx helpers)
    |
    | The code is highlighted on the server, so a visitor with JavaScript off still
    | gets a fully coloured, selectable, copyable-by-hand block. JavaScript only
    | adds the copy button's clipboard call and the optional typing reveal.
    */
    use FalconCms\Core\Support\CodeHighlighter;

    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    // ── Content ──────────────────────────────────────────────────────────────
    $code      = (string) ($s['code'] ?? '');
    $language  = $s['language'] ?? 'php';
    $themeKey  = $s['codeTheme'] ?? 'falcon-dark';
    $themes    = CodeHighlighter::themes();
    $theme     = $themes[$themeKey] ?? reset($themes);

    $showChrome   = !empty($s['showChrome']);
    $chromeDots   = ($s['chromeDots'] ?? true) !== false;
    $filename     = trim((string) ($s['filename'] ?? ''));
    $showLangTag  = ($s['showLangTag'] ?? true) !== false;

    $showLineNo   = ($s['showLineNumbers'] ?? true) !== false;
    $startLine    = max(1, (int) ($s['startLine'] ?? 1));
    $markLines    = CodeHighlighter::parseLineSpec($s['highlightLines'] ?? '');
    $wrapLines    = !empty($s['wrapLines']);
    $maxHeight    = (int) ($s['maxHeight'] ?? 0);

    // ── Copy button ──────────────────────────────────────────────────────────
    $showCopy     = ($s['showCopy'] ?? true) !== false;
    $copyLabel    = trim((string) ($s['copyLabel'] ?? 'Copy')) ?: 'Copy';
    $copiedLabel  = trim((string) ($s['copiedLabel'] ?? 'Copied!')) ?: 'Copied!';

    // ── Typing reveal ────────────────────────────────────────────────────────
    // Distinct from the builder's shared entrance animations (anim_type), which
    // already wrap every element: those move the whole block, this reveals the
    // code itself. Both can be on at once.
    $typeMode     = $s['typeMode']  ?? 'none';          // none | typewriter | lines
    $typeSpeed    = max(1, (int) ($s['typeSpeed'] ?? 30));
    $typeStart    = $s['typeStart'] ?? 'view';          // view | load
    $typeCaret    = ($s['typeCaret'] ?? true) !== false;

    // ── Box ──────────────────────────────────────────────────────────────────
    $fontSize     = (float) ($s['fontSize'] ?? 14);
    $lineHeight   = (float) ($s['lineHeight'] ?? 1.7);
    $radius       = (int) ($s['borderRadius'] ?? 10);
    $padding      = (int) ($s['padding'] ?? 18);
    $borderWidth  = (int) ($s['borderWidth'] ?? 1);
    $fontFamily   = trim((string) ($s['fontFamily'] ?? '')) ?: 'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace';

    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');

    $lines   = CodeHighlighter::highlightLines($code, $language);
    // A trailing newline yields one empty line the author did not type; drop it so
    // the block does not end with a blank numbered row.
    if (count($lines) > 1 && $lines[count($lines) - 1] === '') array_pop($lines);

    // $uid scopes this block's <style>; it belongs to the shell only. The wrapper takes
    // the author's CSS ID when there is one and otherwise stays without an id — giving
    // both elements $uid put the same id on the page twice.
    $uid     = 'fc-code-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($el['id'] ?? uniqid('', false)));
    $elemId  = !empty($s['cssId']) ? $s['cssId'] : null;
    $langTag = CodeHighlighter::languageOptions()[$language] ?? $language;
@endphp

<div class="falcon-code-block{{ $visibilityClasses }} {{ $s['cssClass'] ?? '' }}"
     @if($elemId) id="{{ $elemId }}" @endif
     style="width:100%;margin-top:{{ $marginTop }};margin-bottom:{{ $marginBottom }};">

    <style>
        #{{ $uid }} {
            --fc-bg: {{ $theme['bg'] }};
            --fc-fg: {{ $theme['fg'] }};
            --fc-border: {{ $theme['border'] }};
            --fc-chrome: {{ $theme['chrome'] }};
            --fc-chrome-text: {{ $theme['chromeText'] }};
            --fc-lineno: {{ $theme['lineNo'] }};
            --fc-mark: {{ $theme['mark'] }};
            background: var(--fc-bg);
            border: {{ $borderWidth }}px solid var(--fc-border);
            border-radius: {{ $radius }}px;
            overflow: hidden;
            position: relative;
            font-family: {{ $fontFamily }};
        }
        #{{ $uid }} .fc-code-chrome {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px;
            background: var(--fc-chrome);
            border-bottom: 1px solid var(--fc-border);
            color: var(--fc-chrome-text);
            font-size: 12px; line-height: 1;
        }
        #{{ $uid }} .fc-code-dots { display: flex; gap: 6px; flex: none; }
        #{{ $uid }} .fc-code-dots i {
            width: 10px; height: 10px; border-radius: 50%; display: block;
        }
        #{{ $uid }} .fc-code-name { flex: 1 1 auto; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #{{ $uid }} .fc-code-lang {
            flex: none; text-transform: uppercase; letter-spacing: .08em;
            font-size: 10.5px; opacity: .75;
        }
        #{{ $uid }} .fc-code-scroll {
            overflow: auto;
            @if($maxHeight > 0) max-height: {{ $maxHeight }}px; @endif
        }
        #{{ $uid }} pre {
            margin: 0;
            padding: {{ $padding }}px {{ $padding }}px;
            color: var(--fc-fg);
            font-family: inherit;
            font-size: {{ $fontSize }}px;
            line-height: {{ $lineHeight }};
            tab-size: 4;
            background: none;
            border: 0;
        }
        #{{ $uid }} .fc-code-line {
            display: flex;
            white-space: {{ $wrapLines ? 'pre-wrap' : 'pre' }};
            @if($wrapLines) overflow-wrap: anywhere; @endif
        }
        #{{ $uid }} .fc-code-line.is-marked {
            background: var(--fc-mark);
            box-shadow: inset 2px 0 0 currentColor;
        }
        #{{ $uid }} .fc-code-no {
            flex: none;
            width: {{ max(2, strlen((string) ($startLine + count($lines)))) + 1 }}ch;
            margin-right: 14px;
            text-align: right;
            color: var(--fc-lineno);
            user-select: none;
            -webkit-user-select: none;
        }
        #{{ $uid }} .fc-code-text { flex: 1 1 auto; min-width: 0; }
        #{{ $uid }} .fc-code-copy {
            position: absolute; top: {{ $showChrome ? 6 : 10 }}px; right: 10px; z-index: 2;
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 10px;
            font: 600 11.5px/1 {{ $fontFamily }};
            color: var(--fc-chrome-text);
            background: var(--fc-chrome);
            border: 1px solid var(--fc-border);
            border-radius: 5px;
            cursor: pointer;
            opacity: 0; transition: opacity .18s ease, color .18s ease;
        }
        #{{ $uid }}:hover .fc-code-copy,
        #{{ $uid }} .fc-code-copy:focus-visible { opacity: 1; }
        #{{ $uid }} .fc-code-copy.is-done { color: var(--fc-fg); }
        @media (hover: none) { #{{ $uid }} .fc-code-copy { opacity: 1; } }

        @foreach(\FalconCms\Core\Support\CodeHighlighter::TOKENS as $tok)
        #{{ $uid }} .fc-t-{{ $tok }} { color: {{ $theme['tokens'][$tok] ?? $theme['fg'] }}; }
        @endforeach
        #{{ $uid }} .fc-t-comment { font-style: italic; }

        @if($typeMode !== 'none')
        /* Hidden while pending AND while running: hiding only on "pending" meant the
           first thing runTyping() does — marking the block as running — revealed every
           line at once, so line-by-line had nothing left to stagger. Each line is opted
           back in with .is-shown as its turn comes. Opacity rather than visibility so
           the reveal can be eased, and the block keeps its height either way. */
        #{{ $uid }}[data-typing="pending"] .fc-code-line,
        #{{ $uid }}[data-typing="running"] .fc-code-line { opacity: 0; }
        #{{ $uid }} .fc-code-line.is-shown { opacity: 1; transform: none; }
        @if($typeMode === 'lines')
        #{{ $uid }} .fc-code-line {
            transform: translateY(4px);
            transition: opacity .3s ease, transform .3s ease;
        }
        @endif
        #{{ $uid }} .fc-code-caret {
            display: inline-block; width: .6ch; height: 1.05em;
            vertical-align: text-bottom; background: currentColor;
            animation: fc-code-blink 1s steps(1) infinite;
        }
        @keyframes fc-code-blink { 50% { opacity: 0; } }
        @media (prefers-reduced-motion: reduce) {
            #{{ $uid }}[data-typing] .fc-code-line {
                opacity: 1 !important;
                transform: none !important;
                transition: none !important;
            }
            #{{ $uid }} .fc-code-caret { display: none; }
        }
        @endif
    </style>

    <div id="{{ $uid }}"
         class="fc-code-shell"
         @if($typeMode !== 'none')
             data-typing="pending"
             data-type-mode="{{ $typeMode }}"
             data-type-speed="{{ $typeSpeed }}"
             data-type-start="{{ $typeStart }}"
             data-type-caret="{{ $typeCaret ? '1' : '0' }}"
         @endif>

        @if($showChrome)
            <div class="fc-code-chrome">
                @if($chromeDots)
                    <span class="fc-code-dots" aria-hidden="true">
                        <i style="background:#FF5F57"></i><i style="background:#FEBC2E"></i><i style="background:#28C840"></i>
                    </span>
                @endif
                <span class="fc-code-name">{{ $filename }}</span>
                @if($showLangTag)<span class="fc-code-lang">{{ $langTag }}</span>@endif
            </div>
        @endif

        @if($showCopy)
            {{-- The button carries the code in a data attribute rather than reading it
                 back out of the DOM: the rendered markup is split across line elements
                 and interleaved with line numbers, so scraping it would copy the
                 numbers too. --}}
            <button type="button" class="fc-code-copy"
                    data-copy-label="{{ $copyLabel }}"
                    data-copied-label="{{ $copiedLabel }}"
                    data-code="{{ base64_encode($code) }}"
                    aria-label="{{ $copyLabel }}">{{ $copyLabel }}</button>
        @endif

        <div class="fc-code-scroll">
            {{-- No newline between the line spans. Each .fc-code-line is a flex row and
                 already stacks; inside <pre> a literal newline would survive as well and
                 every line would sit a blank row apart. --}}
            <pre><code>@foreach($lines as $i => $line)<span class="fc-code-line{{ in_array($startLine + $i, $markLines, true) ? ' is-marked' : '' }}">@if($showLineNo)<span class="fc-code-no">{{ $startLine + $i }}</span>@endif<span class="fc-code-text">{!! $line !== '' ? $line : '&nbsp;' !!}</span></span>@endforeach</code></pre>
        </div>
    </div>
</div>

{{-- Emitted with every block, and made safe to repeat by the flag below, rather than
     wrapped in @once.

     @once cannot be trusted here: the theme layout renders the builder's content more
     than once per request — once early to scan it for icon libraries — and the first
     render spends the @once. The copy of the content that actually reaches the page is
     the later one, which then carries no script at all: the copy button did nothing and
     a block with a typing reveal stayed hidden forever, because the CSS hides its lines
     until the script reveals them. The other elements that ship behaviour (accordion,
     counter) emit a plain <script> for the same reason. --}}
<script>
    (function () {
        'use strict';

        if (window.__falconCodeBlock) { window.__falconCodeBlock.init(); return; }
        window.__falconCodeBlock = { init: null };

        // The Clipboard API needs a secure context, and a CMS is very often reached over
        // plain http on a local domain, where it simply is not there. The textarea
        // fallback has to be genuinely selectable to copy from — an opacity:0 or
        // display:none element is refused — so it is parked off-screen at full opacity
        // instead, which is the only version browsers actually honour.
        // The textarea has to be genuinely selectable to copy from — an opacity:0 or
        // display:none element is refused — so it is parked off-screen at full opacity,
        // which is the only version browsers actually honour.
        function copyViaTextarea(text) {
            return new Promise(function (resolve, reject) {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.cssText = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;padding:0;border:0;';
                document.body.appendChild(ta);
                ta.select();
                ta.setSelectionRange(0, ta.value.length);   // iOS ignores select() alone
                var ok = false;
                try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
                document.body.removeChild(ta);
                ok ? resolve() : reject(new Error('copy refused'));
            });
        }

        function copyText(text) {
            // The Clipboard API needs a secure context, and a CMS is very often reached
            // over plain http on a local domain, where it is simply not there.
            if (!navigator.clipboard || !window.isSecureContext) {
                return copyViaTextarea(text);
            }

            // Even where it exists, writeText() requires the document to hold focus, and
            // when it does not Chrome leaves the promise pending rather than rejecting —
            // the button then sits there having done nothing, with no error to explain
            // it. So the promise is raced against a short timer and the textarea takes
            // over if it has not settled.
            return new Promise(function (resolve, reject) {
                var settled = false;
                var finish = function (ok, err) {
                    if (settled) return;
                    settled = true;
                    ok ? resolve() : copyViaTextarea(text).then(resolve, reject);
                };
                var timer = setTimeout(function () { finish(false); }, 250);
                navigator.clipboard.writeText(text).then(
                    function () { clearTimeout(timer); if (!settled) { settled = true; resolve(); } },
                    function () { clearTimeout(timer); finish(false); }
                );
            });
        }

        function flash(btn, label, done) {
            btn.textContent = label;
            btn.classList.toggle('is-done', !!done);
            clearTimeout(btn._t);
            btn._t = setTimeout(function () {
                btn.textContent = btn.dataset.copyLabel;
                btn.classList.remove('is-done');
            }, 1600);
        }

        document.addEventListener('click', function (e) {
            var btn = e.target && e.target.closest ? e.target.closest('.fc-code-copy') : null;
            if (!btn) return;
            e.preventDefault();

            var code;
            try { code = decodeURIComponent(escape(atob(btn.dataset.code || ''))); }
            catch (err) { code = atob(btn.dataset.code || ''); }

            copyText(code).then(function () {
                flash(btn, btn.dataset.copiedLabel, true);
            }).catch(function () {
                // Never leave the click unanswered: a button that looks dead is worse
                // than one that says it could not copy, and the visitor can still select
                // the code by hand.
                flash(btn, 'Press Ctrl+C', false);
                var range = document.createRange();
                var pre = btn.parentNode.querySelector('pre');
                if (pre) {
                    range.selectNodeContents(pre);
                    var sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            });
        });

        function runTyping(shell) {
            if (shell.dataset.typing !== 'pending') return;
            shell.dataset.typing = 'running';

            var lines = Array.prototype.slice.call(shell.querySelectorAll('.fc-code-line'));
            var speed = parseInt(shell.dataset.typeSpeed, 10) || 30;
            var caret = shell.dataset.typeCaret === '1';

            if (shell.dataset.typeMode === 'lines') {
                lines.forEach(function (line, i) {
                    setTimeout(function () {
                        line.classList.add('is-shown');
                        if (i === lines.length - 1) shell.dataset.typing = 'done';
                    }, i * speed * 2);
                });
                if (!lines.length) shell.dataset.typing = 'done';
                return;
            }

            // typewriter: reveal character by character, keeping the highlighted
            // markup by walking text nodes rather than rewriting innerHTML.
            var nodes = [];
            lines.forEach(function (line) {
                var walker = document.createTreeWalker(line, NodeFilter.SHOW_TEXT);
                var n;
                while ((n = walker.nextNode())) {
                    if (line.querySelector('.fc-code-no') && line.querySelector('.fc-code-no').contains(n)) continue;
                    nodes.push({ node: n, full: n.nodeValue, line: line });
                    n.nodeValue = '';
                }
            });

            var idx = 0, pos = 0, cursor = null;
            if (caret) {
                cursor = document.createElement('span');
                cursor.className = 'fc-code-caret';
            }

            (function step() {
                if (idx >= nodes.length) {
                    shell.dataset.typing = 'done';
                    if (cursor && cursor.parentNode) cursor.parentNode.removeChild(cursor);
                    return;
                }
                var item = nodes[idx];
                item.line.classList.add('is-shown');
                if (pos === 0 && cursor) item.line.appendChild(cursor);
                item.node.nodeValue = item.full.slice(0, ++pos);
                if (pos >= item.full.length) { idx++; pos = 0; }
                setTimeout(step, speed);
            })();
        }

        function init() {
            var shells = document.querySelectorAll('.fc-code-shell[data-typing="pending"]');
            Array.prototype.forEach.call(shells, function (shell) {
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    shell.dataset.typing = 'done';
                    return;
                }
                if (shell.dataset.typeStart === 'load' || !('IntersectionObserver' in window)) {
                    runTyping(shell);
                    return;
                }
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) { runTyping(entry.target); io.unobserve(entry.target); }
                    });
                }, { threshold: 0.25 });
                io.observe(shell);
            });
        }

        // Later copies of this script call init() again through the flag above, which is
        // how a block rendered after the first one still gets picked up.
        window.__falconCodeBlock.init = init;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
