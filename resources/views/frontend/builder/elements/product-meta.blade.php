@php
    $s = $el['settings'] ?? [];

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    // Field toggles
    $showPrice    = $s['showPrice']    ?? true;
    $showSku      = $s['showSku']      ?? true;
    $showStock    = $s['showStock']    ?? true;
    $showStockQty = $s['showStockQty'] ?? false;
    $showType     = $s['showType']     ?? false;

    // Labels
    $showLabels = $s['showLabels'] ?? true;
    $priceLabel = $s['priceLabel'] ?? 'Price:';
    $skuLabel   = $s['skuLabel']   ?? 'SKU:';
    $stockLabel = $s['stockLabel'] ?? 'Availability:';
    $qtyLabel   = $s['qtyLabel']   ?? 'In stock:';
    $typeLabel  = $s['typeLabel']  ?? 'Type:';

    // Layout
    $layout    = $s['layout']    ?? 'stacked';
    $isInline  = $layout === 'inline';
    $separator = $s['separator'] ?? '·';
    $metaAlign = $s['metaAlign'] ?? 'left';
    $justify   = match ($metaAlign) { 'center' => 'center', 'right' => 'flex-end', default => 'flex-start' };

    // Design
    $labelColor   = $s['labelColor']      ?? '#6b7280';
    $valueColor   = $s['valueColor']      ?? '#111827';
    $saleColor    = $s['saleColor']       ?? '#e02b2b';
    $instockColor = $s['instockColor']    ?? '#15803d';
    $outColor     = $s['outofstockColor'] ?? '#b91c1c';
    $fontSize     = ($s['fontSize'] ?? 14) . ($s['fontSizeUnit'] ?? 'px');
    $fontWeight   = $s['fontWeight'] ?? '400';
    $gap          = ($s['gap'] ?? 8) . ($s['gapUnit'] ?? 'px');
    $mt = (isset($s['marginTop'])    && $s['marginTop']    !== '' ? $s['marginTop']    : 0) . ($s['marginTopUnit']    ?? 'px');
    $mb = (isset($s['marginBottom']) && $s['marginBottom'] !== '' ? $s['marginBottom'] : 0) . ($s['marginBottomUnit'] ?? 'px');
    $cssClass = $s['cssClass'] ?? '';
    $cssId    = $s['cssId']    ?? '';
    $pmUid    = 'fpm-' . ($el['id'] ?? str_replace('.', '', uniqid('', true)));

    // Resolve the product's shop data from the current post context (product page).
    // Post::shopData() exists for any post, so this works whether $post is a Post or Product.
    $shopData = (isset($post) && $post) ? ($post->shopData ?? null) : null;

    $priceFmt = function ($val) {
        return function_exists('falcon_price_format') ? falcon_price_format((float) $val) : number_format((float) $val, 2);
    };

    $rows = [];
    if ($shopData) {
        if ($showPrice) {
            $regular    = (float) ($shopData->price ?? 0);
            $sale       = $shopData->sale_price;
            $saleActive = ($sale !== null && $sale !== '' &&
                (empty($shopData->sale_ends_at) || \Carbon\Carbon::parse($shopData->sale_ends_at)->isFuture()));
            if ($saleActive) {
                $valueHtml = '<span style="text-decoration:line-through;opacity:.55;margin-right:6px;">' . e($priceFmt($regular)) . '</span>'
                           . '<span style="color:' . e($saleColor) . ';font-weight:700;">' . e($priceFmt($sale)) . '</span>';
            } else {
                $valueHtml = '<span style="font-weight:700;">' . e($priceFmt($regular)) . '</span>';
            }
            $rows[] = ['label' => $priceLabel, 'html' => $valueHtml];
        }

        if ($showSku && !empty($shopData->sku)) {
            $rows[] = ['label' => $skuLabel, 'html' => '<span>' . e($shopData->sku) . '</span>'];
        }

        if ($showStock) {
            $isOut = ($shopData->stock_status ?? 'instock') === 'outofstock'
                  || (($shopData->manage_stock ?? false) && (int) ($shopData->stock_quantity ?? 0) <= 0);
            $rows[] = [
                'label' => $stockLabel,
                'html'  => '<span style="color:' . e($isOut ? $outColor : $instockColor) . ';font-weight:600;">'
                         . ($isOut ? 'Out of stock' : 'In stock') . '</span>',
            ];
        }

        if ($showStockQty && ($shopData->manage_stock ?? false) && $shopData->stock_quantity !== null) {
            $rows[] = ['label' => $qtyLabel, 'html' => '<span>' . (int) $shopData->stock_quantity . '</span>'];
        }

        if ($showType && !empty($shopData->product_type)) {
            $rows[] = ['label' => $typeLabel, 'html' => '<span style="text-transform:capitalize;">' . e($shopData->product_type) . '</span>'];
        }
    }
@endphp

@if(!empty($rows))
@php
    // Stacked: align rows horizontally via the cross axis (align-items).
    // Inline:  align the whole line horizontally via the main axis (justify-content).
    $alignCss = $isInline
        ? 'justify-content:' . $justify . ';align-items:center;'
        : 'align-items:' . $justify . ';';
@endphp
<div @if($cssId) id="{{ $cssId }}" @endif
     class="falcon-product-meta {{ $pmUid }} {{ $visibilityClasses }} {{ $cssClass }}"
     style="width:100%;margin-top:{{ $mt }};margin-bottom:{{ $mb }};font-size:{{ $fontSize }};font-weight:{{ $fontWeight }};display:flex;flex-direction:{{ $isInline ? 'row' : 'column' }};flex-wrap:wrap;{{ $alignCss }}gap:{{ $gap }};">
    @foreach($rows as $i => $row)
        <div class="fpm-row" style="display:flex;align-items:center;gap:6px;">
            @if($showLabels && $row['label'] !== '')
                <span class="fpm-label" style="color:{{ $labelColor }};">{{ $row['label'] }}</span>
            @endif
            <span class="fpm-value" style="color:{{ $valueColor }};">{!! $row['html'] !!}</span>
        </div>
        @if($isInline && $separator !== '' && $i < count($rows) - 1)
            <span class="fpm-sep" aria-hidden="true" style="color:{{ $labelColor }};opacity:.6;">{{ $separator }}</span>
        @endif
    @endforeach
</div>
@endif
