<?php

namespace FalconCms\Core\Support;

/**
 * The Dual Button element — two calls to action that belong together.
 *
 * "Start free / See pricing", "Download / Read the docs": a pair that is read as one
 * choice, either joined into a single shape with an optional "OR" between the halves, or
 * set side by side with a gap.
 *
 * Everything the two renderers have to agree on lives here, once:
 *   - FIELDS    every setting, its kind and its default. The shortcode converter walks
 *               this list in both directions, and the builder drops a new element with
 *               these defaults.
 *   - VARS      which setting feeds which CSS custom property. The page (PHP) and the
 *               canvas (JS, handed this table as JSON) both do nothing but copy values
 *               across, so neither holds a rule of its own that the other could lack.
 *   - css()     the one stylesheet, included by both. All the logic — a cleared
 *               background is transparent, a cleared border follows the background, a
 *               joined pair shares its inner corners — is written there, in CSS.
 *
 * Renderers:
 *   resources/views/frontend/builder/elements/dual-button.blade.php
 *   resources/views/admin/falcon-builder/partials/components/elements/dual-button.blade.php
 */
class DualButton
{
    /** The two buttons, by the prefix their settings carry. */
    public const SIDES = ['primary', 'secondary'];

    /**
     * Setting => [kind, default].
     *
     * Kinds: 'text' (free text an author may clear on purpose), 'str' (a keyword, a
     * colour or a URL), 'num' (a number, or blank to follow the stylesheet) and 'bool'.
     *
     * A setting that is absent means "never set" and takes the default. One that is
     * present and empty was cleared, and stays cleared: a cleared label hides its button,
     * a cleared background is transparent (that is how an outline button is made).
     */
    public const FIELDS = [
        // Content
        'primaryText' => ['text', 'Get Started'],
        'primaryUrl' => ['str', '#'],
        'primaryTarget' => ['str', '_self'],
        'primaryIcon' => ['str', ''],
        'primaryIconPosition' => ['str', 'left'],
        'secondaryText' => ['text', 'Learn More'],
        'secondaryUrl' => ['str', '#'],
        'secondaryTarget' => ['str', '_self'],
        'secondaryIcon' => ['str', ''],
        'secondaryIconPosition' => ['str', 'left'],
        'showConnector' => ['bool', true],
        'connectorText' => ['text', 'OR'],
        // Layout
        'gap' => ['num', 0],
        'align' => ['str', 'center'],
        'fullWidth' => ['bool', false],
        'equalWidth' => ['bool', true],
        'stackOn' => ['str', 'never'],
        'hoverEffect' => ['str', 'none'],
        // Colours
        'primaryBg' => ['str', '#2271b1'],
        'primaryColor' => ['str', '#ffffff'],
        'primaryBorderColor' => ['str', ''],
        'primaryHoverBg' => ['str', '#1a5a96'],
        'primaryHoverColor' => ['str', ''],
        'primaryHoverBorderColor' => ['str', ''],
        'secondaryBg' => ['str', '#1d2327'],
        'secondaryColor' => ['str', '#ffffff'],
        'secondaryBorderColor' => ['str', ''],
        'secondaryHoverBg' => ['str', '#3c434a'],
        'secondaryHoverColor' => ['str', ''],
        'secondaryHoverBorderColor' => ['str', ''],
        'connectorBg' => ['str', '#ffffff'],
        'connectorColor' => ['str', '#1d2327'],
        // Shape
        'connectorSize' => ['num', 30],
        'borderWidth' => ['num', 2],
        'radius' => ['num', 6],
        'iconGap' => ['num', 8],
    ];

