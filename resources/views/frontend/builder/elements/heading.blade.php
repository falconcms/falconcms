@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $respId = 'lzr-hd-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));

    $fsRaw = $s['fontSize'] ?? '';
    $fontSizeCSS = '';
    if ($fsRaw !== '') {
        $fontSizeCSS = 'font-size:' . (is_numeric($fsRaw) ? ($fsRaw . ($s['fontSizeUnit'] ?? 'px')) : $fsRaw) . ';';
    }
    $letterSpacingCSS = isset($s['letterSpacing']) && $s['letterSpacing'] !== ''
        ? 'letter-spacing:' . $s['letterSpacing'] . ($s['letterSpacingUnit'] ?? 'px') . ';'
        : '';

    // Outer wrapper styles (applied only when setting is explicitly saved)
    $outerStyle = '';
    foreach (['marginTop','marginRight','marginBottom','marginLeft'] as $mp) {
        if (isset($s[$mp]) && $s[$mp] !== '') {
            $cssp = strtolower(preg_replace('/([A-Z])/', '-$1', $mp));
            $outerStyle .= "{$cssp}:{$s[$mp]}" . ($s[$mp . 'Unit'] ?? 'px') . ';';
        }
    }
    foreach (['paddingTop','paddingRight','paddingBottom','paddingLeft'] as $pp) {
        if (isset($s[$pp]) && $s[$pp] !== '') {
            $cssp = strtolower(preg_replace('/([A-Z])/', '-$1', $pp));
            $outerStyle .= "{$cssp}:{$s[$pp]}" . ($s[$pp . 'Unit'] ?? 'px') . ';';
        }
    }

    $w = ".{$respId}";
    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'textAlign',     'sel' => $w],
        ['prop' => 'marginTop',     'unitProp' => 'marginTopUnit',     'sel' => $w],
        ['prop' => 'marginRight',   'unitProp' => 'marginRightUnit',   'sel' => $w],
        ['prop' => 'marginBottom',  'unitProp' => 'marginBottomUnit',  'sel' => $w],
        ['prop' => 'marginLeft',    'unitProp' => 'marginLeftUnit',    'sel' => $w],
        ['prop' => 'paddingTop',    'unitProp' => 'paddingTopUnit',    'sel' => $w],
        ['prop' => 'paddingRight',  'unitProp' => 'paddingRightUnit',  'sel' => $w],
        ['prop' => 'paddingBottom', 'unitProp' => 'paddingBottomUnit', 'sel' => $w],
        ['prop' => 'paddingLeft',   'unitProp' => 'paddingLeftUnit',   'sel' => $w],
    ]);
@endphp
@if($respCss){!! '<style>' . $respCss . '</style>' !!}@endif
<div class="element-heading {{ $respId }}{{ $visibilityClasses }}"
     @if(!empty($s['cssId'])) id="{{ $s['cssId'] }}" @endif
     style="{{ $outerStyle }}">
    @php $headingTag = in_array($s['tag'] ?? 'h2', ['h1','h2','h3','h4','h5','h6','div','p','span']) ? ($s['tag'] ?? 'h2') : 'h2'; @endphp
    <{{ $headingTag }} style="text-align:{{ $s['textAlign'] ?? 'left' }};margin:0;padding:0;{{ $fontSizeCSS }}{{ $letterSpacingCSS }}" class="text-slate-800 font-bold leading-tight">
        {{ $s['title'] ?? 'New Heading' }}
    </{{ $headingTag }}>
</div>
