@php
    /*
    |--------------------------------------------------------------------------
    | Table — frontend renderer
    |--------------------------------------------------------------------------
    | Presets and cell formatting come from FalconCms\Core\Support\TableStyles.
    | The admin canvas receives the same arrays as JSON and formats cells with a
    | mirror of the same rules, so the two renderers cannot drift. See:
    |   resources/views/admin/falcon-builder/partials/components/elements/table.blade.php
    |   resources/views/admin/falcon-builder/partials/scripts.blade.php  (fcTbl* helpers)
    |
    | Cells are plain text with a small markup — `code`, **bold**, *italic*,
    | [text](url) — escaped before those rules run, so a cell can never carry
    | script and no sanitiser has to sit between the editor and the page.
    */
    use FalconCms\Core\Support\TableStyles;

    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $presets = TableStyles::presets();
    $preset  = $presets[$s['preset'] ?? 'docs'] ?? reset($presets);
    // Every preset value stays overridable; the preset only supplies the default.
    //
    // An empty string counts as "not set", not as a value. The Design tab's reset
    // button and the element's own defaults both write '', and with a plain ?? that
    // reached the stylesheet as `color: ;` — the rule was dropped and the preset's
    // colour went with it, so Body text simply did nothing on the page while the
    // canvas, which checks for '' too, showed it working.
    $g = function (string $key) use ($s, $preset) {
        $v = $s[$key] ?? null;

        return ($v === null || $v === '') ? ($preset[$key] ?? '') : $v;
    };

    $rows = TableStyles::rectangular($s['rows'] ?? []);
    $cols = $s['cols'] ?? [];
    $colCount = $rows ? count($rows[0]) : 0;

    $headerRow = ($s['headerRow'] ?? true) !== false;
    $headerCol = !empty($s['headerCol']);
    $caption   = trim((string) ($s['caption'] ?? ''));

    $borders   = $g('borders');            // all | horizontal | none
    $stripe    = (bool) $g('stripe');
    $hover     = (bool) $g('hover');
    $sortable  = !empty($s['sortable']) && $headerRow;
    $responsive = $s['responsive'] ?? 'scroll';   // scroll | stack
    $maxHeight = (int) ($s['maxHeight'] ?? 0);
    $sticky    = !empty($s['stickyHeader']) && $headerRow;

    // Rows and columns an author wants picked out. Numbers are 1-based and counted
    // the way the table reads: row 1 is the first body row.
    $hlRows = TableStyles::parseSpec($s['highlightRows'] ?? '');
    $hlCols = TableStyles::parseSpec($s['highlightCols'] ?? '');
    $hlBg    = $s['highlightBg']    ?? 'rgba(232,145,43,.10)';
    $hlColor = $s['highlightColor'] ?? '';

    $yesColor = $s['iconYesColor'] ?? '#3E7D4F';
    $noColor  = $s['iconNoColor']  ?? '#B0392B';

    // Typography, from the shared control every other element uses. Empty means
    // "leave it to the preset", so a table that has never been touched still follows
    // whichever preset is chosen.
    $typo = function (string $prefix) use ($s): string {
        $out = '';
        foreach ([
            'family' => 'font-family', 'weight' => 'font-weight', 'size' => 'font-size',
            'line_height' => 'line-height', 'letter_spacing' => 'letter-spacing',
            'transform' => 'text-transform',
        ] as $key => $css) {
            $v = trim((string) ($s[$prefix.'_'.$key] ?? ''));
            if ($v === '' || $v === 'inherit' || $v === 'none' && $css === 'text-transform') {
                continue;
            }
            if ($css === 'font-size' && is_numeric($v)) {
                $v .= 'px';
            }
            $out .= $css.': '.$v.'; ';
        }

        return trim($out);
    };
    $headTypo = $typo('tbl_head');
    $bodyTypo = $typo('tbl_body');

    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');

    $uid    = 'fc-tbl-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($el['id'] ?? uniqid('', false)));
    $elemId = !empty($s['cssId']) ? $s['cssId'] : null;

    $head = $headerRow && $rows ? array_shift($rows) : [];
    $align = fn (int $i) => in_array($cols[$i]['align'] ?? '', TableStyles::ALIGNMENTS, true) ? $cols[$i]['align'] : 'left';
    $width = fn (int $i) => trim((string) ($cols[$i]['width'] ?? ''));
@endphp

