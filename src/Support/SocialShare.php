<?php

namespace FalconCms\Core\Support;

use Throwable;

/**
 * The Social Share builder element (Pro, builder_pro): buttons that share the page they
 * are on to social networks.
 *
 * "The page they are on" is worked out where the element is drawn, so one element in a
 * header, a footer or a Layout shares whichever page is being viewed, and one inside a
 * Post Card shares that card's post:
 *   1. a post the surrounding template hands down (a Post Card / Post Grid / Layout sets
 *      $postPermalink and $postTitle),
 *   2. else the post being viewed,
 *   3. else the address of the current page and its title.
 *
 * The links are built on the server, so every button works without JavaScript as a plain
 * link; the small script in the template only opens them in a centred popup, copies the
 * link and offers the device's own share sheet.
 *
 * Renderer: resources/views/frontend/builder/elements/social-share/render.blade.php
 * (also the builder canvas preview, 'preview' => 'server').
 */
final class SocialShare
{
    public const TYPE = 'social_share';

    public const DESIGNS = ['brand', 'outline', 'icons', 'minimal'];

    /**
     * Network => [label, icon, brand colour, share URL pattern]. In the pattern {url},
     * {title}, {text}, {image} and {tags}/{via} are replaced by encoded values; null
     * means the button is handled by the script (copy, the device's share sheet, print).
     */
    public const NETWORKS = [
        'facebook' => ['Facebook', 'fa-brands fa-facebook-f', '#1877F2', 'https://www.facebook.com/sharer/sharer.php?u={url}'],
        'x_twitter' => ['X', 'fa-brands fa-x-twitter', '#000000', 'https://x.com/intent/post?url={url}&text={title}{tags}{via}'],
        'linkedin' => ['LinkedIn', 'fa-brands fa-linkedin-in', '#0A66C2', 'https://www.linkedin.com/sharing/share-offsite/?url={url}'],
        'whatsapp' => ['WhatsApp', 'fa-brands fa-whatsapp', '#25D366', 'https://api.whatsapp.com/send?text={text}'],
        'telegram' => ['Telegram', 'fa-brands fa-telegram', '#26A5E4', 'https://t.me/share/url?url={url}&text={title}'],
        'pinterest' => ['Pinterest', 'fa-brands fa-pinterest-p', '#BD081C', 'https://pinterest.com/pin/create/button/?url={url}&media={image}&description={title}'],
        'reddit' => ['Reddit', 'fa-brands fa-reddit-alien', '#FF4500', 'https://www.reddit.com/submit?url={url}&title={title}'],
        'tumblr' => ['Tumblr', 'fa-brands fa-tumblr', '#36465D', 'https://www.tumblr.com/widgets/share/tool?canonicalUrl={url}&title={title}'],
        'threads' => ['Threads', 'fa-brands fa-threads', '#000000', 'https://www.threads.net/intent/post?text={text}'],
        'bluesky' => ['Bluesky', 'fa-brands fa-bluesky', '#0085FF', 'https://bsky.app/intent/compose?text={text}'],
        'email' => ['Email', 'fa fa-envelope', '#6B7280', 'mailto:?subject={title}&body={url}'],
        'copy' => ['Copy link', 'fa fa-link', '#4B5563', null],
        'print' => ['Print', 'fa fa-print', '#4B5563', null],
        'native' => ['More', 'fa fa-share-nodes', '#2271b1', null],
    ];