    /**
     * Setting => [custom property, kind]. 'size' is a length (a bare number is px),
     * 'color' is passed through. An empty setting sets nothing, and the stylesheet's
     * own fallback for that property applies.
     */
    public const VARS = [
        'gap' => ['--fdb-gap', 'size'],
        'radius' => ['--fdb-radius', 'size'],
        'borderWidth' => ['--fdb-bw', 'size'],
        'iconGap' => ['--fdb-icon-gap', 'size'],
        'connectorSize' => ['--fdb-or-size', 'size'],
        'primaryBg' => ['--fdb-p-bg', 'color'],
        'primaryColor' => ['--fdb-p-color', 'color'],
        'primaryBorderColor' => ['--fdb-p-border', 'color'],
        'primaryHoverBg' => ['--fdb-p-hbg', 'color'],
        'primaryHoverColor' => ['--fdb-p-hcolor', 'color'],
        'primaryHoverBorderColor' => ['--fdb-p-hborder', 'color'],
        'secondaryBg' => ['--fdb-s-bg', 'color'],
        'secondaryColor' => ['--fdb-s-color', 'color'],
        'secondaryBorderColor' => ['--fdb-s-border', 'color'],
        'secondaryHoverBg' => ['--fdb-s-hbg', 'color'],
        'secondaryHoverColor' => ['--fdb-s-hcolor', 'color'],
        'secondaryHoverBorderColor' => ['--fdb-s-hborder', 'color'],
        'connectorBg' => ['--fdb-or-bg', 'color'],
        'connectorColor' => ['--fdb-or-color', 'color'],
    ];

    /** Padding sides => [custom property, default px]. Responsive, with a unit per side. */
    public const PADDING = [
        'Top' => ['--fdb-pt', 14],
        'Right' => ['--fdb-pr', 28],
        'Bottom' => ['--fdb-pb', 14],
        'Left' => ['--fdb-pl', 28],
    ];

    /** Margin above and below the pair, in px, when none was ever set. */
    public const MARGIN = ['Top' => 10, 'Bottom' => 10];

    /**
     * Sizes that can differ per device ({key}_tablet, {key}_mobile). Each is one of VARS,
     * so a tablet or mobile value is the same custom property in a media query.
     */
    public const RESPONSIVE = ['borderWidth', 'radius', 'iconGap'];

    public const ALIGNMENTS = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'];

    public const STACK_ON = ['never', 'mobile', 'tablet'];

    public const HOVER_EFFECTS = ['none', 'lift', 'grow'];

    /**
     * One-click looks for the Design tab. A preset is not stored: choosing one writes
     * these settings, and every one of them stays editable afterwards.
     */
    public const PRESETS = [
        'joined' => [
            'name' => 'Joined', 'gap' => 0, 'radius' => 6, 'borderWidth' => 2, 'showConnector' => true,
            'primaryBg' => '#2271b1', 'primaryColor' => '#ffffff', 'primaryBorderColor' => '',
            'primaryHoverBg' => '#1a5a96', 'primaryHoverColor' => '', 'primaryHoverBorderColor' => '',
            'secondaryBg' => '#1d2327', 'secondaryColor' => '#ffffff', 'secondaryBorderColor' => '',
            'secondaryHoverBg' => '#3c434a', 'secondaryHoverColor' => '', 'secondaryHoverBorderColor' => '',
        ],
        'outline' => [
            'name' => 'Solid + Outline', 'gap' => 12, 'radius' => 6, 'borderWidth' => 2, 'showConnector' => false,
            'primaryBg' => '#2271b1', 'primaryColor' => '#ffffff', 'primaryBorderColor' => '',
            'primaryHoverBg' => '#1a5a96', 'primaryHoverColor' => '', 'primaryHoverBorderColor' => '',
            'secondaryBg' => '', 'secondaryColor' => '#2271b1', 'secondaryBorderColor' => '#2271b1',
            'secondaryHoverBg' => '#2271b1', 'secondaryHoverColor' => '#ffffff', 'secondaryHoverBorderColor' => '',
        ],
        'pill' => [
            'name' => 'Pill', 'gap' => 0, 'radius' => 999, 'borderWidth' => 2, 'showConnector' => true,
            'primaryBg' => '#111827', 'primaryColor' => '#ffffff', 'primaryBorderColor' => '',
            'primaryHoverBg' => '#374151', 'primaryHoverColor' => '', 'primaryHoverBorderColor' => '',
            'secondaryBg' => '#f3f4f6', 'secondaryColor' => '#111827', 'secondaryBorderColor' => '',
            'secondaryHoverBg' => '#e5e7eb', 'secondaryHoverColor' => '', 'secondaryHoverBorderColor' => '',
        ],
        'ghost' => [
            'name' => 'Solid + Text', 'gap' => 8, 'radius' => 6, 'borderWidth' => 2, 'showConnector' => false,
            'primaryBg' => '#2271b1', 'primaryColor' => '#ffffff', 'primaryBorderColor' => '',
            'primaryHoverBg' => '#1a5a96', 'primaryHoverColor' => '', 'primaryHoverBorderColor' => '',
            'secondaryBg' => '', 'secondaryColor' => '#2271b1', 'secondaryBorderColor' => '',
            'secondaryHoverBg' => '#eef5fb', 'secondaryHoverColor' => '', 'secondaryHoverBorderColor' => '',
        ],
    ];

