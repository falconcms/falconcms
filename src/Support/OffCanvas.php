<?php

namespace FalconCms\Core\Support;

use Illuminate\Support\Str;
use Throwable;

/**
 * Off-canvas panels — slide-in drawers and popups designed in the builder.
 *
 * Each one is a builder layout plus a handful of panel settings (side, size, overlay,
 * animation, auto-open rules), kept as one JSON list in the `falcon_off_canvases` option the
 * same way mega menus and post cards are. The Builder Library lists them; the builder edits
 * the layout; the front end draws them from the `falcon_footer` action, so every theme that
 * calls the hook gets them without a template change.
 *
 * Opening one needs nothing but a link: any `<a href="#offcanvas-{slug}">` — a menu item, a
 * builder button, a link typed into a text block — or an element carrying
 * `data-falcon-offcanvas="{slug}"`. `#offcanvas-close` closes whichever is open.
 *
 * A page only carries the panels it can actually open. When the footer hook runs, the page
 * above it has already been written into the output buffer, so the buffer is searched for
 * each panel's trigger; a panel nothing points at (and that does not open by itself) costs
 * the page nothing at all.
 */
class OffCanvas
{
    const OPTION_KEY = 'falcon_off_canvases';

    /** Every panel setting with its default. Anything not listed here is dropped on save. */
    const DEFAULTS = [
        'position' => 'right',          // left | right | top | bottom | center
        'width' => 400,
        'width_unit' => 'px',           // px | % | vw
        'height' => 0,                  // 0 = fit the content (top/bottom/center)
        'height_unit' => 'px',          // px | vh
        'bg_color' => '#ffffff',
        'radius' => 0,
        'shadow' => '1',
        'animation' => 'slide',         // slide | fade | zoom | none
        'duration' => 350,
        'overlay' => '1',
        'overlay_color' => '#000000',
        'overlay_opacity' => 50,        // percent
        'overlay_blur' => 0,            // px
        'close_overlay' => '1',
        'close_esc' => '1',
        'close_button' => '1',
        'close_color' => '#1d2327',
        'close_size' => 22,
        'lock_scroll' => '1',
        'trigger' => 'none',            // none | load | scroll | exit
        'trigger_delay' => 3,           // seconds, for "load"
        'trigger_scroll' => 50,         // percent, for "scroll"
        'frequency' => 'session',       // always | session | days
        'frequency_days' => 7,
        'auto_pages' => 'all',          // all | include | exclude
        'auto_targets' => [],           // Layout Builder condition targets, plus path:/url/*
        'auto_desktop' => '1',
        'auto_tablet' => '1',
        'auto_mobile' => '1',
    ];

    const POSITIONS = ['left', 'right', 'top', 'bottom', 'center'];

    const ANIMATIONS = ['slide', 'fade', 'zoom', 'none'];

    const TRIGGERS = ['none', 'load', 'scroll', 'exit'];

    const FREQUENCIES = ['always', 'session', 'days'];

    const PAGE_RULES = ['all', 'include', 'exclude'];

    /**
     * What an "On Pages" entry may be: one of the Layout Builder's condition targets
     * (falcon_condition_target_matches() decides them), or a URL pattern typed by hand.
     */
    const TARGET_PATTERN = '/^(entire_site|home|search|404|all_archives|author_archive|(all|singular|archive|tax|taxonomy|term|author|post):[\w:-]+|path:\/[^\s<>"\']*)$/';

    // ── Storage ─────────────────────────────────────────────────────────────

    public static function all(): array
    {
        $raw = get_cms_option(self::OPTION_KEY, null);
        $list = $raw ? json_decode((string) $raw, true) : null;

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    public static function store(array $items): void
    {
        update_cms_option(self::OPTION_KEY, json_encode(array_values($items)));
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    /** Apply $fn to the panel with this id and save; false when there is no such panel. */
    public static function modify(string $id, callable $fn): bool
    {
        $items = self::all();
        foreach ($items as $i => $item) {
            if (($item['id'] ?? null) === $id) {
                $items[$i] = $fn($item);
                self::store($items);

                return true;
            }
        }

        return false;
    }

    /** A new panel entry, ready to be stored. */
    public static function make(string $name, array $config = []): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => self::uniqueSlug($name),
            'enabled' => true,
            'config' => [
                'layout' => is_array($config['layout'] ?? null) ? $config['layout'] : [],
                'settings' => self::settings($config['settings'] ?? []),
            ],
            'created_at' => now()->format('Y-m-d H:i'),
        ];
    }