    /** On falcon_builder_elements. */
    public static function register(array $elements): array
    {
        $networks = [];
        foreach (self::NETWORKS as $key => [$label]) {
            $networks[$key] = $key === 'native' ? 'More… (the device\'s share menu)' : $label;
        }

        $elements[self::TYPE] = [
            'type' => self::TYPE,
            'name' => 'Social Share',
            'icon' => 'fa fa-share-nodes',
            'template' => 'falcon-cms::frontend.builder.elements.social-share.render',
            'preview' => 'server',
            'fields' => [
                // ── General ─────────────────────────────────────────────
                'networks' => ['type' => 'checkbox', 'label' => 'Share buttons', 'options' => $networks,
                    'default' => ['facebook', 'x_twitter', 'linkedin', 'whatsapp', 'copy']],
                'share_source' => ['type' => 'select', 'label' => 'What to share', 'default' => 'auto', 'options' => [
                    'auto' => 'The page or post it is on',
                    'site' => 'The home page',
                    'custom' => 'A link I enter',
                ], 'description' => 'In a header, footer or Layout it shares whichever page is open; inside a Post Card, that card\'s post.'],
                'custom_url' => ['type' => 'text', 'label' => 'Link to share', 'default' => '', 'placeholder' => 'https://…',
                    'condition' => ['field' => 'share_source', 'value' => 'custom']],
                'share_text' => ['type' => 'text', 'label' => 'Share text', 'default' => '', 'placeholder' => 'Empty = the page title',
                    'description' => 'Used by X, WhatsApp, Telegram, Pinterest, Reddit and email.'],
                'hashtags' => ['type' => 'text', 'label' => 'Hashtags for X', 'default' => '', 'placeholder' => 'laravel, cms'],
                'via' => ['type' => 'text', 'label' => 'Via (X username)', 'default' => '', 'placeholder' => 'yourbrand'],
                'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => '', 'placeholder' => 'e.g. Share this'],
                'labels' => ['type' => 'select', 'label' => 'Button text', 'default' => 'show', 'options' => [
                    'show' => 'Network name', 'hide' => 'Icons only',
                ]],
                'copied_text' => ['type' => 'text', 'label' => '"Copied" message', 'default' => 'Link copied!'],
                'open_in' => ['type' => 'select', 'label' => 'Open share windows', 'default' => 'popup', 'options' => [
                    'popup' => 'In a small popup', 'tab' => 'In a new tab',
                ]],

                // ── Design ──────────────────────────────────────────────
                'design' => ['type' => 'visual_select', 'label' => 'Design', 'default' => 'brand', 'tab' => 'design', 'options' => [
                    'brand' => ['label' => 'Filled', 'icon' => 'fa fa-square'],
                    'outline' => ['label' => 'Outline', 'icon' => 'fa-regular fa-square'],
                    'icons' => ['label' => 'Round icons', 'icon' => 'fa fa-circle'],
                    'minimal' => ['label' => 'Minimal', 'icon' => 'fa fa-minus'],
                ]],
                'color_mode' => ['type' => 'select', 'label' => 'Colours', 'default' => 'brand', 'tab' => 'design', 'options' => [
                    'brand' => 'Each network\'s own colour', 'custom' => 'My colours',
                ]],
                'btn_color' => ['type' => 'color', 'label' => 'Button colour', 'default' => '#2271b1', 'tab' => 'design',
                    'condition' => ['field' => 'color_mode', 'value' => 'custom']],
                'btn_text' => ['type' => 'color', 'label' => 'Text / icon colour', 'default' => '#ffffff', 'tab' => 'design',
                    'condition' => ['field' => 'color_mode', 'value' => 'custom']],
                'btn_hover' => ['type' => 'color', 'label' => 'Hover colour', 'default' => '', 'hint' => 'A little darker', 'tab' => 'design',
                    'condition' => ['field' => 'color_mode', 'value' => 'custom']],
                'btn_hover_text' => ['type' => 'color', 'label' => 'Hover text colour', 'default' => '', 'hint' => 'Unchanged', 'tab' => 'design',
                    'condition' => ['field' => 'color_mode', 'value' => 'custom']],
                'position' => ['type' => 'select', 'label' => 'Position', 'default' => 'inline', 'tab' => 'design', 'options' => [
                    'inline' => 'Where it is placed',
                    'float_left' => 'Floating, left edge of the screen',
                    'float_right' => 'Floating, right edge of the screen',
                ], 'description' => 'Floating buttons stay at the side while the page scrolls, and become a bar along the bottom on phones.'],
                'layout' => ['type' => 'select', 'label' => 'Direction', 'default' => 'row', 'tab' => 'design', 'options' => [
                    'row' => 'Side by side', 'column' => 'Stacked',
                ], 'condition' => ['field' => 'position', 'value' => 'inline']],
                'stretch' => ['type' => 'select', 'label' => 'Width', 'default' => 'auto', 'tab' => 'design', 'options' => [
                    'auto' => 'Fit the text', 'fill' => 'Share the full width',
                ], 'condition' => ['field' => 'position', 'value' => 'inline']],
                'align' => ['type' => 'select', 'label' => 'Alignment', 'default' => 'flex-start', 'tab' => 'design', 'responsive' => true,
                    'options' => ['flex-start' => 'Left', 'center' => 'Center', 'flex-end' => 'Right'],
                    'condition' => ['field' => 'position', 'value' => 'inline']],
                'size' => ['type' => 'select', 'label' => 'Size', 'default' => 'medium', 'tab' => 'design', 'options' => [
                    'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large',
                ]],
                'shape' => ['type' => 'select', 'label' => 'Shape', 'default' => 'rounded', 'tab' => 'design', 'options' => [
                    'rounded' => 'Rounded', 'pill' => 'Pill / circle', 'square' => 'Square',
                ]],
                'gap' => ['type' => 'slider', 'label' => 'Space between', 'default' => 8, 'min' => 0, 'max' => 30, 'step' => 1, 'unit' => 'px', 'tab' => 'design'],
                'heading_color' => ['type' => 'color', 'label' => 'Heading colour', 'default' => '', 'hint' => 'Text colour', 'tab' => 'design'],
                'heading_typo' => ['type' => 'typography', 'label' => 'Heading typography', 'tab' => 'design'],
                'label_typo' => ['type' => 'typography', 'label' => 'Button typography', 'tab' => 'design'],
                'margin' => ['type' => 'dimensions', 'label' => 'Margin', 'tab' => 'design'],
            ],
        ];

        return $elements;
    }

