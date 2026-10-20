@php
    use FalconCms\Core\Support\Instagram;
    use Illuminate\Support\Str;

    $s = $el['settings'] ?? [];
    $isPreview = !empty($builderPreview);
    $get = fn (string $k, $d = null) => (isset($s[$k]) && $s[$k] !== '' && $s[$k] !== null) ? $s[$k] : $d;
    $notice = null;

    // ── what to show ────────────────────────────────────────────────────
    $items = [];
    $profile = ['username' => '', 'name' => '', 'avatar' => '', 'followers' => null];

    if (!Instagram::licensed()) {
        $notice = 'The Instagram Feed is a Pro element. Upgrade to Pro to show it on the site.';
    } elseif ($get('source', 'account') === 'manual') {
        foreach ((array) ($s['items'] ?? []) as $row) {
            if (!is_array($row) || trim((string) ($row['image'] ?? '')) === '') continue;
            $items[] = ['image' => (string) $row['image'], 'permalink' => trim((string) ($row['link'] ?? '')),
                        'caption' => (string) ($row['caption'] ?? ''), 'type' => 'IMAGE'];
        }
        $profile['username'] = ltrim(trim((string) $get('username', '')), '@');
        if (!$items) $notice = 'Add pictures in General → Pictures.';
    } elseif ($get('source', 'account') === 'embed') {
        // Instagram's own embed of each post: any public account, personal ones too, no token.
        foreach ((array) ($s['embed_posts'] ?? []) as $row) {
            $permalink = Instagram::embedPermalink(is_array($row) ? (string) ($row['url'] ?? '') : (string) $row);
            if ($permalink !== null) {
                $items[] = ['image' => '', 'permalink' => $permalink, 'caption' => '', 'type' => 'EMBED'];
            }
        }
        $profile['username'] = ltrim(trim((string) $get('username', '')), '@');
        if (!$items) $notice = 'Add the links of the posts to show in General → Post links (Share → Copy link in Instagram).';
    } elseif (!Instagram::connected()) {
        $notice = 'Connect your Instagram account in Appearance → Customizer → Instagram to show its posts here.';
    } else {
        $items = Instagram::media();
        $account = Instagram::account();
        $profile = ['username' => (string) ($account['username'] ?? ''), 'name' => (string) ($account['name'] ?? ''),
                    'avatar' => (string) ($account['profile_picture_url'] ?? ''), 'followers' => $account['followers_count'] ?? null];
        if (!$items) $notice = 'No posts to show yet. They are fetched in the background; check back in a minute (Appearance → Customizer → Instagram shows any error).';
    }

    $items = array_slice($items, 0, max(1, (int) $get('count', 8)));

    $src = function (string $url): string {
        if ($url === '') return '';
        if (preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, 'data:')) return $url;
        return asset(ltrim(str_starts_with($url, '/') ? $url : 'storage/'.$url, '/'));
    };

    // ── layout ──────────────────────────────────────────────────────────
    $uid      = 'fni-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($el['id'] ?? uniqid()));
    $layout   = $get('layout', 'grid') === 'carousel' ? 'carousel' : 'grid';
    $cols     = max(1, min(8, (int) $get('columns', 4)));
    $colsT    = max(1, min(8, (int) $get('columns_tablet', $cols)));
    $colsM    = max(1, min(8, (int) $get('columns_mobile', $colsT)));
    // The canvas is always desktop-wide, so its device switch picks the column count directly.
    if (($previewDevice ?? null) === 'tablet')     { $cols = $colsT; }
    elseif (($previewDevice ?? null) === 'mobile') { $cols = $colsM; }
    $gap      = max(0, (int) $get('gap', 8));
    $radius   = max(0, (int) $get('radius', 6));
    $ratio    = ['portrait' => '4 / 5', 'landscape' => '16 / 9'][$get('ratio', 'square')] ?? '1 / 1';
    $caption  = in_array($get('caption', 'hover'), ['hover', 'below', 'none'], true) ? $get('caption', 'hover') : 'hover';
    $capLen   = max(10, min(500, (int) $get('caption_length', 90)));
    $hover    = $get('hover', 'overlay');
    $overlay  = in_array($hover, ['overlay', 'both'], true);
    $zoom     = in_array($hover, ['zoom', 'both'], true);
    $target   = $get('link_target', '_blank') === '_self' ? '_self' : '_blank';
    $showHead = $get('header', 'show') !== 'hide' && $profile['username'] !== '';
    $arrows   = $layout === 'carousel' && $get('arrows', 'show') !== 'hide';
    $bpSm     = (int) get_cms_option('theme_small_screen_breakpoint', '800');
    $bpMed    = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');

    // ── colours and type ────────────────────────────────────────────────
    $overlayColor = $get('overlay_color', 'rgba(0,0,0,0.45)');
    $iconColor    = $get('icon_color', '#ffffff');
    $captionColor = $get('caption_color', $caption === 'below' ? '#374151' : '#ffffff');
    $nameColor    = $get('name_color', 'inherit');
    $btnStyle     = $get('button_style', 'gradient');
    $btnColor     = $get('button_color', '#2271b1');
    $btnText      = $get('button_text_color', '#ffffff');
    $typo = function (string $p) use ($get): string {
        $css = '';
        if (($f = $get($p.'_family')) && $f !== 'inherit') $css .= 'font-family:'.$f.';';
        if ($v = $get($p.'_size'))        $css .= 'font-size:'.(is_numeric($v) ? $v.'px' : $v).';';
        if ($v = $get($p.'_weight'))      $css .= 'font-weight:'.$v.';';
        if ($v = $get($p.'_line_height')) $css .= 'line-height:'.$v.';';
        if (($v = $get($p.'_letter_spacing')) !== null) $css .= 'letter-spacing:'.(is_numeric($v) ? $v.'px' : $v).';';
        if (($v = $get($p.'_transform')) && $v !== 'none') $css .= 'text-transform:'.$v.';';
        return $css;
    };
    $fonts = [];
    foreach (['caption_typo', 'name_typo'] as $p) {
        $f = trim(explode(',', (string) $get($p.'_family', ''))[0], " '\"");
        if ($f !== '' && strtolower($f) !== 'inherit') $fonts[$f] = true;
    }

    // Margin, per device, in a scoped rule so the media queries can win.
    $margin = function (string $suffix) use ($s): string {
        $css = '';
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $v = $s['margin_'.$side.$suffix] ?? '';
            if ($v === '' || $v === null) continue;
            $unit = $s['margin_'.$side.'_unit'.$suffix] ?? ($s['margin_'.$side.'_unit'] ?? 'px');
            $css .= 'margin-'.$side.':'.$v.$unit.';';
        }
        return $css;
    };

    $followers = $profile['followers'] !== null ? Instagram::shortNumber((int) $profile['followers']) : null;