    /**
     * A slug no other panel uses. The slug is the trigger (`#offcanvas-{slug}`), so it is fixed
     * at creation — renaming a panel later must not break the links already pointing at it.
     */
    public static function uniqueSlug(string $name, ?string $exceptId = null): string
    {
        $base = Str::slug($name) ?: 'panel';
        $taken = [];
        foreach (self::all() as $item) {
            if (($item['id'] ?? null) !== $exceptId && !empty($item['slug'])) {
                $taken[$item['slug']] = true;
            }
        }

        $slug = $base;
        for ($n = 2; isset($taken[$slug]); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    // ── Settings ────────────────────────────────────────────────────────────

    /** Settings with every key present, every value in range, and nothing unknown. */
    public static function settings($input): array
    {
        $in = is_array($input) ? $input : [];
        $s = [];
        foreach (self::DEFAULTS as $key => $default) {
            $s[$key] = array_key_exists($key, $in) && $in[$key] !== null ? $in[$key] : $default;
        }

        $pick = fn ($v, array $allowed, $default) => in_array($v, $allowed, true) ? $v : $default;
        $int = fn ($v, int $min, int $max) => max($min, min($max, (int) $v));
        $flag = fn ($v) => in_array($v, [true, 1, '1', 'true', 'on'], true) ? '1' : '0';
        $color = fn ($v, $default) => self::color($v) ?? $default;

        $s['position'] = $pick($s['position'], self::POSITIONS, 'right');
        $s['width_unit'] = $pick($s['width_unit'], ['px', '%', 'vw'], 'px');
        $s['width'] = $int($s['width'], 1, $s['width_unit'] === 'px' ? 3000 : 100);
        $s['height_unit'] = $pick($s['height_unit'], ['px', 'vh'], 'px');
        $s['height'] = $int($s['height'], 0, $s['height_unit'] === 'px' ? 3000 : 100);
        $s['bg_color'] = $color($s['bg_color'], '#ffffff');
        $s['radius'] = $int($s['radius'], 0, 200);
        $s['animation'] = $pick($s['animation'], self::ANIMATIONS, 'slide');
        $s['duration'] = $int($s['duration'], 0, 3000);
        $s['overlay_color'] = $color($s['overlay_color'], '#000000');
        $s['overlay_opacity'] = $int($s['overlay_opacity'], 0, 100);
        $s['overlay_blur'] = $int($s['overlay_blur'], 0, 30);
        $s['close_color'] = $color($s['close_color'], '#1d2327');
        $s['close_size'] = $int($s['close_size'], 10, 80);
        $s['trigger'] = $pick($s['trigger'], self::TRIGGERS, 'none');
        $s['trigger_delay'] = $int($s['trigger_delay'], 0, 600);
        $s['trigger_scroll'] = $int($s['trigger_scroll'], 1, 100);
        $s['frequency'] = $pick($s['frequency'], self::FREQUENCIES, 'session');
        $s['frequency_days'] = $int($s['frequency_days'], 1, 365);
        // Earlier saves kept a "Home page only" rule and a textarea of paths; both are
        // entries in the target list now.
        $targets = is_array($s['auto_targets']) ? $s['auto_targets'] : [];
        if ($s['auto_pages'] === 'home') {
            $s['auto_pages'] = 'include';
            $targets[] = 'home';
        }
        foreach (preg_split('/[\r\n,]+/', (string) ($in['auto_paths'] ?? '')) as $line) {
            if (trim($line) !== '') {
                $targets[] = 'path:/'.trim(trim($line), '/');
            }
        }
        $s['auto_pages'] = $pick($s['auto_pages'], self::PAGE_RULES, 'all');
        $s['auto_targets'] = array_slice(array_values(array_unique(array_filter(
            array_map(fn ($t) => is_string($t) ? trim($t) : '', $targets),
            fn ($t) => $t !== '' && strlen($t) <= 300 && preg_match(self::TARGET_PATTERN, $t)
        ))), 0, 100);
        foreach (['shadow', 'overlay', 'close_overlay', 'close_esc', 'close_button', 'lock_scroll',
            'auto_desktop', 'auto_tablet', 'auto_mobile'] as $key) {
            $s[$key] = $flag($s[$key]);
        }

        // Tablet and mobile overrides ({key}_tablet, {key}_mobile — the builder's usual naming).
        // Absent means "same as the size above". Each is checked against the values that will
        // actually apply on that device, so a mobile width of 100 is a valid 100% when mobile
        // switched to %, and clamped to 100 when it did not.
        foreach (['tablet', 'mobile'] as $device) {
            foreach (self::RESPONSIVE as $key) {
                $raw = $in[$key.'_'.$device] ?? null;
                // An unknown position or unit is no override at all: the device inherits.
                $enum = ['position' => self::POSITIONS, 'width_unit' => ['px', '%', 'vw'], 'height_unit' => ['px', 'vh']][$key] ?? null;
                if ($raw !== null && $raw !== '' && !is_array($raw) && ($enum === null || in_array($raw, $enum, true))) {
                    $s[$key.'_'.$device] = $raw;
                }
            }
            $resolved = self::forDevice($s, $device);
            foreach (self::RESPONSIVE as $key) {
                if (array_key_exists($key.'_'.$device, $s)) {
                    $s[$key.'_'.$device] = self::geometry($key, $s[$key.'_'.$device], $resolved);
                }
            }
        }

        return $s;
    }

    /** Settings that can differ per device. */
    const RESPONSIVE = ['position', 'width', 'width_unit', 'height', 'height_unit', 'radius', 'close_size'];

    /** One responsive value, brought into range for the device it applies to. */
    private static function geometry(string $key, $value, array $on)
    {
        $int = fn ($v, int $min, int $max) => max($min, min($max, (int) $v));

        return match ($key) {
            'position', 'width_unit', 'height_unit' => $value, // checked on the way in
            'width' => $int($value, 1, $on['width_unit'] === 'px' ? 3000 : 100),
            'height' => $int($value, 0, $on['height_unit'] === 'px' ? 3000 : 100),
            'radius' => $int($value, 0, 200),
            'close_size' => $int($value, 10, 80),
        };
    }

    /**
     * The settings as they apply on one device: mobile falls back to tablet, tablet to
     * desktop — the same cascade the builder's getResponsiveVal() uses.
     */
    public static function forDevice(array $s, string $device): array
    {
        $chain = match ($device) {
            'mobile' => ['_mobile', '_tablet'],
            'tablet' => ['_tablet'],
            default => [],
        };
        foreach (self::RESPONSIVE as $key) {
            foreach ($chain as $suffix) {
                if (isset($s[$key.$suffix]) && $s[$key.$suffix] !== '') {
                    $s[$key] = $s[$key.$suffix];
                    break;
                }
            }
        }

        return $s;
    }

    /**
     * The panel's geometry — where it sits, how big it is, how it moves in — as CSS for one
     * panel, with a media query for each device whose geometry differs from the one above
     * it. Position is in here rather than in a class because it, too, can change per device:
     * a right-hand drawer on desktop can be a full-width bottom sheet on a phone.
     */
    public static function panelCss(string $selector, array $s, int $bpSm, int $bpMed): string
    {
        $css = self::geometryCss($selector, self::forDevice($s, 'desktop'));
        $previous = $css;
        foreach ([['tablet', $bpMed], ['mobile', $bpSm]] as [$device, $bp]) {
            $rules = self::geometryCss($selector, self::forDevice($s, $device));
            if ($rules !== $previous) {
                $css .= '@media (max-width:'.$bp.'px){'.$rules.'}';
            }
            $previous = $rules;
        }

        return $css;
    }

    private static function geometryCss(string $sel, array $s): string
    {
        $pos = $s['position'];
        $w = $s['width'].$s['width_unit'];
        $h = $s['height'] > 0 ? $s['height'].$s['height_unit'] : 'auto';
        $r = $s['radius'].'px';
        $anim = $s['animation'];

        // Every rule restates every property, so a device that changes position does not
        // inherit an edge or a size from the position the device above it had.
        $box = ['top' => 'auto', 'right' => 'auto', 'bottom' => 'auto', 'left' => 'auto',
            'width' => 'auto', 'height' => 'auto', 'max-width' => 'none', 'max-height' => 'none'];
        $open = 'none';
        switch ($pos) {
            case 'left':
            case 'right':
                $box = array_merge($box, ['top' => '0', 'bottom' => '0', $pos => '0', 'width' => 'min('.$w.', 100vw)']);
                $box['border-radius'] = $pos === 'left' ? "0 $r $r 0" : "$r 0 0 $r";
                $closed = $pos === 'left' ? 'translateX(-100%)' : 'translateX(100%)';
                break;
            case 'top':
            case 'bottom':
                $box = array_merge($box, ['left' => '0', 'right' => '0', $pos => '0', 'height' => $h, 'max-height' => '100vh']);
                $box['border-radius'] = $pos === 'top' ? "0 0 $r $r" : "$r $r 0 0";
                $closed = $pos === 'top' ? 'translateY(-100%)' : 'translateY(100%)';
                break;
            default: // center — a popup
                $box = array_merge($box, ['top' => '50%', 'left' => '50%', 'width' => $w, 'height' => $h,
                    'max-width' => 'calc(100vw - 32px)', 'max-height' => 'calc(100vh - 32px)']);
                $box['border-radius'] = $r;
                $open = 'translate(-50%, -50%)';
                $closed = 'translate(-50%, calc(-50% + 40px))';
        }
        if ($anim === 'zoom') {
            $closed = $pos === 'center' ? 'translate(-50%, -50%) scale(.92)' : 'scale(.92)';
        } elseif ($anim !== 'slide') {
            $closed = $open; // fade and none do not move the panel
        }

        $decl = '';
        foreach ($box as $prop => $value) {
            $decl .= $prop.':'.$value.';';
        }
        $hidden = $anim === 'slide' && $pos === 'center' ? 'opacity:0;' : '';

        return $sel.' .falcon-oc__panel{'.$decl.'transform:'.$open.';}'
            .$sel.':not(.is-open) .falcon-oc__panel{transform:'.$closed.';'.$hidden.'}'
            .$sel.'{--oc-close-size:'.$s['close_size'].'px;}';
    }

    /** A CSS colour we are willing to print into a stylesheet, or null. */
    public static function color($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || $v === 'transparent') {
            return $v === 'transparent' ? 'transparent' : null;
        }
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) {
            return $v;
        }
        if (preg_match('/^rgba?\(\s*[\d.\s,%\/]+\)$/i', $v)) {
            return $v;
        }

        return null;
    }

