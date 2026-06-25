@php
    $s = $el['settings'] ?? [];

    // Device Visibility
    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';
    if (!($v['mobile'] ?? true) && !($v['tablet'] ?? true) && !($v['desktop'] ?? true)) {
        $visibilityClasses = ' falcon-hide-all';
    }
@endphp
<div class="element-text mb-4 {{ $visibilityClasses }}">
    <div class="prose prose-slate max-w-none" style="
        text-align: {{ $s['textAlign'] ?? 'left' }};
        @if(!empty($s['fontSize'])) font-size: {{ getUnitVal($s['fontSize'], $s['fontSizeUnit'] ?? 'px') }}; @endif
    ">
        {!! falcon_sanitize_html($s['content'] ?? 'Start typing your content here...') !!}
    </div>
</div>