    /** Every setting's default, as a new element in the builder starts out. */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::FIELDS as $key => [, $default]) {
            $out[$key] = $default;
        }
        foreach (self::PADDING as $side => [, $px]) {
            $out['padding'.$side] = $px;
            $out['padding'.$side.'Unit'] = 'px';
        }
        foreach (self::MARGIN as $side => $px) {
            $out['margin'.$side] = $px;
            $out['margin'.$side.'Unit'] = 'px';
        }

        return $out + ['cssClass' => '', 'cssId' => ''];
    }

    /**
     * Everything the builder's canvas needs, as one table for @json: the same lists
     * this class works from, so the canvas cannot hold a default or a mapping of its own.
     *
     * @return array<string, mixed>
     */
    public static function builderConfig(): array
    {
        return [
            'fields' => self::FIELDS,
            'vars' => self::VARS,
            'padding' => self::PADDING,
            'margin' => self::MARGIN,
            'alignments' => self::ALIGNMENTS,
            'presets' => self::PRESETS,
            'defaults' => self::defaults(),
            'responsive' => self::RESPONSIVE,
        ];
    }

    /**
     * A setting's value: what was saved, or the default when it was never set.
     * An empty string is a saved value (the author cleared the field).
     *
     * @param  array<string, mixed>  $s
     */
    public static function get(array $s, string $key)
    {
        if (array_key_exists($key, $s) && $s[$key] !== null) {
            return $s[$key];
        }

        return self::FIELDS[$key][1] ?? null;
    }

    /**
     * A keyword setting, held to the values it may take.
     *
     * @param  array<string, mixed>  $s
     * @param  list<string>  $allowed
     */
    public static function choice(array $s, string $key, array $allowed): string
    {
        $value = (string) self::get($s, $key);

        return in_array($value, $allowed, true) ? $value : (string) self::FIELDS[$key][1];
    }

    /**
     * One button's dynamic-source settings.
     *
     * The builder's dynamic picker writes a source and its options under fixed names —
     * dynamic_source, dynamic_before, dynamic_link_tax_slug … — onto whatever object it is
     * given. A Button has one label and one link, so it hands over its own settings. Two
     * buttons in one element would overwrite each other's options there, so each gets an
     * object of its own ({side}Dynamic) and the picker writes into that instead.
     *
     * @param  array<string, mixed>  $s
     * @return array<string, mixed>
     */
    public static function dynamic(array $s, string $side): array
    {
        $dyn = $s[$side.'Dynamic'] ?? null;

        return is_array($dyn) ? $dyn : [];
    }

    /**
     * The buttons to draw. One with neither a label nor an icon is left out, so a pair
     * with one side cleared is an ordinary single button rather than a button and a stub.
     *
     * A label or link bound to a dynamic source is resolved against $post (the post being
     * viewed when null). A source that gives nothing falls back to what was typed, as the
     * Button does.
     *
     * @param  array<string, mixed>  $s
     * @return list<array{side: string, text: string, url: string, target: string, icon: string, iconPosition: string}>
     */
    public static function buttons(array $s, $post = null): array
    {
        $resolve = function_exists('falcon_resolve_dynamic_value') && function_exists('falcon_dynamic_config');
        $out = [];
        foreach (self::SIDES as $side) {
            $dyn = self::dynamic($s, $side);
            $text = trim((string) self::get($s, $side.'Text'));
            $url = trim((string) self::get($s, $side.'Url'));

            if ($resolve && !empty($dyn['dynamic_source'])) {
                // A label is plain text: a source that answers in HTML (an author bio)
                // gives its words, not its tags.
                $value = trim(strip_tags((string) falcon_resolve_dynamic_value((string) $dyn['dynamic_source'], $post, falcon_dynamic_config($dyn))));
                $text = $value !== '' ? $value : $text;
            }
            if ($resolve && !empty($dyn['link_dynamic_source'])) {
                $value = trim((string) falcon_resolve_dynamic_value((string) $dyn['link_dynamic_source'], $post, falcon_dynamic_config($dyn, 'link')));
                $url = $value !== '' ? $value : $url;
            }

            $icon = CalloutStyles::safeIcon((string) self::get($s, $side.'Icon'));
            if ($text === '' && $icon === '') {
                continue;
            }
            // A URL pasted from an HTML snippet arrives as "?a=1&amp;b=2"; decoded here so
            // the template's own escaping encodes it exactly once (as the Button does).
            $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $out[] = [
                'side' => $side,
                'text' => $text,
                'url' => self::safeHref($url),
                'target' => self::get($s, $side.'Target') === '_blank' ? '_blank' : '_self',
                'icon' => $icon,
                'iconPosition' => self::get($s, $side.'IconPosition') === 'right' ? 'right' : 'left',
            ];
        }

        return $out;
    }

    /**
     * A link an author typed, or '' when it is not one a button should carry.
     * http(s), relative paths, #anchors, mailto:, tel: and sms: pass; javascript:, data:
     * and the like do not.
     */
    public static function safeHref(string $url): string
    {
        if ($url === '' || $url[0] === '#' || $url[0] === '?') {
            return $url;
        }
        if (preg_match('/^(mailto|tel|sms):/i', $url)) {
            return $url;
        }

        return falcon_safe_url($url);
    }

    /** The connector's text, or '' when there is none to show (it needs two buttons). */
    public static function connector(array $s, int $buttonCount): string
    {
        if ($buttonCount < 2 || !self::get($s, 'showConnector')) {
            return '';
        }

        return trim((string) self::get($s, 'connectorText'));
    }

    /**
     * The modifier classes for the wrapper. $device is null on the page, where stacking
     * is a media query; the canvas passes its current device and gets the stacked state
     * itself (mirrored by fcDbClasses() in the builder's scripts).
     *
     * @param  array<string, mixed>  $s
     * @return list<string>
     */
    public static function classes(array $s, ?string $device = null): array
    {
        $classes = ['fdb'];
        if (self::isZero(self::get($s, 'gap'))) {
            $classes[] = 'fdb--joined';
        }
        if (self::get($s, 'equalWidth')) {
            $classes[] = 'fdb--equal';
        }
        if (self::get($s, 'fullWidth')) {
            $classes[] = 'fdb--full';
        }
        $hover = self::choice($s, 'hoverEffect', self::HOVER_EFFECTS);
        if ($hover !== 'none') {
            $classes[] = 'fdb--'.$hover;
        }
        $stack = self::choice($s, 'stackOn', self::STACK_ON);
        if ($device === null) {
            if ($stack !== 'never') {
                $classes[] = 'fdb--stack-'.$stack;
            }
        } elseif (($stack === 'mobile' && $device === 'mobile') || ($stack === 'tablet' && $device !== 'desktop')) {
            $classes[] = 'fdb--stacked';
        }

        return $classes;
    }

    /**
     * The wrapper's custom properties for the desktop state (tablet and mobile overrides
     * are media queries, written by the frontend view).
     *
     * @param  array<string, mixed>  $s
     * @return array<string, string>
     */
    public static function vars(array $s): array
    {
        $out = [];
        foreach (self::VARS as $key => [$prop, $kind]) {
            $value = self::cssValue(self::get($s, $key), $kind);
            if ($value !== '') {
                $out[$prop] = $value;
            }
        }
        foreach (self::PADDING as $side => [$prop, $default]) {
            // Blank is the default here too: the box's placeholder shows it.
            $raw = $s['padding'.$side] ?? '';
            $raw = $raw === '' ? $default : $raw;
            $value = self::cssValue(getUnitVal($raw, self::unit($s['padding'.$side.'Unit'] ?? 'px')));
            if ($value !== '') {
                $out[$prop] = $value;
            }
        }
        $out['--fdb-justify'] = self::ALIGNMENTS[self::get($s, 'align')] ?? 'center';

        return $out;
    }

    /** A unit token, or px for anything that is not one. */
    public static function unit($unit): string
    {
        return preg_replace('/[^a-zA-Z%]/', '', (string) $unit) ?: 'px';
    }

    /**
     * A setting as a CSS value that cannot leave the declaration it is written into.
     * What survives is enough for a colour in any notation (#hex, rgb(), hsl(), var(),
     * a name) and a length; ; { } < > and quotes do not.
     */
    public static function cssValue($value, string $kind = 'color'): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }
        if ($kind === 'size' && is_numeric($value)) {
            return max(0, $value + 0).'px';
        }

        return trim(preg_replace('/[^A-Za-z0-9#(),.%\s_\/+*-]/', '', $value) ?? '');
    }

    /** True for 0, "0", "0px" and blank — a pair with no gap is a joined pair. */
    private static function isZero($value): bool
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' || ((float) $value == 0.0 && preg_match('/^[0-9.]+[a-z%]*$/i', $value) === 1);
    }

    /**
     * Whether a layout contains a Dual Button anywhere (containers, columns, nested
     * rows, card layouts). The page asks before printing the stylesheet, so a layout
     * without one carries none of it.
     *
     * @param  mixed  $node
     */
    public static function layoutUses($node): bool
    {
        if (!is_array($node)) {
            return false;
        }
        if (($node['type'] ?? null) === 'dual_button') {
            return true;
        }
        foreach ($node as $value) {
            if (is_array($value) && self::layoutUses($value)) {
                return true;
            }
        }

        return false;
    }

    /** The rules for a stacked pair, under whichever selector says it is stacked. */
    private static function stackedCss(string $p): string
    {
        return "{$p} .fdb-group{grid-auto-flow:row}"
            ."{$p}.fdb--joined .fdb-cell--first:not(.fdb-cell--last) .fdb-btn{border-start-end-radius:var(--fdb-radius,6px);border-end-start-radius:0;border-end-end-radius:0;margin-inline-end:0;margin-bottom:calc(var(--fdb-bw,2px) / -2)}"
            ."{$p}.fdb--joined .fdb-cell--last:not(.fdb-cell--first) .fdb-btn{border-end-start-radius:var(--fdb-radius,6px);border-start-start-radius:0;border-start-end-radius:0;margin-inline-start:0;margin-top:calc(var(--fdb-bw,2px) / -2)}"
            ."{$p} .fdb-or{top:auto;bottom:calc(var(--fdb-gap,0px) / -2);inset-inline-end:50%;transform:translate(50%,50%)}"
            ."[dir=rtl] {$p} .fdb-or{transform:translate(-50%,50%)}";
    }

    /**
     * The element's stylesheet. Included once by the page (frontend/builder/render) and
     * once by the builder (falcon-builder/partials/styles), so the canvas previews with
     * the rules that ship.
     *
     * Each button resolves its own colours through private properties (--_bg, --_c …),
     * which is where the fallbacks are written: no background is transparent, no border
     * colour follows the background, and a hover colour left empty keeps the resting one.
     *
     * @param  int  $bpSm  the theme's Small Screen breakpoint (mobile ends here)
     * @param  int  $bpMed  the theme's Medium Screen breakpoint (tablet ends here)
     */
    public static function css(int $bpSm = 800, int $bpMed = 1100): string
    {
        $css = '.fdb{display:flex;width:100%;justify-content:var(--fdb-justify,center);font-size:16px;font-weight:600;line-height:1.25}'
            .'.fdb-group{position:relative;display:inline-grid;grid-auto-flow:column;grid-auto-columns:auto;gap:var(--fdb-gap,0px);max-width:100%}'
            .'.fdb--equal .fdb-group{grid-auto-columns:minmax(0,1fr)}'
            .'.fdb--full .fdb-group{display:grid;width:100%}'
            .'.fdb-cell{position:relative;display:flex;min-width:0}'
            .'.fdb-cell:hover{z-index:1}'
            .'.fdb-btn{flex:1 1 auto;display:inline-flex;align-items:center;justify-content:center;gap:var(--fdb-icon-gap,8px);box-sizing:border-box;min-width:0;'
                .'padding:var(--fdb-pt,14px) var(--fdb-pr,28px) var(--fdb-pb,14px) var(--fdb-pl,28px);'
                .'border:var(--fdb-bw,2px) solid var(--_b);border-radius:var(--fdb-radius,6px);background:var(--_bg);color:var(--_c);'
                .'font:inherit;letter-spacing:inherit;text-transform:inherit;text-align:center;text-decoration:none;cursor:pointer;'
                .'transition:background-color .2s ease,color .2s ease,border-color .2s ease,transform .2s ease,box-shadow .2s ease}'
            .'span.fdb-btn{cursor:default}'
            .'.fdb-btn>i,.fdb-btn>svg{flex:0 0 auto}'
            .'.fdb-label{min-width:0}';

        foreach (['primary' => 'p', 'secondary' => 's'] as $side => $v) {
            $css .= ".fdb-btn--{$side}{"
                ."--_bg:var(--fdb-{$v}-bg,transparent);"
                ."--_c:var(--fdb-{$v}-color,inherit);"
                ."--_b:var(--fdb-{$v}-border,var(--_bg));"
                ."--_hbg:var(--fdb-{$v}-hbg,var(--_bg));"
                ."--_hc:var(--fdb-{$v}-hcolor,var(--_c));"
                ."--_hb:var(--fdb-{$v}-hborder,var(--fdb-{$v}-border,var(--_hbg)))}";
        }

        $css .= '.fdb-btn:hover,.fdb-btn:focus-visible{background:var(--_hbg);color:var(--_hc);border-color:var(--_hb);text-decoration:none}'
            .'.fdb-btn:focus-visible{outline:2px solid currentColor;outline-offset:2px}'
            .'.fdb--lift .fdb-btn:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(0,0,0,.16)}'
            .'.fdb--grow .fdb-btn:hover{transform:scale(1.04)}'
            // Joined: the pair is one shape, so the corners where the halves meet are square
            // and the two borders sit on each other rather than side by side. Each button
            // gives half the overlap, so a pair of equal widths stays equal.
            .'.fdb--joined .fdb-cell--first:not(.fdb-cell--last) .fdb-btn{border-start-end-radius:0;border-end-end-radius:0;margin-inline-end:calc(var(--fdb-bw,2px) / -2)}'
            .'.fdb--joined .fdb-cell--last:not(.fdb-cell--first) .fdb-btn{border-start-start-radius:0;border-end-start-radius:0;margin-inline-start:calc(var(--fdb-bw,2px) / -2)}'
            // The connector hangs off the first cell's far edge, centred on the seam (or
            // on the middle of the gap), wherever unequal labels put that.
            .'.fdb-or{position:absolute;z-index:2;top:50%;inset-inline-end:calc(var(--fdb-gap,0px) / -2);transform:translate(50%,-50%);'
                .'width:var(--fdb-or-size,30px);height:var(--fdb-or-size,30px);border-radius:50%;display:flex;align-items:center;justify-content:center;'
                .'background:var(--fdb-or-bg,#fff);color:var(--fdb-or-color,#1d2327);font-size:calc(var(--fdb-or-size,30px) * .36);font-weight:700;'
                .'line-height:1;letter-spacing:0;text-transform:uppercase;white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.18);pointer-events:none}'
            .'[dir=rtl] .fdb-or{transform:translate(-50%,-50%)}';

        $css .= self::stackedCss('.fdb--stacked');
        $css .= "@media(max-width:{$bpSm}px){".self::stackedCss('.fdb--stack-mobile').'}';
        $css .= "@media(max-width:{$bpMed}px){".self::stackedCss('.fdb--stack-tablet').'}';
        $css .= '@media(prefers-reduced-motion:reduce){.fdb-btn{transition:none}.fdb-btn:hover{transform:none}}';

        return $css;
    }
}