    // ── Front end ───────────────────────────────────────────────────────────

    /** The link that opens a panel. */
    public static function anchor(array $item): string
    {
        return '#offcanvas-'.($item['slug'] ?? '');
    }

    /** The trigger for the panel with this id — what the "Open Off-Canvas" link source returns. */
    public static function anchorFor(string $id): string
    {
        $item = self::find($id);

        return $item && !empty($item['slug']) ? self::anchor($item) : '';
    }

    /** Does this HTML point at the panel — a #offcanvas-{slug} link or a data attribute? */
    public static function referencedIn(string $html, string $slug): bool
    {
        if ($slug === '' || $html === '') {
            return false;
        }
        $q = preg_quote($slug, '/');

        return (bool) preg_match('/(?:#offcanvas-'.$q.'|data-falcon-offcanvas=["\']'.$q.')(?![\w-])/', $html);
    }

    /** Does the current request satisfy the panel's "auto open on" page rule? */
    public static function pageMatches(array $s): bool
    {
        $rule = $s['auto_pages'] ?? 'all';
        if ($rule === 'all') {
            return true;
        }

        $hit = false;
        foreach ((array) ($s['auto_targets'] ?? []) as $target) {
            if (self::targetMatches((string) $target)) {
                $hit = true;
                break;
            }
        }

        return $rule === 'include' ? $hit : !$hit;
    }