@if($colCount > 0)
<div class="falcon-table{{ $visibilityClasses }} {{ $s['cssClass'] ?? '' }}"
     @if($elemId) id="{{ $elemId }}" @endif
     style="width:100%;margin-top:{{ $marginTop }};margin-bottom:{{ $marginBottom }};">

    <style>
        #{{ $uid }} { --fc-line: {{ $g('borderColor') }}; }
        #{{ $uid }} .fc-tbl-scroll {
            overflow-x: auto;
            @if($maxHeight > 0) overflow-y: auto; max-height: {{ $maxHeight }}px; @endif
            border-radius: {{ (int) $g('radius') }}px;
            @if($borders === 'all') border: 1px solid var(--fc-line); @endif
        }
        #{{ $uid }} table {
            width: 100%;
            border-collapse: collapse;
            font-size: {{ $g('fontSize') }}px;
            color: {{ $g('textColor') }};
            background: {{ $g('bodyBg') }};
            @if($bodyTypo) {{ $bodyTypo }} @endif
        }
        #{{ $uid }} caption {
            caption-side: bottom;
            padding: 10px 2px 0;
            font-size: {{ max(11, (float) $g('fontSize') - 2) }}px;
            color: #79838F;
            text-align: left;
        }
        {{-- The body colour is set on the cells, not only on the table.

             Inheritance loses to any rule that matches the element directly, however
             low its specificity — and a theme, or Custom CSS carrying a stylesheet from
             elsewhere, very often ships a plain `tbody td { color: … }`. With the colour
             only on <table> that rule won every time, so Body text did nothing on the
             page while the canvas, which paints each cell inline, showed it working.
             The header and any highlighted cells set their own colour further down and
             still win, being written later. --}}
        #{{ $uid }} th, #{{ $uid }} td {
            padding: {{ (int) $g('cellPaddingY') }}px {{ (int) $g('cellPaddingX') }}px;
            vertical-align: top;
            color: {{ $g('textColor') }};
            @if($bodyTypo) {{ $bodyTypo }} @endif
            @if($borders === 'all')
            border: 1px solid var(--fc-line);
            @elseif($borders === 'horizontal')
            border-bottom: 1px solid var(--fc-line);
            @endif
        }
        #{{ $uid }} thead th {
            background: {{ $g('headerBg') }};
            color: {{ $g('headerColor') }};
            font-weight: {{ $g('headerWeight') }};
            text-align: left;
            white-space: nowrap;
            @if($headTypo) {{ $headTypo }} @endif
            @if($sticky) position: sticky; top: 0; z-index: 2; @endif
        }
        @if($headerCol)
        #{{ $uid }} tbody th {
            background: {{ $g('headerBg') }};
            color: {{ $g('headerColor') }};
            font-weight: {{ $g('headerWeight') }};
            text-align: left;
        }
        @endif
        @if($stripe)
        #{{ $uid }} tbody tr:nth-child(even) td { background: {{ $g('stripeBg') }}; }
        @endif
        @if($hover)
        #{{ $uid }} tbody tr:hover td { background: {{ $g('hoverBg') }}; }
        @endif
        #{{ $uid }} td code, #{{ $uid }} th code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: .88em;
            background: rgba(125,135,150,.12);
            padding: .12em .38em;
            border-radius: 4px;
        }
        #{{ $uid }} a { color: #B9720F; }
        #{{ $uid }} .fc-tbl-yes { color: {{ $yesColor }}; }
        #{{ $uid }} .fc-tbl-no  { color: {{ $noColor }}; }
        #{{ $uid }} td i, #{{ $uid }} th i { font-style: normal; }

        {{-- Highlighted rows and columns. These come after the stripe and hover rules
             so a picked-out row keeps its colour rather than losing it every other
             line, and they carry no !important — a hover still reads on top. --}}
        @foreach($hlRows as $n)
        #{{ $uid }} tbody tr:nth-child({{ (int) $n }}) > * {
            background: {{ $hlBg }};
            @if($hlColor) color: {{ $hlColor }}; @endif
        }
        @endforeach
        @foreach($hlCols as $n)
        #{{ $uid }} tbody tr > *:nth-child({{ (int) $n }}) {
            background: {{ $hlBg }};
            @if($hlColor) color: {{ $hlColor }}; @endif
        }
        #{{ $uid }} thead th:nth-child({{ (int) $n }}) { box-shadow: inset 0 -2px 0 {{ $hlColor ?: '#B9720F' }}; }
        @endforeach
        @foreach(range(0, max(0, $colCount - 1)) as $i)
            @php $w = $width($i); $a = $align($i); @endphp
            @if($a !== 'left')
        #{{ $uid }} th:nth-child({{ $i + 1 }}), #{{ $uid }} td:nth-child({{ $i + 1 }}) { text-align: {{ $a }}; }
            @endif
            @if($w !== '')
        #{{ $uid }} th:nth-child({{ $i + 1 }}) { width: {{ $w }}; }
            @endif
        @endforeach

        @if($sortable)
        #{{ $uid }} thead th[data-sort] { cursor: pointer; user-select: none; }
        #{{ $uid }} thead th[data-sort]::after {
            content: "↕"; opacity: .3; margin-left: 6px; font-size: .85em;
        }
        #{{ $uid }} thead th[data-dir="asc"]::after  { content: "↑"; opacity: .9; }
        #{{ $uid }} thead th[data-dir="desc"]::after { content: "↓"; opacity: .9; }
        @endif

        @if($responsive === 'stack')
        /* Below the small breakpoint each row becomes its own card and every cell
           carries its column name, because a documentation table with five columns
           is unreadable squeezed into a phone and side-scrolling hides the first
           column — the one that says what the row is about. */
        @media (max-width: {{ (int) get_cms_option('theme_small_screen_breakpoint', '800') }}px) {
            #{{ $uid }} thead { display: none; }
            #{{ $uid }} table, #{{ $uid }} tbody, #{{ $uid }} tr, #{{ $uid }} td, #{{ $uid }} th { display: block; width: 100%; }
            #{{ $uid }} tbody tr {
                border: 1px solid var(--fc-line);
                border-radius: {{ (int) $g('radius') }}px;
                margin-bottom: 12px;
                overflow: hidden;
            }
            #{{ $uid }} tbody td, #{{ $uid }} tbody th { border: 0; border-bottom: 1px solid var(--fc-line); }
            #{{ $uid }} tbody tr > *:last-child { border-bottom: 0; }
            #{{ $uid }} tbody td[data-label]::before {
                content: attr(data-label);
                display: block;
                font-size: .78em;
                text-transform: uppercase;
                letter-spacing: .06em;
                color: #79838F;
                margin-bottom: 3px;
            }
            #{{ $uid }} th:nth-child(n), #{{ $uid }} td:nth-child(n) { text-align: left; width: auto; }
        }
        @endif
    </style>

    <div id="{{ $uid }}">
        <div class="fc-tbl-scroll">
            <table @if($sortable) data-fc-sortable="1" @endif>
                @if($caption)<caption>{!! TableStyles::cell($caption) !!}</caption>@endif

                @if($headerRow && $head)
                <thead>
                    <tr>
                        @foreach($head as $i => $cell)
                            <th scope="col" @if($sortable) data-sort="{{ $i }}" @endif>{!! TableStyles::cell($cell) !!}</th>
                        @endforeach
                    </tr>
                </thead>
                @endif

                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            @foreach($row as $i => $cell)
                                @if($headerCol && $i === 0)
                                    <th scope="row">{!! TableStyles::cell($cell) !!}</th>
                                @else
                                    <td @if($responsive === 'stack' && isset($head[$i]) && $head[$i] !== '') data-label="{{ strip_tags(TableStyles::cell($head[$i])) }}" @endif>{!! TableStyles::cell($cell) !!}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($sortable)
{{-- Emitted with every sortable table and made safe to repeat by the flag below,
     rather than wrapped in @once: the theme layout renders the builder's content
     more than once per request — once early, to scan it for icon libraries — and
     the first render spends the @once, so the copy that reaches the page carries no
     script at all. The Code Block element was silently broken this way. --}}
