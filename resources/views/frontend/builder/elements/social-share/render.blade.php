@php
    /*
    |--------------------------------------------------------------------------
    | Social Share — renderer (page and builder canvas)
    |--------------------------------------------------------------------------
    | Networks, share links, what is shared and the designs' stylesheet live in
    | FalconCms\Core\Support\SocialShare. Every button is a real link built here, so it
    | works with no script at all; the script below only adds the popup window, Copy
    | link, Print and the device's own share menu.
    |
    | The builder canvas fetches this same template ('preview' => 'server', with
    | $builderPreview set). There the buttons are drawn but not wired, and a floating
    | bar is shown in place, with a note.
    |
    | Visibility, CSS class and ID sit on the wrapper frontend/builder/column puts around
    | custom elements.
    */
    use FalconCms\Core\Support\ElementStyle;
    use FalconCms\Core\Support\SocialShare;

    $s = $s ?? ($el['settings'] ?? []);
    $s = is_array($s) ? $s : [];
    $isPreview = !empty($builderPreview);
    $device = $isPreview ? ($previewDevice ?? null) : null;

    // Whatever the surrounding template knows about the post: a Post Card, Post Grid or
    // Layout sets these for the post it is drawing.
    $target = SocialShare::target($s, [
        'postPermalink' => $postPermalink ?? null,
        'postTitle' => $postTitle ?? null,
        'postFeaturedImage' => $postFeaturedImage ?? null,
        'post' => $post ?? null,
    ]);
    $buttons = SocialShare::buttons($s, $target);

    $uid = 'fss-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($el['id'] ?? uniqid('', false)));
    [$bpSm, $bpMed] = ElementStyle::breakpoints();

    $design = ElementStyle::pick($s['design'] ?? 'brand', SocialShare::DESIGNS, 'brand');
    $custom = ($s['color_mode'] ?? 'brand') === 'custom';
    $position = ElementStyle::pick($s['position'] ?? 'inline', ['inline', 'float_left', 'float_right'], 'inline');
    $floating = $position !== 'inline' && !$isPreview;
    $iconsOnly = ($s['labels'] ?? 'show') === 'hide' || $design === 'icons';
    $popup = ($s['open_in'] ?? 'popup') !== 'tab';

    $sizes = ['small' => [32, 12, 13], 'medium' => [40, 16, 14], 'large' => [50, 22, 16]];
    [$h, $px, $fs] = $sizes[ElementStyle::pick($s['size'] ?? 'medium', array_keys($sizes), 'medium')];
    $radius = ['rounded' => max(4, (int) round($h * .15)).'px', 'pill' => '999px', 'square' => '0'][ElementStyle::pick($s['shape'] ?? 'rounded', ['rounded', 'pill', 'square'], 'rounded')];

    $align = (string) ElementStyle::forDevice($s, 'align', $device, 'flex-start');
    $align = in_array($align, ['flex-start', 'center', 'flex-end'], true) ? $align : 'flex-start';

    $vars = "--fss-h:{$h}px;--fss-px:{$px}px;--fss-fs:{$fs}px;--fss-r:{$radius};"
        .'--fss-gap:'.ElementStyle::int($s['gap'] ?? null, 8, 0, 30).'px;'
        .'--fss-justify:'.$align.';';
    if ($custom) {
        $vars .= '--fss-c:'.ElementStyle::color($s['btn_color'] ?? '', '#2271b1').';'
            .'--fss-fg:'.ElementStyle::color($s['btn_text'] ?? '', '#ffffff').';';
        if (($v = ElementStyle::color($s['btn_hover'] ?? '')) !== '') $vars .= '--fss-hbg:'.$v.';';
        if (($v = ElementStyle::color($s['btn_hover_text'] ?? '')) !== '') $vars .= '--fss-hfg:'.$v.';';
    }
    if (($v = ElementStyle::color($s['heading_color'] ?? '')) !== '') $vars .= '--fss-heading:'.$v.';';

    $css = SocialShare::css($bpSm);
    $css .= "#{$uid}{{$vars}" . ($floating ? '' : ElementStyle::margin($s)) . '}';
    if (($t = ElementStyle::typo($s, 'heading_typo')) !== '') $css .= "#{$uid} .fss-heading{{$t}}";
    if (($t = ElementStyle::typo($s, 'label_typo')) !== '') $css .= "#{$uid} .fss-btn{{$t}}";
    if ($isPreview && ($device === 'tablet' || $device === 'mobile')) {
        $css .= "#{$uid}{" . ElementStyle::margin($s, '_tablet') . ($device === 'mobile' ? ElementStyle::margin($s, '_mobile') : '') . '}';
    }
    if (!$isPreview) {
        $bpSm1 = $bpSm + 1;
        $tabletAlign = ElementStyle::forDevice($s, 'align', 'tablet', $align);
        $mobileAlign = ElementStyle::forDevice($s, 'align', 'mobile', $tabletAlign);
        $okAlign = fn ($a) => in_array($a, ['flex-start', 'center', 'flex-end'], true) ? "--fss-justify:{$a};" : '';
        $css .= "@media(min-width:{$bpSm1}px) and (max-width:{$bpMed}px){#{$uid}{" . ($floating ? '' : ElementStyle::margin($s, '_tablet')) . $okAlign($tabletAlign) . '}}';
        $css .= "@media(max-width:{$bpSm}px){#{$uid}{" . ($floating ? '' : ElementStyle::margin($s, '_tablet') . ElementStyle::margin($s, '_mobile')) . $okAlign($mobileAlign) . '}}';
    }

    $classes = 'fss fss--' . $design
        . ($custom ? ' fss--custom' : '')
        . ($iconsOnly ? ' fss--icons-only' : '')
        . (!$floating && ($s['layout'] ?? 'row') === 'column' ? ' fss--column' : '')
        . (!$floating && ($s['stretch'] ?? 'auto') === 'fill' ? ' fss--fill' : '')
        . ($floating ? ' fss--float fss--' . str_replace('_', '-', $position) : '');
    $heading = trim((string) ($s['heading'] ?? ''));
    $copied = trim((string) ($s['copied_text'] ?? '')) ?: 'Link copied!';
