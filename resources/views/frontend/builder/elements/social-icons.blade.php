@php
    $s = $el['settings'] ?? [];
    $platforms = function_exists('lazy_social_platforms') ? lazy_social_platforms() : [];

    // Device visibility (this element renders via its own view, so it must apply the classes itself).
    $vis = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (($vis['mobile']  ?? true) === false) $visibilityClasses .= ' falcon-hide-mobile';
    if (($vis['tablet']  ?? true) === false) $visibilityClasses .= ' falcon-hide-tablet';
    if (($vis['desktop'] ?? true) === false) $visibilityClasses .= ' falcon-hide-desktop';

    $boxSize  = max(0, (int)($s['boxSize']  ?? 38));
    $iconSize = max(0, (int)($s['iconSize'] ?? 18));
    $gap      = max(0, (int)($s['gap']      ?? 10));
    $align    = $s['align'] ?? 'center';
    if (!in_array($align, ['flex-start','center','flex-end'], true)) $align = 'center';
    $shape    = $s['shape'] ?? 'circle';
    $radius   = $shape === 'circle' ? '50%' : ($shape === 'rounded' ? '8px' : '0');
    $target   = $s['target'] ?? '_blank';
    $uid = 'soc-' . ($el['id'] ?? substr(md5(json_encode($s)), 0, 8));

    // CSS Class & ID (standard). A CSS ID also becomes the scope id so the scoped styles still apply.
    $cssClass = trim((string)($s['cssClass'] ?? ''));
    $cssId    = trim((string)($s['cssId'] ?? ''));
    if ($cssId !== '') $uid = $cssId;

    // Boxed Style + Color Type
    $boxed     = ($s['boxedStyle'] ?? 'default') !== 'no';
    $colorType = $s['colorType'] ?? 'default';

    // Custom colours (only used when Color Type = custom)
    $cIcon  = $s['iconColor']      ?? '#ffffff';
    $cBg    = $s['bgColor']        ?? '#2271b1';
    $cIconH = $s['iconHoverColor'] ?? $cIcon;
    $cBgH   = $s['bgHoverColor']   ?? $cBg;

    // Resolve the base fg/bg for a given platform brand colour.
    $resolve = function(string $brand) use ($colorType, $boxed, $cIcon, $cBg) {
        if ($colorType === 'brand') {
            return $boxed ? ['fg' => lazy_contrast_color($brand), 'bg' => $brand]
                          : ['fg' => $brand, 'bg' => 'transparent'];
        }
        if ($colorType === 'custom') {
            return $boxed ? ['fg' => $cIcon, 'bg' => $cBg]
                          : ['fg' => $cIcon, 'bg' => 'transparent'];
        }
        // default theme colours
        return $boxed ? ['fg' => '#ffffff', 'bg' => '#2271b1']
                      : ['fg' => '#2271b1', 'bg' => 'transparent'];
    };

    // Hover rule (shared). Brand mode keeps its own colour (no hover change).
    if ($colorType === 'brand') {
        $hoverCss = '';
    } elseif ($colorType === 'custom') {
        $hoverCss = $boxed ? "#{$uid} a:hover{background:{$cBgH};color:{$cIconH}}"
                           : "#{$uid} a:hover{color:{$cIconH}}";
    } else {
        $hoverCss = $boxed ? "#{$uid} a:hover{background:#135e96;color:#ffffff}"
                           : "#{$uid} a:hover{color:#135e96}";
    }

    // Tooltip (platform name on hover). default = top; none = off.
    $tipPos = $s['tooltipPosition'] ?? 'default';
    if ($tipPos === 'default') $tipPos = 'top';
    $tipEnabled = $tipPos !== 'none';
    $tipCss = '';
    if ($tipEnabled) {
        $place = [
            'top'    => 'bottom:100%;left:50%;transform:translateX(-50%) translateY(-8px)',
            'bottom' => 'top:100%;left:50%;transform:translateX(-50%) translateY(8px)',
            'left'   => 'right:100%;top:50%;transform:translateY(-50%) translateX(-8px)',
            'right'  => 'left:100%;top:50%;transform:translateY(-50%) translateX(8px)',
        ][$tipPos] ?? 'bottom:100%;left:50%;transform:translateX(-50%) translateY(-8px)';
        $tipCss  = "#{$uid} a{position:relative}";
        $tipCss .= "#{$uid} a::after{content:attr(data-tip);position:absolute;{$place};background:#111;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;line-height:1.5;white-space:nowrap;opacity:0;visibility:hidden;transition:opacity .18s;pointer-events:none;z-index:30}";
        $tipCss .= "#{$uid} a:hover::after{opacity:1;visibility:visible}";
    }

    // Responsive breakpoints (theme settings)
    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $bpSm1 = $bpSm + 1;

    // Margin (dimensions field): desktop = margin_{side}, responsive = margin_{side}_{device}.
    $sides = ['top','right','bottom','left'];
    $marginCss = '';
    foreach ($sides as $side) {
        $v = $s['margin_' . $side] ?? '';
        if ($v === '' || $v === null) continue;
        $u = $s['margin_' . $side . '_unit'] ?? 'px';
        $marginCss .= "margin-{$side}:{$v}{$u};";
    }
    $getRespMargin = function(string $side, string $dev) use ($s): ?array {
        $base = 'margin_' . $side;
        if ($dev === 'mobile') {
            foreach (['_mobile','_tablet'] as $suf) {
                if (isset($s[$base . $suf]) && $s[$base . $suf] !== '') {
                    $u = $s[$base . '_unit' . $suf] ?? $s[$base . '_unit'] ?? 'px';
                    return [(string)$s[$base . $suf], $u];
                }
            }
        } elseif ($dev === 'tablet') {
            if (isset($s[$base . '_tablet']) && $s[$base . '_tablet'] !== '') {
                $u = $s[$base . '_unit_tablet'] ?? $s[$base . '_unit'] ?? 'px';
                return [(string)$s[$base . '_tablet'], $u];
            }
        }
        return null;
    };
    // Responsive alignment (justify-content): desktop = align, responsive = align_{device}.
    $getRespAlign = function(string $dev) use ($s): ?string {
        if ($dev === 'mobile') {
            if (!empty($s['align_mobile'])) return $s['align_mobile'];
            if (!empty($s['align_tablet'])) return $s['align_tablet'];
        } elseif ($dev === 'tablet') {
            if (!empty($s['align_tablet'])) return $s['align_tablet'];
        }
        return null;
    };
    $respCss = '';
    foreach ([
        ['tablet', "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px)"],
        ['mobile', "@media(max-width:{$bpSm}px)"],
    ] as [$rDev, $rMq]) {
        $rules = [];
        foreach ($sides as $side) {
            $m = $getRespMargin($side, $rDev);
            if ($m !== null) $rules[] = "margin-{$side}:{$m[0]}{$m[1]}!important";
        }
        $a = $getRespAlign($rDev);
        if ($a && in_array($a, ['flex-start','center','flex-end'], true)) $rules[] = "justify-content:{$a}!important";
        if (!empty($rules)) $respCss .= $rMq . '{#' . $uid . '{' . implode(';', $rules) . '}}';
    }

    // Visible links: only platforms whose field has a value.
    $links = [];
    foreach ($platforms as $key => $p) {
        $url = trim((string)($s[$key] ?? ''));
        if ($url === '') continue;
        if ($key === 'phone') {
            if (!preg_match('~^tel:~i', $url)) $url = 'tel:' . preg_replace('~[\s()\-]+~', '', $url);
        } elseif ($key === 'email') {
            if (!preg_match('~^mailto:~i', $url)) $url = 'mailto:' . $url;
        } elseif (!preg_match('~^(https?:|//)~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        $c = $resolve($p['color'] ?? '#2271b1');
        $links[] = ['url' => $url, 'icon' => $p['icon'], 'label' => $p['label'], 'fg' => $c['fg'], 'bg' => $c['bg']];
    }

    // Base anchor CSS — box dimensions only when boxed.
    $boxCss = $boxed ? "width:{$boxSize}px;height:{$boxSize}px;border-radius:{$radius};" : '';
@endphp

<style>
    #{{ $uid }}{display:flex;flex-wrap:wrap;width:100%;gap:{{ $gap }}px;justify-content:{{ $align }};{!! $marginCss !!}}
    #{{ $uid }} a{{!! $boxCss !!}display:inline-flex;align-items:center;justify-content:center;text-decoration:none;transition:background .25s,color .25s,filter .2s;line-height:1}
    #{{ $uid }} a i{font-size:{{ $iconSize }}px}
    {!! $hoverCss !!}
    {!! $tipCss !!}
    {!! $respCss !!}
</style>

<div id="{{ $uid }}" class="lazy-social-icons{{ $visibilityClasses }}{{ $cssClass ? ' '.$cssClass : '' }}">
    @forelse($links as $l)
        <a href="{{ $l['url'] }}" target="{{ $target }}" rel="noopener noreferrer" aria-label="{{ $l['label'] }}"
           @if($tipEnabled) data-tip="{{ $l['label'] }}" @endif
           style="color:{{ $l['fg'] }};@if($boxed)background:{{ $l['bg'] }};@endif"><i class="{{ $l['icon'] }}"></i></a>
    @empty
        <span style="font-size:12px;color:#9ca3af">Add a social link in the element settings.</span>
    @endforelse
</div>