@endphp

@if($notice !== null && $isPreview)
<div style="width:100%;padding:26px 20px;text-align:center;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;color:#64748b;font-size:13px;line-height:1.5;">
    <div style="font-size:24px;margin-bottom:6px;color:#d6249f;"><i class="fab fa-instagram"></i></div>
    {{ $notice }}
    @if(Instagram::licensed() && $get('source', 'account') === 'account' && !Instagram::connected())
    <div style="margin-top:10px;">
        <a href="{{ route('admin.customizer.index', ['section' => 'instagram']) }}" target="_blank" rel="noopener"
           style="display:inline-block;padding:7px 14px;border-radius:6px;background:#2271b1;color:#fff;text-decoration:none;font-weight:600;">Set up Instagram</a>
        <a href="https://falconcms.github.io/falconcms/guide/instagram-feed" target="_blank" rel="noopener"
           style="display:inline-block;margin-left:8px;color:#2271b1;text-decoration:underline;">Setup guide</a>
    </div>
    @endif
</div>
@elseif($items)
@foreach(array_keys($fonts) as $font)
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', $font) }}:wght{{ '@' }}400;600;700&display=swap">
@endforeach
@php
    $css  = "#{$uid}{width:100%;box-sizing:border-box;--fni-cols:{$cols};--fni-gap:{$gap}px;" . $margin('') . "}";
    $css .= "#{$uid} .fni-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}";
    $css .= "#{$uid} .fni-avatar{width:48px;height:48px;border-radius:50%;padding:2px;background:linear-gradient(45deg,#feda75,#fa7e1e,#d62976,#962fbf,#4f5bd5);flex:0 0 auto}";
    $css .= "#{$uid} .fni-avatar img,#{$uid} .fni-avatar span{width:100%;height:100%;border-radius:50%;border:2px solid #fff;object-fit:cover;display:flex;align-items:center;justify-content:center;background:#fff;color:#d62976;font-size:22px;box-sizing:border-box}";
    $css .= "#{$uid} .fni-who{flex:1 1 auto;min-width:0}";
    $css .= "#{$uid} .fni-name{font-weight:700;font-size:15px;color:{$nameColor};text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" . $typo('name_typo') . "}";
    $css .= "#{$uid} .fni-sub{font-size:12px;opacity:.65}";
    $btnCss = match ($btnStyle) {
        'solid'   => "background:{$btnColor};color:{$btnText};border:1px solid {$btnColor};",
        'outline' => "background:transparent;color:{$btnColor};border:1px solid {$btnColor};",
        default   => "background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);color:{$btnText};border:0;",
    };
    $css .= "#{$uid} .fni-follow{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none;line-height:1.2;transition:opacity .2s,transform .2s;{$btnCss}}";
    $css .= "#{$uid} .fni-follow:hover{opacity:.9;transform:translateY(-1px)}";
    $css .= "#{$uid} .fni-wrap{position:relative}";
    if ($layout === 'carousel') {
        $css .= "#{$uid} .fni-track{display:flex;gap:var(--fni-gap);overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;scrollbar-width:none}";
        $css .= "#{$uid} .fni-track::-webkit-scrollbar{display:none}";
        $css .= "#{$uid} .fni-item{flex:0 0 calc((100% - (var(--fni-cols) - 1) * var(--fni-gap)) / var(--fni-cols));scroll-snap-align:start}";
        $css .= "#{$uid} .fni-arrow{position:absolute;top:50%;transform:translateY(-50%);width:36px;height:36px;border-radius:50%;border:0;background:rgba(255,255,255,.92);color:#111;box-shadow:0 2px 10px rgba(0,0,0,.18);cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2;transition:opacity .2s}";
        $css .= "#{$uid} .fni-arrow:hover{opacity:.85}#{$uid} .fni-prev{left:8px}#{$uid} .fni-next{right:8px}";
    } else {
        $css .= "#{$uid} .fni-track{display:grid;grid-template-columns:repeat(var(--fni-cols),minmax(0,1fr));gap:var(--fni-gap)}";
    }
    $css .= "#{$uid} .fni-item{min-width:0}";
    $css .= "#{$uid} .fni-link{display:block;position:relative;overflow:hidden;border-radius:{$radius}px;aspect-ratio:{$ratio};background:#f1f5f9;color:inherit;text-decoration:none}";
    $css .= "#{$uid} .fni-link img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .45s ease}";
    if ($zoom) $css .= "#{$uid} .fni-link:hover img,#{$uid} .fni-link:focus-visible img{transform:scale(1.07)}";
    $css .= "#{$uid} .fni-badge{position:absolute;top:8px;right:8px;color:{$iconColor};filter:drop-shadow(0 1px 2px rgba(0,0,0,.45));line-height:0}";
    $css .= "#{$uid} .fni-over{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:12px;text-align:center;background:{$overlayColor};color:{$iconColor};opacity:0;transition:opacity .3s ease}";
    if ($overlay || $caption === 'hover') $css .= "#{$uid} .fni-link:hover .fni-over,#{$uid} .fni-link:focus-visible .fni-over{opacity:1}";
    $css .= "#{$uid} .fni-over .fni-cap{color:{$captionColor};font-size:13px;line-height:1.45;display:-webkit-box;-webkit-line-clamp:5;-webkit-box-orient:vertical;overflow:hidden;" . $typo('caption_typo') . "}";
    $css .= "#{$uid} .fni-below{margin-top:6px;color:{$captionColor};font-size:13px;line-height:1.45;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;" . $typo('caption_typo') . "}";
    if (!$isPreview) {
        $css .= "@media(min-width:" . ($bpSm + 1) . "px) and (max-width:{$bpMed}px){#{$uid}{--fni-cols:{$colsT};" . $margin('_tablet') . "}}";
        $css .= "@media(max-width:{$bpSm}px){#{$uid}{--fni-cols:{$colsM};" . $margin('_mobile') . "}}";
    }
    $igIcon = '<svg width="%1$s" height="%1$s" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2c3.2 0 3.6 0 4.8.1 1.2.1 1.8.2 2.2.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.4 1 .4 2.2.1 1.3.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 1.2-.2 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1 .4-2.2.4-1.3.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-1.2-.1-1.8-.2-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.8-.9-1.4-.2-.4-.4-1-.4-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.8c.1-1.2.2-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1-.4 2.2-.4C8.4 2.2 8.8 2.2 12 2.2zm0 1.8c-3.1 0-3.5 0-4.7.1-1.1.1-1.7.2-2.1.4-.5.2-.9.4-1.3.8-.4.4-.6.8-.8 1.3-.2.4-.3 1-.4 2.1C2.6 9.9 2.6 10.3 2.6 12s0 2.1.1 3.3c.1 1.1.2 1.7.4 2.1.2.5.4.9.8 1.3.4.4.8.6 1.3.8.4.2 1 .3 2.1.4 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c1.1-.1 1.7-.2 2.1-.4.5-.2.9-.4 1.3-.8.4-.4.6-.8.8-1.3.2-.4.3-1 .4-2.1.1-1.2.1-1.6.1-3.3s0-2.1-.1-3.3c-.1-1.1-.2-1.7-.4-2.1-.2-.5-.4-.9-.8-1.3-.4-.4-.8-.6-1.3-.8-.4-.2-1-.3-2.1-.4C15.5 4 15.1 4 12 4zm0 3.1a4.9 4.9 0 1 1 0 9.8 4.9 4.9 0 0 1 0-9.8zm0 8.1a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4zm6.2-8.3a1.1 1.1 0 1 1-2.3 0 1.1 1.1 0 0 1 2.3 0z"/></svg>';
    $playIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg>';
    $albumIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 3h11a3 3 0 0 1 3 3v11h-2V6a1 1 0 0 0-1-1H7z"/><rect x="3" y="7" width="14" height="14" rx="2"/></svg>';
    $profileUrl = $profile['username'] !== '' ? 'https://www.instagram.com/' . rawurlencode($profile['username']) . '/' : '';

    // Instagram's embedded posts. Its card asks for at least 326px and a white frame; in a
    // narrower column it is let shrink rather than spill over the next one.
    $hasEmbeds = in_array('EMBED', array_column($items, 'type'), true);
    if ($hasEmbeds) {
        $css .= "#{$uid} .fni-embed .instagram-media,#{$uid} .fni-embed iframe{min-width:0 !important;width:100% !important;max-width:100% !important;margin:0 !important}";
        $css .= "#{$uid} .fni-embed .instagram-media{background:#fff;border:1px solid #dbdbdb !important;border-radius:{$radius}px !important;box-shadow:none !important;padding:0 !important}";
        $css .= "#{$uid} .fni-embed .instagram-media>a{display:block;padding:40px 16px;text-align:center;color:#3897f0;font-size:14px;font-weight:600;text-decoration:none}";
        $css .= "#{$uid} .fni-embed-ph{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;aspect-ratio:4 / 5;padding:16px;text-align:center;border:1px solid #dbdbdb;border-radius:{$radius}px;background:#fafafa;color:#262626;font-size:13px;overflow:hidden}";
        $css .= "#{$uid} .fni-embed-ph-icon{color:#d62976;margin-bottom:4px}#{$uid} .fni-embed-ph span{color:#737373;font-size:12px;word-break:break-all}#{$uid} .fni-embed-ph em{color:#a8a8a8;font-size:11px}";
    }