@endphp
@if($buttons)
{!! '<style>' . $css . '</style>' !!}
<div id="{{ $uid }}" class="{{ $classes }}"
     @unless($isPreview) data-fss data-fss-popup="{{ $popup ? '1' : '0' }}" data-fss-copied="{{ $copied }}" @endunless
     @if($floating) role="complementary" aria-label="{{ $heading !== '' ? $heading : 'Share' }}" @endif>
    @if($heading !== '')<span class="fss-heading">{{ $heading }}</span>@endif
    <ul class="fss-list">
        @foreach($buttons as $b)
        @php
            $style = $custom ? '' : '--fss-c:' . $b['color'] . ';';
            $aria = $b['key'] === 'copy' || $b['key'] === 'print' || $b['key'] === 'native' ? $b['label'] : 'Share on ' . $b['label'];
        @endphp
        {{-- The device's own share menu exists only where the browser has one; it is
             revealed by the script, so a desktop without it never shows a dead button. --}}
        <li @if($b['key'] === 'native' && !$isPreview) hidden data-fss-native @endif>
            @if($b['href'] !== '')
            <a class="fss-btn fss-btn--{{ $b['key'] }}" href="{{ $isPreview ? '#' : $b['href'] }}"
               @if(!$isPreview && !str_starts_with($b['href'], 'mailto:')) target="_blank" rel="noopener nofollow" data-fss-open @endif
               @if($style !== '') style="{{ $style }}" @endif
               aria-label="{{ $aria }}" @if($iconsOnly) title="{{ $b['label'] }}" @endif>
                <i class="{{ $b['icon'] }}" aria-hidden="true"></i>
                @unless($iconsOnly)<span class="fss-label">{{ $b['label'] }}</span>@endunless
            </a>
            @else
            <button type="button" class="fss-btn fss-btn--{{ $b['key'] }}" data-fss-action="{{ $b['key'] }}"
                    data-fss-url="{{ $target['url'] }}" data-fss-title="{{ $target['title'] }}"
                    @if($style !== '') style="{{ $style }}" @endif
                    aria-label="{{ $aria }}" @if($iconsOnly) title="{{ $b['label'] }}" @endif>
                <i class="{{ $b['icon'] }}" aria-hidden="true"></i>
                @unless($iconsOnly)<span class="fss-label">{{ $b['label'] }}</span>@endunless
            </button>
            @endif
        </li>
        @endforeach
    </ul>