<script>
(function () {
    'use strict';
    if (window.__falconTableSort) return;
    window.__falconTableSort = true;

    // "10" must not sort before "9", and "v2.10.0" must not sort before "v2.9.0",
    // which is exactly what a plain string compare does to a documentation table.
    function compare(a, b) {
        var na = parseFloat(a.replace(/[^0-9.\-]/g, ''));
        var nb = parseFloat(b.replace(/[^0-9.\-]/g, ''));
        var aNum = a !== '' && !isNaN(na) && /^[^a-z]*[\d.\-]+[^a-z]*$/i.test(a);
        var bNum = b !== '' && !isNaN(nb) && /^[^a-z]*[\d.\-]+[^a-z]*$/i.test(b);
        if (aNum && bNum) return na - nb;

        var va = a.match(/^v?(\d+(?:\.\d+)*)$/i), vb = b.match(/^v?(\d+(?:\.\d+)*)$/i);
        if (va && vb) {
            var pa = va[1].split('.').map(Number), pb = vb[1].split('.').map(Number);
            for (var i = 0; i < Math.max(pa.length, pb.length); i++) {
                var d = (pa[i] || 0) - (pb[i] || 0);
                if (d) return d;
            }
            return 0;
        }
        return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
    }

    document.addEventListener('click', function (e) {
        var th = e.target && e.target.closest ? e.target.closest('th[data-sort]') : null;
        if (!th) return;

        var table = th.closest('table');
        if (!table || table.dataset.fcSortable !== '1') return;

        var col = parseInt(th.dataset.sort, 10);
        var dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';

        Array.prototype.forEach.call(table.querySelectorAll('th[data-sort]'), function (o) {
            delete o.dataset.dir;
        });
        th.dataset.dir = dir;

        var tbody = table.tBodies[0];
        if (!tbody) return;

        var rows = Array.prototype.slice.call(tbody.rows);
        rows.sort(function (r1, r2) {
            var c1 = r1.cells[col], c2 = r2.cells[col];
            var t1 = (c1 ? c1.textContent : '').trim();
            var t2 = (c2 ? c2.textContent : '').trim();
            return dir === 'asc' ? compare(t1, t2) : compare(t2, t1);
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
    });
})();
</script>
@endif
@endif