@endphp
{!! '<style>' . $css . '</style>' !!}
<div id="{{ $uid }}" class="falcon-instagram-feed fni-{{ $layout }}">
    @if($showHead)
    <div class="fni-head">
        <a class="fni-avatar" href="{{ $profileUrl }}" target="{{ $target }}" rel="noopener" aria-label="&#64;{{ $profile['username'] }} on Instagram">
            @if($profile['avatar'] !== '')
                <img src="{{ $src($profile['avatar']) }}" alt="" width="44" height="44" loading="lazy" decoding="async">
            @else
                <span>{!! sprintf($igIcon, 22) !!}</span>
            @endif
        </a>
        <div class="fni-who">
            <a class="fni-name" href="{{ $profileUrl }}" target="{{ $target }}" rel="noopener">&#64;{{ $profile['username'] }}</a>
            @php $sub = implode(' · ', array_filter([$profile['name'], $followers !== null ? $followers.' followers' : ''])); @endphp
            @if($sub !== '')
            <div class="fni-sub">{{ $sub }}</div>
            @endif
        </div>
        <a class="fni-follow" href="{{ $profileUrl }}" target="{{ $target }}" rel="noopener">{!! sprintf($igIcon, 15) !!}<span>{{ $get('follow_text', 'Follow on Instagram') }}</span></a>
    </div>
    @endif

    <div class="fni-wrap">
        @if($arrows && count($items) > $cols)
        <button type="button" class="fni-arrow fni-prev" aria-label="Previous" onclick="var t=this.parentNode.querySelector('.fni-track');t.scrollBy({left:-t.clientWidth,behavior:'smooth'})"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></button>
        @endif
        <div class="fni-track">
            @foreach($items as $item)
            @php
                $capText = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $item['caption'])));
                $capShort = Str::limit($capText, $capLen);
                $alt = $capText !== '' ? Str::limit($capText, 120) : ('Instagram post' . ($profile['username'] !== '' ? ' by @' . $profile['username'] : ''));
                $href = $item['permalink'] !== '' ? $item['permalink'] : $profileUrl;
                $tag = $href !== '' ? 'a' : 'div';
            @endphp
            @if($item['type'] === 'EMBED')
            <div class="fni-item fni-embed">
                @if($isPreview)
                    {{-- The canvas cannot run Instagram's script, so each post is a stand-in card. --}}
                    <div class="fni-embed-ph">
                        <span class="fni-embed-ph-icon">{!! sprintf($igIcon, 30) !!}</span>
                        <strong>Instagram post</strong>
                        <span>{{ preg_replace('#^https://www\.instagram\.com/#', 'instagram.com/', $item['permalink']) }}</span>
                        <em>Shown by Instagram on the page</em>
                    </div>
                @else
                    <blockquote class="instagram-media" data-instgrm-permalink="{{ $item['permalink'] }}?utm_source=ig_embed&amp;utm_campaign=loading" data-instgrm-version="14"
                                @if($caption !== 'none') data-instgrm-captioned @endif>
                        <a href="{{ $item['permalink'] }}" target="_blank" rel="noopener">View this post on Instagram</a>
                    </blockquote>
                @endif
            </div>
            @continue
            @endif
            <div class="fni-item">
                <{{ $tag }} class="fni-link" @if($href !== '') href="{{ $href }}" target="{{ $target }}" rel="noopener" @endif>
                    <img src="{{ $src($item['image']) }}" alt="{{ $alt }}" loading="lazy" decoding="async">
                    @if($item['type'] === 'VIDEO')
                        <span class="fni-badge">{!! $playIcon !!}</span>
                    @elseif($item['type'] === 'CAROUSEL_ALBUM')
                        <span class="fni-badge">{!! $albumIcon !!}</span>
                    @endif
                    @if($overlay || ($caption === 'hover' && $capShort !== ''))
                    <span class="fni-over">
                        @if($caption === 'hover' && $capShort !== '')
                            <span class="fni-cap">{{ $capShort }}</span>
                        @else
                            {!! sprintf($igIcon, 26) !!}
                        @endif
                    </span>
                    @endif
                </{{ $tag }}>
                @if($caption === 'below' && $capShort !== '')
                <div class="fni-below">{{ $capShort }}</div>
                @endif
            </div>
            @endforeach
        </div>
        @if($arrows && count($items) > $cols)
        <button type="button" class="fni-arrow fni-next" aria-label="Next" onclick="var t=this.parentNode.querySelector('.fni-track');t.scrollBy({left:t.clientWidth,behavior:'smooth'})"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
        @endif
    </div>
</div>
@if($hasEmbeds && !$isPreview)
{{-- Instagram's embed script, once per page however many feeds use it; on a page where it has
     already loaded, it is asked to draw the posts that just arrived. --}}
<script>(function(){if(window.instgrm&&window.instgrm.Embeds){window.instgrm.Embeds.process();return;}if(document.getElementById('fni-instagram-embed-js'))return;var s=document.createElement('script');s.id='fni-instagram-embed-js';s.async=true;s.src='https://www.instagram.com/embed.js';document.body.appendChild(s);})();</script>
@endif
@endif