    /**
     * What is being shared: ['url', 'title', 'image'].
     *
     * @param  array<string, mixed>  $s
     * @param  array<string, mixed>  $context  what the template sees: postPermalink, postTitle,
     *                                         postFeaturedImage (Post Card / Layout), post
     * @return array{url: string, title: string, image: string}
     */
    public static function target(array $s, array $context = []): array
    {
        $source = ElementStyle::pick($s['share_source'] ?? 'auto', ['auto', 'site', 'custom'], 'auto');
        $siteTitle = (string) get_cms_option('site_title', get_cms_option('site_name', config('app.name', '')));
        $url = '';
        $title = '';
        $image = '';

        if ($source === 'custom') {
            $url = self::absolute(trim(html_entity_decode((string) ($s['custom_url'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $title = $siteTitle;
        } elseif ($source === 'site') {
            $url = url('/');
            $title = $siteTitle;
        } else {
            $post = $context['post'] ?? null;
            if (!is_object($post)) {
                try {
                    $shared = view()->getShared();
                    $post = $shared['current_post'] ?? ($shared['post'] ?? null);
                } catch (Throwable $e) {
                    $post = null;
                }
            }
            if (!empty($context['postPermalink'])) {
                $url = (string) $context['postPermalink'];
                $title = (string) ($context['postTitle'] ?? '');
                $image = (string) ($context['postFeaturedImage'] ?? '');
            } elseif (is_object($post) && !empty($post->id) && function_exists('get_falcon_permalink')) {
                try {
                    $url = (string) get_falcon_permalink($post);
                } catch (Throwable $e) {
                    $url = '';
                }
                $title = (string) ($post->title ?? '');
                $image = (string) ($post->featured_image ?? '');
            }
            if ($url === '') {
                $url = url()->current();
                $title = $title !== '' ? $title : $siteTitle;
            }
        }

        if ($url === '') {
            $url = url('/');
        }
        $url = self::absolute($url);
        if ($image !== '' && !preg_match('#^https?://#i', $image)) {
            $image = asset('storage/'.ltrim($image, '/'));
        }
        if ($image === '') {
            $default = (string) get_cms_option('seo_default_image', '');
            $image = $default === '' ? '' : (preg_match('#^https?://#i', $default) ? $default : asset('storage/'.ltrim($default, '/')));
        }

        $text = trim((string) ($s['share_text'] ?? ''));

        return ['url' => $url, 'title' => $text !== '' ? $text : ($title !== '' ? $title : $siteTitle), 'image' => $image];
    }

    /** A link made absolute against the site, or '' when it is not a web address. */
    public static function absolute(string $url): string
    {
        if ($url === '') {
            return '';
        }
        // Another scheme (javascript:, data:, mailto: …) is not a page to share.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && !preg_match('#^https?://#i', $url)) {
            return '';
        }
        if (str_starts_with($url, '//')) {
            $url = request()->getScheme().':'.$url;
        } elseif (!preg_match('#^https?://#i', $url)) {
            $url = url('/'.ltrim($url, '/'));
        }

        return preg_match('#^https?://[^\s<>"]+$#i', $url) ? $url : '';
    }

    /**
     * The buttons to draw, in the order of NETWORKS, each with its link.
     *
     * @param  array<string, mixed>  $s
     * @param  array{url: string, title: string, image: string}  $target
     * @return list<array{key: string, label: string, icon: string, color: string, href: string}>
     */
    public static function buttons(array $s, array $target): array
    {
        $chosen = $s['networks'] ?? null;
        $chosen = is_array($chosen) ? $chosen : (is_string($chosen) && $chosen !== '' ? explode(',', $chosen) : ['facebook', 'x_twitter', 'linkedin', 'whatsapp', 'copy']);
        $chosen = array_map('strval', $chosen);

        $tags = implode(',', array_filter(array_map(
            fn ($t) => preg_replace('/[^\p{L}\p{N}_]/u', '', $t),
            explode(',', (string) ($s['hashtags'] ?? ''))
        )));
        $via = preg_replace('/[^A-Za-z0-9_]/', '', ltrim(trim((string) ($s['via'] ?? '')), '@'));

        $enc = fn (string $v) => rawurlencode($v);
        $replace = [
            '{url}' => $enc($target['url']),
            '{title}' => $enc($target['title']),
            '{text}' => $enc(trim($target['title'].' '.$target['url'])),
            '{image}' => $enc($target['image']),
            '{tags}' => $tags !== '' ? '&hashtags='.$enc($tags) : '',
            '{via}' => $via !== '' ? '&via='.$enc($via) : '',
        ];

        $out = [];
        foreach (self::NETWORKS as $key => [$label, $icon, $color, $pattern]) {
            if (!in_array($key, $chosen, true)) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'color' => $color,
                'href' => $pattern === null ? '' : strtr($pattern, $replace),
            ];
        }

        return $out;
    }

    /**
     * The designs' shared rules. Per-element values arrive as custom properties
     * (--fss-gap, --fss-justify …) and each button carries its colour in --fss-c.
     */
    public static function css(int $bpSm): string
    {
        return '.fss{display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;width:100%;box-sizing:border-box;line-height:1.2;justify-content:var(--fss-justify,flex-start)}'
            .'.fss *,.fss *::before,.fss *::after{box-sizing:border-box}'
            .'.fss-heading{font-weight:700;font-size:15px;color:var(--fss-heading,inherit)}'
            .'.fss-list{display:flex;flex-wrap:wrap;gap:var(--fss-gap,8px);margin:0;padding:0;list-style:none}'
            .'.fss--column .fss-list{flex-direction:column;align-items:stretch}'
            .'.fss--column{flex-direction:column;align-items:var(--fss-justify,flex-start)}'
            .'.fss--fill .fss-list{flex:1 1 100%}.fss--fill .fss-list>li{flex:1 1 0}.fss--fill .fss-btn{width:100%}'
            .'.fss-list>li{margin:0;padding:0;list-style:none}'
            .'.fss-list>li[hidden]{display:none!important}'
            .'.fss-btn{--_bg:var(--fss-c);--_fg:#fff;display:inline-flex;align-items:center;justify-content:center;gap:8px;height:var(--fss-h,40px);min-width:var(--fss-h,40px);padding:0 var(--fss-px,16px);'
                .'border:2px solid var(--_bg);border-radius:var(--fss-r,6px);background:var(--_bg);color:var(--_fg);font:inherit;font-size:var(--fss-fs,14px);font-weight:600;line-height:1;'
                .'text-decoration:none;white-space:nowrap;cursor:pointer;transition:filter .2s ease,transform .2s ease,background-color .2s ease,color .2s ease,border-color .2s ease}'
            .'.fss-btn:hover,.fss-btn:focus-visible{filter:brightness(.92);transform:translateY(-1px);color:var(--_fg);text-decoration:none}'
            .'.fss-btn:focus-visible{outline:2px solid var(--_bg);outline-offset:2px}'
            .'.fss-btn i{font-size:1.1em;line-height:1;width:1.1em;text-align:center}'
            .'.fss--icons-only .fss-btn{padding:0;width:var(--fss-h,40px)}'
            .'.fss--outline .fss-btn{background:transparent;color:var(--_bg)}'
            .'.fss--outline .fss-btn:hover,.fss--outline .fss-btn:focus-visible{background:var(--_bg);color:var(--_fg);filter:none}'
            .'.fss--icons .fss-btn{padding:0;width:var(--fss-h,40px)}'
            .'.fss--icons .fss-label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}'
            .'.fss--minimal .fss-btn{background:transparent;border-color:transparent;color:var(--_bg);padding:0 6px;min-width:0;height:auto;min-height:var(--fss-h,40px)}'
            .'.fss--minimal .fss-btn:hover,.fss--minimal .fss-btn:focus-visible{filter:none;opacity:.75}'
            .'.fss-btn.is-done .fss-label{opacity:.95}'
            .'.fss--custom .fss-btn{--_fg:var(--fss-fg,#fff)}'
            .'.fss--custom .fss-btn:hover,.fss--custom .fss-btn:focus-visible{filter:none;background:var(--fss-hbg,var(--_bg));border-color:var(--fss-hbg,var(--_bg));color:var(--fss-hfg,var(--_fg))}'
            .'.fss--custom.fss--minimal .fss-btn:hover,.fss--custom.fss--outline .fss-btn:hover{color:var(--fss-hfg,var(--_fg))}'
            // Floating: a column fixed to the side of the screen; a bar along the bottom on phones.
            .'.fss--float{position:fixed;z-index:9990;top:50%;transform:translateY(-50%);width:auto;flex-direction:column;gap:0}'
            .'.fss--float .fss-heading{display:none}'
            .'.fss--float .fss-list{flex-direction:column;gap:var(--fss-gap,8px)}'
            .'.fss--float-left{left:12px}.fss--float-right{right:12px}'
            ."@media(max-width:{$bpSm}px){"
                .'.fss--float{top:auto;bottom:0;left:0;right:0;transform:none;width:100%;padding:8px;background:rgba(255,255,255,.96);box-shadow:0 -2px 12px rgba(0,0,0,.12)}'
                .'.fss--float .fss-list{flex-direction:row;flex-wrap:nowrap;width:100%;justify-content:center}'
                .'.fss--float .fss-list>li{flex:1 1 0;max-width:72px}.fss--float .fss-btn{width:100%;padding:0}'
                .'.fss--float .fss-label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}'
            .'}'
            .'.fss-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:9999;padding:9px 16px;border-radius:6px;background:#111827;color:#fff;font-size:14px;line-height:1.3;box-shadow:0 6px 20px rgba(0,0,0,.25);pointer-events:none}'
            .'@media(prefers-reduced-motion:reduce){.fss-btn{transition:none}.fss-btn:hover{transform:none}}';
    }
}