    /** Is the current request one of these pages? Same targets as Layout Builder conditions. */
    public static function targetMatches(string $target): bool
    {
        if (str_starts_with($target, 'path:')) {
            $p = trim(substr($target, 5), '/');

            return request()->is($p === '' ? '/' : $p);
        }

        $ctx = function_exists('falcon_layout_context') ? falcon_layout_context() : ['kind' => null];
        // The theme's own index (no front page assigned) sets no context; it is still home.
        if ($target === 'home' && request()->path() === '/') {
            return true;
        }

        return function_exists('falcon_condition_target_matches') && falcon_condition_target_matches($target, $ctx);
    }

    /**
     * The panels this page needs: every enabled panel whose trigger appears in $html, plus the
     * auto-opening ones whose page rule matches. A panel can hold the trigger for another, so
     * each one picked is searched in turn.
     */
    public static function needed(string $html, ?array $items = null): array
    {
        $pending = [];
        foreach ($items ?? self::all() as $item) {
            if (($item['enabled'] ?? true) && !empty($item['slug']) && !empty($item['id'])) {
                $pending[$item['id']] = $item;
            }
        }

        $picked = [];
        $haystack = $html;
        do {
            $found = false;
            foreach ($pending as $id => $item) {
                $s = self::settings($item['config']['settings'] ?? []);
                $auto = $s['trigger'] !== 'none' && self::pageMatches($s);
                if ($auto || self::referencedIn($haystack, $item['slug'])) {
                    $picked[$id] = $item;
                    unset($pending[$id]);
                    $haystack = json_encode($item['config']['layout'] ?? []);
                    $found = true;
                }
            }
        } while ($found && $pending);

        return array_values($picked);
    }

    /** The markup for every panel this page needs. '' when there are none. */
    public static function renderForPage(string $pageHtml): string
    {
        try {
            $items = self::needed($pageHtml);
            if (!$items) {
                return '';
            }

            return view('falcon-cms::components.frontend.off-canvas', ['panels' => $items])->render();
        } catch (Throwable $e) {
            report($e);

            return '';
        }
    }
}
