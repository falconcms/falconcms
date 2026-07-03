@php
    $s = $el['settings'] ?? [];

    $showHome   = ($s['showHome'] ?? true) !== false;
    $homeLabel  = trim((string)($s['homeLabel'] ?? 'Home')) ?: 'Home';
    $separator  = (string)($s['separator'] ?? '/');
    if ($separator === '') { $separator = '/'; }
    $showCurrent = ($s['showCurrent'] ?? true) !== false;

    // Build the breadcrumb trail (mirrors the frontend breadcrumbs component)
    $items = [];
    if ($showHome) {
        $items[] = ['title' => $homeLabel, 'url' => url('/')];
    }

    if (isset($post) && $post) {
        if (($post->type ?? null) && $post->type !== 'post' && $post->type !== 'page') {
            try {
                $postType = \FalconCms\Core\Models\PostType::where('slug', $post->type)->first();
                if ($postType) {
                    $items[] = ['title' => $postType->name, 'url' => route('frontend.show', $post->type)];
                }
            } catch (\Throwable $e) {}
        }

        if (($post->type ?? null) === 'post' && isset($post->categories) && $post->categories->isNotEmpty()) {
            $cat = $post->categories->first();
            try {
                $items[] = ['title' => $cat->name, 'url' => route('frontend.category', $cat->slug)];
            } catch (\Throwable $e) {
                $items[] = ['title' => $cat->name, 'url' => null];
            }
        }

        if ($showCurrent) {
            $items[] = ['title' => $post->title ?? '', 'url' => null];
        }
    } elseif ($showCurrent && isset($title)) {
        $items[] = ['title' => $title, 'url' => null];
    }

    // Visibility classes
    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $fsRaw = $s['fontSize'] ?? 14;
    $fsCSS = preg_match('/[a-zA-Z%]/', (string)$fsRaw) ? (string)$fsRaw : ($fsRaw . ($s['fontSizeUnit'] ?? 'px'));

    $wrapperStyles = [
        'width: 100%',
        'text-align: ' . ($s['textAlign'] ?? 'left'),
        'padding-top: '    . ($s['paddingTop']    ?? 0) . ($s['paddingTopUnit']    ?? 'px'),
        'padding-right: '  . ($s['paddingRight']  ?? 0) . ($s['paddingRightUnit']  ?? 'px'),
        'padding-bottom: ' . ($s['paddingBottom'] ?? 0) . ($s['paddingBottomUnit'] ?? 'px'),
        'padding-left: '   . ($s['paddingLeft']   ?? 0) . ($s['paddingLeftUnit']   ?? 'px'),
        'margin-top: '     . ($s['marginTop']     ?? 0)  . ($s['marginTopUnit']     ?? 'px'),
        'margin-right: '   . ($s['marginRight']   ?? 0)  . ($s['marginRightUnit']   ?? 'px'),
        'margin-bottom: '  . ($s['marginBottom']  ?? 0)  . ($s['marginBottomUnit']  ?? 'px'),
        'margin-left: '    . ($s['marginLeft']    ?? 0)  . ($s['marginLeftUnit']    ?? 'px'),
    ];

    $align   = $s['textAlign'] ?? 'left';
    $justify = $align === 'right' ? 'flex-end' : ($align === 'center' ? 'center' : 'flex-start');

    $lsRaw = $s['letterSpacing'] ?? '';
    $lsCSS = ($lsRaw === '' || $lsRaw === null) ? 'normal' : (preg_match('/[a-zA-Z%]/', (string)$lsRaw) ? (string)$lsRaw : ($lsRaw . 'px'));

    $navStyles = [
        'font-family: ' . ($s['fontFamily'] ?? 'inherit'),
        'font-size: ' . $fsCSS,
        'font-weight: ' . ($s['fontWeight'] ?? '400'),
        'line-height: ' . ($s['lineHeight'] ?? '1.6'),
        'letter-spacing: ' . $lsCSS,
        'text-transform: ' . ($s['textTransform'] ?? 'none'),
        'display: flex',
        'flex-wrap: wrap',
        'align-items: center',
        'gap: 6px',
        'width: 100%',
        'max-width: 100%',
        'justify-content: ' . $justify,
    ];

    $baseColor      = $s['color']          ?: '#6b7280';
    $linkColor      = $s['linkColor']      ?: $baseColor;
    $linkHoverColor = $s['linkHoverColor'] ?: '#2271b1';
    $separatorColor = $s['separatorColor'] ?: '#9ca3af';
    $currentColor   = $s['currentColor']   ?: '#111827';

    $bcId = 'falcon-bc-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));

    // Schema.org data
    $breadcrumbList = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [],
    ];
    foreach ($items as $i => $it) {
        $breadcrumbList['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $it['title'],
            'item' => $it['url'] ?? url()->current(),
        ];
    }
@endphp

@if(count($items))
<style>
    #{{ $bcId }} a { color: {{ $linkColor }}; text-decoration: none; transition: color 0.2s ease; }
    #{{ $bcId }} a:hover { color: {{ $linkHoverColor }}; }
</style>
<div class="element-breadcrumb-wrapper {{ $s['cssClass'] ?? '' }}{{ $visibilityClasses }}"
     @if(!empty($s['cssId'])) id="{{ $s['cssId'] }}" @endif
     style="{{ implode('; ', $wrapperStyles) }}">
    <nav id="{{ $bcId }}" class="element-breadcrumb" aria-label="Breadcrumb"
         style="{{ implode('; ', $navStyles) }}">
        @foreach($items as $index => $item)
            @php $isLast = $index === count($items) - 1; @endphp
            @if(!empty($item['url']) && !$isLast)
                <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                <span style="color: {{ $separatorColor }};">{!! $separator !!}</span>
            @else
                <span style="color: {{ $currentColor }}; font-weight: 500;">{{ $item['title'] }}</span>
            @endif
        @endforeach
    </nav>
</div>
<script type="application/ld+json">
{!! json_encode($breadcrumbList, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
@endif