</div>
@if($isPreview && $position !== 'inline')
<div style="margin-top:6px;color:#94a3b8;font-size:12px;line-height:1.4;">Shown here in place. On the page it floats at the {{ $position === 'float_left' ? 'left' : 'right' }} edge of the screen, and along the bottom on phones.</div>
@endif
@if($isPreview && in_array('native', array_column($buttons, 'key'), true))
<div style="margin-top:6px;color:#94a3b8;font-size:12px;line-height:1.4;">"More" appears only on devices with a share menu (most phones).</div>
@endif
@unless($isPreview)
{{-- Repeatable: one listener for the whole document, set up by the first copy. --}}
<script>
(function () {
    'use strict';
    if (window.__falconShare) return;
    window.__falconShare = true;

    function reveal() {
        if (!navigator.share) return;
        document.querySelectorAll('[data-fss] [data-fss-native]').forEach(function (li) { li.hidden = false; });
    }

    function toast(text) {
        var t = document.createElement('div');
        t.className = 'fss-toast';
        t.setAttribute('role', 'status');
        t.textContent = text;
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, 1800);
    }

    function copy(text) {
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.cssText = 'position:fixed;left:-9999px;top:0;';
            document.body.appendChild(ta);
            ta.select();
            ta.setSelectionRange(0, ta.value.length);
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) {}
            ta.remove();
            ok ? resolve() : reject();
        });
    }

    document.addEventListener('click', function (e) {
        var root = e.target.closest && e.target.closest('[data-fss]');
        if (!root) return;

        var link = e.target.closest('a[data-fss-open]');
        if (link && root.getAttribute('data-fss-popup') === '1' && window.innerWidth > 640) {
            var w = 600, h = 560;
            var left = Math.max(0, (window.screenX || 0) + (window.outerWidth - w) / 2);
            var top = Math.max(0, (window.screenY || 0) + (window.outerHeight - h) / 2);
            var win = window.open(link.href, 'falcon-share', 'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top);
            if (win) { e.preventDefault(); try { win.opener = null; win.focus(); } catch (err) {} }
            return;
        }

        var btn = e.target.closest('button[data-fss-action]');
        if (!btn) return;
        e.preventDefault();
        var url = btn.getAttribute('data-fss-url') || location.href;
        var title = btn.getAttribute('data-fss-title') || document.title;
        var action = btn.getAttribute('data-fss-action');

        if (action === 'copy') {
            copy(url).then(function () {
                btn.classList.add('is-done');
                toast(root.getAttribute('data-fss-copied') || 'Link copied!');
                setTimeout(function () { btn.classList.remove('is-done'); }, 1800);
            }, function () { window.prompt('Copy this link:', url); });
        } else if (action === 'print') {
            window.print();
        } else if (action === 'native' && navigator.share) {
            navigator.share({ title: title, url: url }).catch(function () {});
        }
    });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', reveal); else reveal();
    // Elements added after load (a section loaded by a script) get the button too.
    window.addEventListener('load', reveal);
})();
</script>
@endunless
@elseif($isPreview)
<div style="padding:18px;border:1px dashed #c9d0d8;border-radius:8px;color:#79838F;font-size:13px;text-align:center;line-height:1.5;">
    No share buttons chosen.<br><span style="font-size:12px;opacity:.85;">Tick some in General → Share buttons.</span>
</div>
@endif
