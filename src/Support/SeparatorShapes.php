<?php

namespace FalconCms\Core\Support;

/**
 * Geometry for the Section Separator builder element.
 *
 * This class is the SINGLE source of truth. The frontend renderer
 * (resources/views/frontend/builder/elements/section-separator.blade.php) reads these
 * arrays directly, and the admin canvas receives the very same arrays as JSON
 * (resources/views/admin/falcon-builder/partials/scripts.blade.php), so the live
 * preview and the published page can never drift apart.
 *
 * Two families:
 *  - patterns(): small tiles repeated along the X axis (background-image).
 *  - shapes():   one full-width silhouette stretched to the element width (inline <svg>).
 */
class SeparatorShapes
{
    /** Shape grid — every shape path below is authored on this viewBox. */
    public const VIEW_W = 1200;

    public const VIEW_H = 120;

    /**
     * Repeating line patterns.
     *
     * name => [viewBox width, viewBox height, inner SVG].
     * {C} is replaced with the colour, {W} with the stroke weight.
     *
     * @return array<string, array{0:int,1:int,2:string}>
     */
    public static function patterns(): array
    {
        return [
            'wavy' => [20, 12, '<path d="M0 6 Q 5 0 10 6 T 20 6" fill="none" stroke="{C}" stroke-width="{W}" stroke-linecap="square"/>'],
            'zigzag' => [20, 12, '<path d="M0 10 L5 2 L10 10 L15 2 L20 10" fill="none" stroke="{C}" stroke-width="{W}"/>'],
            'curly' => [24, 12, '<path d="M0 6 C0 1 8 1 8 6 C8 11 16 11 16 6 C16 1 24 1 24 6" fill="none" stroke="{C}" stroke-width="{W}"/>'],
            'slashes' => [12, 12, '<path d="M-3 15 L15 -3" fill="none" stroke="{C}" stroke-width="{W}"/>'],
            'squares' => [12, 12, '<rect x="2" y="2" width="8" height="8" fill="{C}"/>'],
            'dots' => [12, 12, '<circle cx="6" cy="6" r="4" fill="{C}"/>'],
            'multi_dots' => [12, 12, '<circle cx="3" cy="6" r="1.8" fill="{C}"/><circle cx="9" cy="6" r="1.8" fill="{C}"/>'],
            'rings' => [12, 12, '<circle cx="6" cy="6" r="4" fill="none" stroke="{C}" stroke-width="{W}"/>'],
            'arrows' => [12, 12, '<path d="M2 2 L9 6 L2 10" fill="none" stroke="{C}" stroke-width="{W}"/>'],
            'stripes' => [8, 12, '<rect x="0" y="0" width="4" height="12" fill="{C}"/>'],
            'parallelogram' => [14, 12, '<path d="M4 0 H14 L10 12 H0 Z" fill="{C}"/>'],
            'half_rounds' => [16, 8, '<path d="M0 8 A8 8 0 0 1 16 8 Z" fill="{C}"/>'],
            'crosses' => [12, 12, '<path d="M2 2 L10 10 M10 2 L2 10" fill="none" stroke="{C}" stroke-width="{W}"/>'],
            'triangles' => [12, 12, '<path d="M6 1 L11 11 H1 Z" fill="{C}"/>'],
            'leaves' => [16, 12, '<path d="M0 6 Q8 -3 16 6 Q8 15 0 6 Z" fill="{C}"/>'],
        ];
    }

    /**
     * Full-width shape dividers.
     *
     * name => list of layers, each ['d' => <path data>, 'o' => <opacity 0..1>].
     * Layers are painted back-to-front, so a translucent layer listed first sits behind.
     *
     * @return array<string, array<int, array{d:string,o:float}>>
     */
    public static function shapes(): array
    {
        return [
            'triangle' => [
                ['d' => 'M0 120 L0 78 L600 18 L1200 78 L1200 120 Z', 'o' => 1.0],
            ],

            'slant' => [
                ['d' => 'M0 120 L0 98 L1200 8 L1200 120 Z', 'o' => 1.0],
            ],

            'big_triangle' => [
                ['d' => 'M0 120 L600 0 L1200 120 Z', 'o' => 1.0],
            ],

            'rounded_split' => [
                ['d' => 'M0 120 L0 68 L392 68 C452 68 456 16 528 16 L672 16 C744 16 748 68 808 68 L1200 68 L1200 120 Z', 'o' => 1.0],
            ],

            'curved' => [
                ['d' => 'M0 120 L0 76 C300 4 900 4 1200 76 L1200 120 Z', 'o' => 1.0],
            ],

            'big_half_circle' => [
                ['d' => 'M0 120 C0 26 268 0 600 0 C932 0 1200 26 1200 120 Z', 'o' => 1.0],
            ],

            'clouds' => [
                ['d' => self::clouds(), 'o' => 1.0],
            ],

            // Translucent bands drifting across each other behind one solid rise.
            'horizon' => [
                ['d' => 'M0 120 L0 58 C220 8 420 96 640 52 C860 8 1040 74 1200 34 L1200 120 Z', 'o' => 0.16],
                ['d' => 'M0 120 L0 30 C200 78 400 22 620 62 C840 102 1030 40 1200 70 L1200 120 Z', 'o' => 0.16],
                ['d' => 'M0 120 L0 78 C240 30 460 100 700 60 C900 26 1060 88 1200 56 L1200 120 Z', 'o' => 0.16],
                ['d' => 'M0 120 L0 46 C180 18 340 88 520 74 C700 60 800 96 940 92'
                    .' C1060 88 1130 62 1200 44 L1200 120 Z', 'o' => 0.16],
                ['d' => 'M0 120 L0 98 C160 96 300 90 430 82 C560 74 700 40 880 40'
                    .' C1020 40 1108 74 1200 90 L1200 120 Z', 'o' => 1.0],
            ],

            'waves' => [
                ['d' => 'M0 120 L0 62 C100 28 200 96 300 62 C400 28 500 96 600 62'
                    .' C700 28 800 96 900 62 C1000 28 1100 96 1200 62 L1200 120 Z', 'o' => 1.0],
            ],

            // Three regular waves at different wavelengths, each a step darker. Different
            // wavelengths matter: at the same one the layers sit almost on top of each
            // other and the translucent ones never show. Its repeating rhythm is also what
            // separates this from Horizon's irregular drifting bands.
            'waves_opacity' => [
                // The faint layers sit HIGHER than the solid one. Each wave fills down to the
                // bottom edge, so a solid front placed above the others would simply cover them.
                ['d' => self::wave(46, 120, 28), 'o' => 0.30],
                ['d' => self::wave(66, 150, 28), 'o' => 0.55],
                ['d' => self::wave(86, 200, 28), 'o' => 1.0],
            ],

            // A wave plus a few tapered brush flicks riding just above it.
            'waves_brush' => [
                ['d' => 'M0 120 L0 78 C90 44 190 108 300 76 C410 44 500 104 610 74'
                    .' C720 44 820 106 930 76 C1030 50 1120 88 1200 70 L1200 120 Z', 'o' => 1.0],
                ['d' => 'M96 56 C168 26 254 30 322 54 C260 40 172 42 106 62 Z'
                    .' M404 44 C486 16 578 22 646 48 C578 32 490 32 414 52 Z'
                    .' M742 52 C818 22 906 26 972 52 C906 38 822 38 752 58 Z'
                    .' M1032 40 C1082 24 1140 28 1180 44 C1136 36 1082 36 1040 48 Z', 'o' => 0.9],
            ],

            'hills' => [
                ['d' => 'M0 120 L0 98 C90 46 200 42 310 84 C420 126 520 52 640 74'
                    .' C760 96 850 34 970 72 C1060 100 1140 98 1200 88 L1200 120 Z', 'o' => 1.0],
            ],

            'hills_opacity' => [
                ['d' => 'M0 120 L0 88 C100 32 210 98 330 68 C450 38 540 104 660 76'
                    .' C780 48 880 102 1000 72 C1090 50 1150 72 1200 80 L1200 120 Z', 'o' => 0.45],
                ['d' => 'M0 120 L0 98 C90 46 200 42 310 84 C420 126 520 52 640 74'
                    .' C760 96 850 34 970 72 C1060 100 1140 98 1200 88 L1200 120 Z', 'o' => 1.0],
            ],

            'grunge' => [
                ['d' => self::eroded(), 'o' => 1.0],
                ['d' => self::grit(), 'o' => 0.55],
            ],

            'music' => [
                ['d' => self::bars(), 'o' => 1.0],
            ],

            'paper' => [
                ['d' => 'M0 120 L0 62 L70 38 L142 70 L214 32 L286 60 L358 28 L430 66 L502 36'
                    .' L574 68 L646 30 L718 62 L790 34 L862 70 L934 40 L1006 64 L1078 32'
                    .' L1150 60 L1200 42 L1200 120 Z', 'o' => 1.0],
            ],

            // Squares stood on a corner — a lattice of diamonds thinning towards the top.
            'squares' => [
                ['d' => self::diamondField(false), 'o' => 0.45],
                ['d' => self::diamondField(true), 'o' => 1.0],
            ],

            'circles' => [
                ['d' => self::circleRows(), 'o' => 1.0],
            ],

            // Free-standing brush band with drips hanging off it.
            'paint' => [
                ['d' => 'M0 42 C120 22 240 62 360 40 C480 18 600 58 720 36 C840 14 960 54 1080 34'
                    .' C1140 24 1172 30 1200 36 L1200 74 C1172 80 1140 74 1080 68'
                    .' C960 88 840 48 720 70 C600 92 480 52 360 74 C240 96 120 56 0 76 Z', 'o' => 1.0],
                ['d' => self::drip(172, 62, 22, 52).' '.self::drip(514, 60, 26, 58).' '.self::drip(874, 64, 19, 46)
                    .' '.self::drip(340, 68, 15, 34).' '.self::drip(1038, 62, 18, 40)
                    .' '.self::circle(258, 108, 8).' '.self::circle(700, 110, 6).' '.self::circle(962, 104, 7), 'o' => 1.0],
            ],

            'grass' => [
                ['d' => 'M0 120 L0 72 L1200 72 L1200 120 Z '.self::blades(), 'o' => 1.0],
            ],

            // ── Second batch ─────────────────────────────────────────────────
            'arrow' => [
                ['d' => 'M0 74 L600 42 L1200 74 L1200 108 L600 76 L0 108 Z', 'o' => 0.45],
                ['d' => 'M0 34 L600 2 L1200 34 L1200 68 L600 36 L0 68 Z', 'o' => 1.0],
            ],

            'book' => [
                ['d' => 'M0 120 L0 38 C170 92 420 100 600 100 C780 100 1030 92 1200 38 L1200 120 Z', 'o' => 1.0],
            ],

            'fan' => [
                ['d' => self::fanBand(0, 16), 'o' => 0.12],
                ['d' => self::fanBand(16, 38), 'o' => 0.24],
                ['d' => self::fanBand(38, 62), 'o' => 0.42],
                ['d' => self::fanBand(62, 88), 'o' => 0.66],
                ['d' => 'M0 120 L0 88 L600 120 L1200 88 L1200 120 Z', 'o' => 1.0],
            ],

            'pyramids' => [
                ['d' => self::pyramids(), 'o' => 1.0],
            ],

            'mountains' => [
                ['d' => self::ridge(), 'o' => 1.0],
            ],

            // Hangs from the top edge — pair it with Invert if you want it the other way up.
            'drops' => [
                ['d' => 'M0 0 L1200 0 L1200 40 L0 40 Z '
                    .self::drip(100, 40, 22, 48).' '.self::drip(282, 40, 16, 34).' '.self::drip(452, 40, 26, 58)
                    .' '.self::drip(642, 40, 18, 38).' '.self::drip(832, 40, 24, 52).' '.self::drip(1032, 40, 15, 32), 'o' => 1.0],
                ['d' => self::circle(224, 98, 8).' '.self::circle(562, 110, 7).' '.self::circle(918, 102, 9), 'o' => 1.0],
            ],

            'zigzag' => [
                ['d' => 'M0 120 L0 78 L100 34 L200 78 L300 34 L400 78 L500 34 L600 78 L700 34'
                    .' L800 78 L900 34 L1000 78 L1100 34 L1200 78 L1200 120 Z', 'o' => 1.0],
            ],

            'stairs' => [
                ['d' => 'M0 120 L0 100 L150 100 L150 80 L300 80 L300 60 L450 60 L450 40 L600 40'
                    .' L600 20 L750 20 L750 40 L900 40 L900 60 L1050 60 L1050 80 L1200 80 L1200 120 Z', 'o' => 1.0],
            ],

            'arches' => [
                ['d' => self::arches(), 'o' => 1.0],
            ],

            'bubbles' => [
                ['d' => self::bubbles(), 'o' => 1.0],
            ],

            'dunes' => [
                ['d' => 'M0 120 L0 54 C200 18 380 76 600 48 C820 20 1000 72 1200 40 L1200 120 Z', 'o' => 0.35],
                ['d' => 'M0 120 L0 80 C220 46 400 100 620 70 C840 40 1020 92 1200 62 L1200 120 Z', 'o' => 0.6],
                ['d' => 'M0 120 L0 104 C240 76 420 118 640 96 C860 74 1030 112 1200 88 L1200 120 Z', 'o' => 1.0],
            ],

            'confetti' => [
                ['d' => 'M0 120 L0 92 L1200 92 L1200 120 Z', 'o' => 1.0],
                ['d' => self::confetti(), 'o' => 0.85],
            ],

            'petals' => [
                ['d' => self::petals(), 'o' => 1.0],
            ],

            // Sharp angular facets, two planes deep.
            'crystal' => [
                ['d' => 'M0 120 L0 70 L90 34 L200 74 L300 28 L420 66 L540 20 L660 62 L780 26'
                    .' L900 70 L1020 32 L1140 68 L1200 46 L1200 120 Z', 'o' => 0.4],
                ['d' => 'M0 120 L0 92 L110 54 L220 96 L340 48 L460 90 L580 42 L700 86 L820 50'
                    .' L940 94 L1060 56 L1180 92 L1200 78 L1200 120 Z', 'o' => 1.0],
            ],

            'smoke' => [
                ['d' => self::smoke(92, [[190, 70], [610, 78], [1030, 66]]), 'o' => 0.26],
                ['d' => self::smoke(112, [[70, 48], [420, 54], [790, 46], [1140, 52]]), 'o' => 1.0],
            ],

            'aurora' => [
                ['d' => 'M0 40 C200 10 400 62 600 34 C800 6 1000 56 1200 28 L1200 56'
                    .' C1000 84 800 34 600 62 C400 90 200 38 0 68 Z', 'o' => 0.3],
                ['d' => 'M0 62 C220 32 420 84 620 56 C820 28 1010 78 1200 50 L1200 76'
                    .' C1010 104 820 54 620 82 C420 110 220 58 0 88 Z', 'o' => 0.55],
                ['d' => 'M0 84 C240 54 440 106 640 78 C840 50 1020 100 1200 72 L1200 96'
                    .' C1020 124 840 74 640 102 C440 130 240 78 0 110 Z', 'o' => 1.0],
            ],

            'splash' => [
                ['d' => 'M0 120 L0 74 C60 54 96 80 140 66 C186 52 210 86 258 72 C300 60 330 88 380 74'
                    .' C430 60 456 90 508 76 C556 64 584 92 636 78 C688 64 714 90 766 76'
                    .' C818 62 844 88 896 74 C948 60 974 86 1026 72 C1078 58 1108 84 1158 70'
                    .' C1176 65 1190 68 1200 72 L1200 120 Z', 'o' => 1.0],
                ['d' => self::circle(118, 44, 13).' '.self::circle(300, 36, 7).' '.self::circle(520, 48, 15)
                    .' '.self::circle(700, 30, 9).' '.self::circle(880, 46, 11).' '.self::circle(1050, 38, 14)
                    .' '.self::circle(418, 20, 5).' '.self::circle(962, 16, 6).' '.self::circle(196, 22, 8)
                    .' '.self::circle(614, 14, 4).' '.self::circle(790, 58, 6).' '.self::circle(1148, 52, 5)
                    // A couple of elongated flicks, as if thrown off the wave.
                    .' M356 62 C362 44 372 30 386 20 C378 36 372 50 370 64 Z'
                    .' M812 66 C818 50 828 38 842 30 C834 44 828 56 826 68 Z', 'o' => 1.0],
            ],
        ];
    }

    /**
     * Every selectable style, grouped for the <select> in the General tab.
     * Line styles are raw CSS border-style keywords; the other two families are
     * prefixed so sepIsPattern()/sepIsShape() can tell them apart.
     *
     * @return array<int, array{group:string, options:array<int, array{v:string,label:string}>}>
     */
    public static function styleOptions(): array
    {
        $groups = self::styleGroups();

        // Options are listed A–Z inside each group so they are easy to scan and to search.
        foreach ($groups as &$group) {
            usort($group['options'], static fn ($a, $b) => strcasecmp($a['label'], $b['label']));
        }

        return $groups;
    }

    /** @return array<int, array{group:string, options:array<int, array{v:string,label:string}>}> */
    private static function styleGroups(): array
    {
        return [
            ['group' => 'Line', 'options' => [
                ['v' => 'solid', 'label' => 'Solid'],
                ['v' => 'double', 'label' => 'Double'],
                ['v' => 'dashed', 'label' => 'Dashed'],
                ['v' => 'dotted', 'label' => 'Dotted'],
                ['v' => 'groove', 'label' => 'Groove'],
                ['v' => 'ridge', 'label' => 'Ridge'],
                ['v' => 'inset', 'label' => 'Inset'],
                ['v' => 'outset', 'label' => 'Outset'],
            ]],
            ['group' => 'Pattern', 'options' => [
                ['v' => 'pattern_wavy', 'label' => 'Wavy'],
                ['v' => 'pattern_zigzag', 'label' => 'Zigzag'],
                ['v' => 'pattern_curly', 'label' => 'Curly'],
                ['v' => 'pattern_slashes', 'label' => 'Slashes'],
                ['v' => 'pattern_squares', 'label' => 'Squares'],
                ['v' => 'pattern_dots', 'label' => 'Dots'],
                ['v' => 'pattern_multi_dots', 'label' => 'Multi Dots'],
                ['v' => 'pattern_rings', 'label' => 'Rings'],
                ['v' => 'pattern_arrows', 'label' => 'Arrows'],
                ['v' => 'pattern_stripes', 'label' => 'Stripes'],
                ['v' => 'pattern_parallelogram', 'label' => 'Parallelogram'],
                ['v' => 'pattern_half_rounds', 'label' => 'Half Rounds'],
                ['v' => 'pattern_crosses', 'label' => 'Crosses'],
                ['v' => 'pattern_triangles', 'label' => 'Triangles'],
                ['v' => 'pattern_leaves', 'label' => 'Leaves'],
            ]],
            ['group' => 'Shape', 'options' => [
                ['v' => 'shape_triangle', 'label' => 'Triangle'],
                ['v' => 'shape_slant', 'label' => 'Slant'],
                ['v' => 'shape_big_triangle', 'label' => 'Big Triangle'],
                ['v' => 'shape_rounded_split', 'label' => 'Rounded Split'],
                ['v' => 'shape_curved', 'label' => 'Curved'],
                ['v' => 'shape_big_half_circle', 'label' => 'Big Half Circle'],
                ['v' => 'shape_clouds', 'label' => 'Clouds'],
                ['v' => 'shape_horizon', 'label' => 'Horizon'],
                ['v' => 'shape_waves', 'label' => 'Waves'],
                ['v' => 'shape_waves_opacity', 'label' => 'Waves Opacity'],
                ['v' => 'shape_waves_brush', 'label' => 'Waves Brush'],
                ['v' => 'shape_hills', 'label' => 'Hills'],
                ['v' => 'shape_hills_opacity', 'label' => 'Hills Opacity'],
                ['v' => 'shape_grunge', 'label' => 'Grunge'],
                ['v' => 'shape_music', 'label' => 'Music'],
                ['v' => 'shape_paper', 'label' => 'Paper'],
                ['v' => 'shape_squares', 'label' => 'Squares'],
                ['v' => 'shape_circles', 'label' => 'Circles'],
                ['v' => 'shape_paint', 'label' => 'Paint'],
                ['v' => 'shape_grass', 'label' => 'Grass'],
                ['v' => 'shape_splash', 'label' => 'Splash'],
                ['v' => 'shape_arrow', 'label' => 'Arrow'],
                ['v' => 'shape_book', 'label' => 'Book'],
                ['v' => 'shape_fan', 'label' => 'Fan'],
                ['v' => 'shape_pyramids', 'label' => 'Pyramids'],
                ['v' => 'shape_mountains', 'label' => 'Mountains'],
                ['v' => 'shape_drops', 'label' => 'Drops'],
                ['v' => 'shape_zigzag', 'label' => 'Zigzag'],
                ['v' => 'shape_stairs', 'label' => 'Stairs'],
                ['v' => 'shape_arches', 'label' => 'Arches'],
                ['v' => 'shape_bubbles', 'label' => 'Bubbles'],
                ['v' => 'shape_dunes', 'label' => 'Dunes'],
                ['v' => 'shape_confetti', 'label' => 'Confetti'],
                ['v' => 'shape_petals', 'label' => 'Petals'],
                ['v' => 'shape_crystal', 'label' => 'Crystal'],
                ['v' => 'shape_smoke', 'label' => 'Smoke'],
                ['v' => 'shape_aurora', 'label' => 'Aurora'],
            ]],
            ['group' => 'Custom', 'options' => [
                ['v' => 'custom_svg', 'label' => 'Custom SVG…'],
            ]],
            ['group' => 'Other', 'options' => [
                ['v' => 'none', 'label' => 'None (spacing only)'],
            ]],
        ];
    }

    // =========================================================================
    // Custom SVG
    // -------------------------------------------------------------------------
    // A user-supplied shape. The markup is stored on the element itself — it is
    // never written to the media library, because SVG uploads are blocked there
    // on purpose (see falcon_blocked_upload_extensions(): the library is shared
    // and its files are served from the site's own origin). Inlining it here
    // keeps it scoped to the one element that uses it.
    //
    // It is sanitised on the way in (browser, at import) AND again here on the
    // way out, because stored settings can also be edited through the shortcode.
    // =========================================================================

    /**
     * Strip everything script-bearing from user-supplied SVG markup.
     *
     * Delegates to the shared sanitiser so the element and the media library enforce exactly
     * the same rules. Mirrored by sepSanitizeSvg() in partials/scripts.blade.php.
     */
    public static function sanitizeCustomSvg(?string $svg): string
    {
        return SvgSanitizer::clean($svg);
    }

    /**
     * Prepare stored markup for output: guarantee a viewBox so it scales, and force the
     * aspect-ratio behaviour the element asked for. Mirrors sepCustomMarkup() in the canvas.
     */
    public static function customSvgMarkup(?string $svg, bool $stretch): string
    {
        $svg = self::sanitizeCustomSvg($svg);
        if ($svg === '') {
            return '';
        }

        // Without a viewBox an SVG cannot scale to the element, so derive one from
        // width/height when the author left it out.
        if (!preg_match('/^\s*<svg\b[^>]*\sviewBox\s*=/i', $svg)
            && preg_match('/^\s*<svg\b[^>]*\swidth\s*=\s*["\']?([\d.]+)/i', $svg, $w)
            && preg_match('/^\s*<svg\b[^>]*\sheight\s*=\s*["\']?([\d.]+)/i', $svg, $h)) {
            $svg = preg_replace('/^(\s*<svg\b)/i', '$1 viewBox="0 0 '.$w[1].' '.$h[1].'"', $svg, 1);
        }

        // Our own preserveAspectRatio always wins over whatever was in the file.
        $svg = preg_replace('/^(\s*<svg\b[^>]*?)\s+preserveAspectRatio\s*=\s*(["\']).*?\2/i', '$1', $svg, 1);
        $par = $stretch ? 'none' : 'xMidYMid meet';

        return (string) preg_replace('/^(\s*<svg\b)/i', '$1 preserveAspectRatio="'.$par.'"', $svg, 1);
    }

    /**
     * Scoped CSS for a custom SVG: make it fill the element, and optionally repaint it.
     *
     * Recolouring is done in CSS rather than by rewriting the markup: presentation
     * attributes like fill="#abc" lose to any rule, and !important beats inline style=""
     * too — so one rule repaints every shape without touching what the user pasted.
     * Anything explicitly painted "none" is left alone so outline artwork stays outlined.
     *
     * Mirrors sepCustomCss() in partials/scripts.blade.php.
     */
    public static function customSvgCss(string $scopeId, string $color, bool $recolor): string
    {
        $wrap = '#'.$scopeId.' .falcon-separator-custom';
        // The pasted <svg> usually carries its own width/height — override so it fills the box.
        $css = $wrap.'>svg{display:block;width:100%;height:100%}';

        if ($recolor) {
            $css .= $wrap.' svg *:not([fill="none"]){fill:'.$color.' !important}'
                 .$wrap.' svg [stroke]:not([stroke="none"]){stroke:'.$color.' !important}';
        }

        return $css;
    }

    // =========================================================================
    // Generated geometry — built here so the JS side never has to rebuild it.
    // =========================================================================

    /**
     * A full circle as path data (two arcs), usable inside a larger `d`.
     *
     * Sweep flag 1 (clockwise) on purpose: every band in this file is wound clockwise, and
     * SVG's default nonzero fill rule cancels overlaps between subpaths of opposite winding —
     * a counter-clockwise circle would punch a hole in the band it is meant to merge with.
     */
    private static function circle(float $cx, float $cy, float $r): string
    {
        return 'M'.($cx - $r).' '.$cy.' a'.$r.' '.$r.' 0 1 1 '.(2 * $r).' 0 a'.$r.' '.$r.' 0 1 1 '.(-2 * $r).' 0 Z';
    }

    /**
     * A single hanging paint drip: attaches wide to the band, tapers to a rounded bulb.
     * $x is the left attachment point, $y the band edge, $w the attach width, $len the drop.
     */
    private static function drip(float $x, float $y, float $w, float $len): string
    {
        $mid = $x + ($w / 2);
        $tip = $y + $len;
        $r = $w * 0.8;           // radius of the round bulb — wider than the neck
        $cy = $tip - $r;         // its centre

        // Wound clockwise (right edge down, around the bulb, left edge up) to match the bands
        // it hangs off — SVG's nonzero fill rule cancels overlaps of opposite winding.
        return 'M'.($x + $w).' '.$y
            .' C'.($x + $w - 1).' '.($y + $len * 0.45).' '.($mid + $r).' '.($cy - $r * 0.9).' '.($mid + $r).' '.$cy
            .' A'.$r.' '.$r.' 0 0 1 '.($mid - $r).' '.$cy
            .' C'.($mid - $r).' '.($cy - $r * 0.9).' '.($x + 1).' '.($y + $len * 0.45).' '.$x.' '.$y.' Z';
    }

    /** Overlapping puffs sitting on a base band — the "Clouds" shape. */
    private static function clouds(): string
    {
        $d = 'M0 120 L0 86 L1200 86 L1200 120 Z ';
        // [centre x, crown radius] — puffs are built symmetrically around each centre.
        $clusters = [[80, 30], [268, 24], [452, 34], [648, 26], [846, 32], [1030, 22], [1168, 28]];
        foreach ($clusters as [$cx, $r]) {
            $d .= self::circle($cx, 86 - $r * 0.62, $r).' ';
            $d .= self::circle($cx - $r * 1.25, 86 - $r * 0.3, $r * 0.68).' ';
            $d .= self::circle($cx + $r * 1.3, 86 - $r * 0.26, $r * 0.62).' ';
        }
        // Smaller puffs filling the gaps so the skyline never reads as separate blobs.
        foreach ([[176, 14], [356, 12], [552, 15], [748, 13], [944, 14], [1104, 12]] as [$cx, $r]) {
            $d .= self::circle($cx, 86 - $r * 0.5, $r).' ';
        }

        return trim($d);
    }

    /** Blocky eroded top edge for the "Grunge" shape. */
    private static function eroded(): string
    {
        // High-frequency jaggedness: short segments, no long flats and no tall vertical walls —
        // that combination is what makes an edge read as torn rather than as a skyline.
        // Fixed pseudo-random sequence so the output is deterministic.
        $dx = [14, 9, 17, 11, 8, 15, 12, 19, 10, 13, 16, 8, 11, 18, 9, 14, 12, 17, 10, 15,
            8, 13, 16, 11, 19, 9, 14, 12, 10, 17, 15, 8, 13, 18, 11, 9, 16, 14, 10, 12,
            19, 8, 15, 11, 17, 13, 9, 16, 12, 14, 10, 18, 8, 15, 11, 13, 17, 9, 14, 12,
            16, 10, 19, 8, 13, 15, 11, 17, 9, 14, 12, 16, 10, 18, 8, 13, 15, 11, 14, 12,
            17, 9, 16, 10, 13, 18, 11, 15, 8, 14];
        $ys = [78, 88, 70, 94, 66, 84, 100, 74, 90, 62, 82, 96, 72, 86, 68, 98, 76, 64, 92, 80,
            70, 88, 60, 94, 74, 84, 66, 100, 78, 90, 68, 82, 96, 72, 86, 62, 92, 76, 98, 70,
            84, 66, 88, 74, 94, 64, 80, 90, 68, 100, 76, 86, 60, 92, 72, 82, 96, 70, 88, 78,
            64, 94, 74, 84, 66, 90, 100, 72, 86, 62, 98, 76, 68, 92, 80, 70, 88, 60, 94, 74,
            82, 96, 66, 90, 78, 64, 92, 72, 86, 76];
        $x = 0;
        $d = 'M0 120 L0 '.$ys[0];
        foreach ($dx as $i => $step) {
            if ($x >= self::VIEW_W) {
                break;
            }
            $x = min(self::VIEW_W, $x + $step);
            $d .= ' L'.$x.' '.($ys[$i + 1] ?? $ys[0]);
        }
        $d .= ' L'.self::VIEW_W.' 82 L'.self::VIEW_W.' 120 Z';

        return $d;
    }

    /** Chipped-off crumbs floating just above the eroded edge. */
    private static function grit(): string
    {
        // [x, y, w, h, skew] — a slight skew keeps them from reading as a row of pixels.
        $flecks = [[142, 48, 17, 8, 4], [268, 38, 11, 12, -3], [386, 52, 21, 6, 5], [512, 34, 9, 9, -2],
            [648, 46, 15, 11, 3], [774, 30, 13, 7, -4], [902, 50, 18, 8, 4], [1028, 36, 10, 12, -3],
            [1146, 44, 14, 8, 3], [206, 22, 8, 6, 2], [830, 18, 10, 7, -2], [560, 16, 7, 6, 2],
            [332, 24, 9, 5, 3], [960, 22, 8, 6, -2], [706, 58, 12, 5, 4]];
        $d = '';
        foreach ($flecks as [$x, $y, $w, $h, $skew]) {
            $d .= 'M'.$x.' '.$y.' L'.($x + $w).' '.($y - abs($skew) / 2)
                .' L'.($x + $w + $skew).' '.($y + $h).' L'.($x + $skew).' '.($y + $h + abs($skew) / 2).' Z ';
        }

        return trim($d);
    }

    /**
     * Alternating rays fanning out of a base band — the "Fan" shape.
     *
     * The apex sits well below the viewBox on purpose. A shape is stretched to the element
     * width with preserveAspectRatio="none", so an apex sitting ON the bottom edge would
     * squash the middle rays to slivers and flatten the outer ones; pushing it further away
     * keeps the spread gentle and even across the whole width.
     */
    private static function fan(): string
    {
        $apexX = self::VIEW_W / 2;
        $apexY = self::VIEW_H + 4;
        $d = '';
        $slots = 21;
        $from = -200;
        $to = 1400;
        $step = ($to - $from) / $slots;
        for ($i = 0; $i < $slots; $i += 2) {
            $a = round($from + ($i * $step), 1);
            $b = round($from + (($i + 1) * $step), 1);
            $d .= 'M'.$apexX.' '.$apexY.' L'.$a.' 0 L'.$b.' 0 Z ';
        }

        return trim($d);
    }

    /** A row of triangles of varying height on a base band — the "Pyramids" shape. */
    private static function pyramids(): string
    {
        $heights = [30, 62, 14, 48, 24, 70, 38, 18, 56, 28, 66, 42];
        $d = 'M0 120 L0 96 L1200 96 L1200 120 Z ';
        foreach ($heights as $i => $h) {
            $x = $i * 100;
            $d .= 'M'.$x.' 96 L'.($x + 50).' '.$h.' L'.($x + 100).' 96 Z ';
        }

        return trim($d);
    }

    /** A colonnade of round-topped pillars — the "Arches" shape. */
    private static function arches(): string
    {
        $d = 'M0 120 L0 100 L1200 100 L1200 120 Z ';
        $w = 52;
        $r = $w / 2;
        for ($i = 0; $i < 12; $i++) {
            $x = 24 + ($i * 100);
            $d .= 'M'.$x.' 100 L'.$x.' 46 A'.$r.' '.$r.' 0 0 1 '.($x + $w).' 46 L'.($x + $w).' 100 Z ';
        }

        return trim($d);
    }

    /** Circles rising off a band, some still attached — the "Bubbles" shape. */
    private static function bubbles(): string
    {
        $d = 'M0 120 L0 90 L1200 90 L1200 120 Z ';
        // [cx, cy, r] — anything whose bottom reaches y=90 merges into the band.
        $balls = [
            [70, 74, 20], [190, 66, 26], [330, 78, 16], [470, 62, 28], [620, 76, 18],
            [760, 64, 24], [900, 78, 15], [1030, 66, 25], [1160, 76, 19],
            // free-floating ones
            [140, 30, 9], [280, 22, 12], [420, 34, 7], [560, 18, 10], [700, 30, 8],
            [850, 20, 11], [980, 32, 7], [1110, 24, 9],
        ];
        foreach ($balls as [$cx, $cy, $r]) {
            $d .= self::circle($cx, $cy, $r).' ';
        }

        return trim($d);
    }

    /** Scattered tilted rectangles for the "Confetti" shape. */
    private static function confetti(): string
    {
        // [x, y, w, h, tilt]
        $bits = [
            [60, 60, 18, 10, 6], [150, 36, 12, 16, -5], [250, 68, 20, 9, 7], [340, 44, 10, 14, -4],
            [440, 62, 16, 11, 5], [530, 30, 14, 12, -6], [630, 66, 19, 10, 6], [720, 42, 11, 15, -5],
            [820, 60, 17, 10, 7], [910, 32, 13, 13, -4], [1010, 64, 18, 11, 5], [1100, 38, 12, 15, -6],
            [200, 78, 10, 8, 4], [400, 82, 11, 8, -4], [590, 80, 10, 8, 3], [780, 78, 11, 9, -4],
            [960, 82, 10, 8, 3], [1150, 76, 12, 9, -4],
            [470, 16, 9, 10, 3], [880, 12, 10, 9, -3], [120, 14, 8, 9, 3],
        ];
        $d = '';
        foreach ($bits as [$x, $y, $w, $h, $t]) {
            $d .= 'M'.$x.' '.$y
                .' L'.($x + $w).' '.($y - $t)
                .' L'.($x + $w + $t).' '.($y + $h - $t)
                .' L'.($x + $t).' '.($y + $h).' Z ';
        }

        return trim($d);
    }

    /** A single rounded petal standing on $baseY. */
    private static function petal(float $cx, float $baseY, float $w, float $h): string
    {
        return 'M'.$cx.' '.$baseY
            .' C'.($cx - $w).' '.($baseY - $h * 0.30).' '.($cx - $w * 0.40).' '.($baseY - $h).' '.$cx.' '.($baseY - $h)
            .' C'.($cx + $w * 0.40).' '.($baseY - $h).' '.($cx + $w).' '.($baseY - $h * 0.30).' '.$cx.' '.$baseY.' Z';
    }

    /**
     * Dense, varied blades standing on the base band — the "Grass" shape.
     *
     * Three de-correlated seed arrays (stepped by different primes) give height, lean and
     * width, so neighbouring blades never repeat and the row reads as a real lawn rather
     * than a comb. Blades overlap by design.
     */
    private static function blades(): string
    {
        $heights = [46, 28, 38, 20, 54, 32, 17, 43, 25, 50, 22, 35, 30, 15, 41, 24, 52, 27, 19, 45,
            33, 23, 37, 16, 48, 29, 21, 40, 26, 34];
        $leans = [12, -8, 20, -14, 6, 26, -18, 10, -4, 16, -22, 4, 24, -10, 14, -6, 18, -16, 8, 22,
            -12, 2, -20, 28, -2, 12, -24, 6, 20, -14];
        $widths = [7, 4, 9, 3, 6, 8, 3, 10, 5, 7, 4, 6, 9, 3, 8, 5, 11, 4, 6, 7, 3, 9, 5, 8, 4, 6, 10, 3, 7, 5];
        $n = count($heights);

        $baseY = 74;
        $d = '';
        for ($i = 0; $i < 86; $i++) {
            $x = -6 + ($i * 14);
            $h = $heights[$i % $n];
            $lean = $leans[($i * 7) % $n];
            $w = $widths[($i * 13) % $n];
            $tipX = $x + ($w / 2) + $lean;
            $tipY = $baseY - $h;

            $d .= 'M'.$x.' '.$baseY
                .' C'.round($x + $lean * 0.12, 1).' '.round($baseY - $h * 0.45, 1)
                .' '.round($x + $lean * 0.6, 1).' '.round($baseY - $h * 0.8, 1)
                .' '.round($tipX, 1).' '.$tipY
                .' C'.round($x + $w * 0.9 + $lean * 0.5, 1).' '.round($baseY - $h * 0.7, 1)
                .' '.round($x + $w * 1.15, 1).' '.round($baseY - $h * 0.35, 1)
                .' '.($x + $w).' '.$baseY.' Z ';
        }

        return trim($d);
    }

    /**
     * Overlapping petals along a base band — the "Petals" shape.
     *
     * Authored tall and narrow: the shape is squashed vertically at typical separator
     * heights, so a petal drawn at "natural" proportions would render as a plain circle.
     */
    private static function petals(): string
    {
        $d = 'M0 120 L0 104 L1200 104 L1200 120 Z ';
        $tall = [104, 88, 112, 92, 100, 84, 110, 90, 106, 86, 114, 94];
        // Short back row first, then the taller front row, so the two interleave.
        for ($i = 0; $i < 13; $i++) {
            $d .= self::petal(50 + ($i * 100), 106, 20, 58).' ';
        }
        foreach ($tall as $i => $h) {
            $d .= self::petal(100 + ($i * 100), 106, 26, $h).' ';
        }

        return trim($d);
    }

    /** Soft overlapping blobs on a band — the "Smoke" shape. */
    private static function smoke(float $baseY, array $clusters): string
    {
        $d = 'M0 120 L0 '.$baseY.' L1200 '.$baseY.' L1200 120 Z ';
        foreach ($clusters as [$cx, $r]) {
            $d .= self::circle($cx, $baseY - $r * 0.55, $r).' ';
            $d .= self::circle($cx - $r * 1.35, $baseY - $r * 0.22, $r * 0.72).' ';
            $d .= self::circle($cx + $r * 1.4, $baseY - $r * 0.18, $r * 0.66).' ';
            $d .= self::circle($cx - $r * 0.5, $baseY - $r * 1.05, $r * 0.5).' ';
            $d .= self::circle($cx + $r * 0.55, $baseY - $r * 1.0, $r * 0.46).' ';
        }

        return trim($d);
    }

    /**
     * A smooth repeating wave filled down to the bottom edge.
     * $halfW is half a wavelength, so a smaller value means more waves across the width.
     */
    private static function wave(float $baseY, float $halfW, float $amp): string
    {
        $d = 'M0 120 L0 '.$baseY;
        $up = true;
        for ($x = 0; $x < self::VIEW_W; $x += $halfW) {
            $y = $up ? $baseY - $amp : $baseY + $amp;
            $end = min($x + $halfW, self::VIEW_W);
            $d .= ' C'.round($x + $halfW / 3, 1).' '.round($y, 1)
                .' '.round($x + 2 * $halfW / 3, 1).' '.round($y, 1)
                .' '.round($end, 1).' '.$baseY;
            $up = !$up;
        }

        return $d.' L'.self::VIEW_W.' 120 Z';
    }

    /** An ellipse as path data (two arcs), wound clockwise like every band here. */
    private static function ellipse(float $cx, float $cy, float $rx, float $ry): string
    {
        return 'M'.($cx - $rx).' '.$cy
            .' a'.$rx.' '.$ry.' 0 1 1 '.(2 * $rx).' 0'
            .' a'.$rx.' '.$ry.' 0 1 1 '.(-2 * $rx).' 0 Z';
    }

    /** A rhombus, wound clockwise. */
    private static function diamond(float $cx, float $cy, float $hw, float $hh): string
    {
        return 'M'.$cx.' '.($cy - $hh)
            .' L'.($cx + $hw).' '.$cy
            .' L'.$cx.' '.($cy + $hh)
            .' L'.($cx - $hw).' '.$cy.' Z';
    }

    /**
     * One wedge of the "Fan": the band between two rays leaving the bottom-centre apex.
     * $from/$to are the heights the rays reach on the left and right edges.
     */
    private static function fanBand(float $from, float $to): string
    {
        $apexX = self::VIEW_W / 2;
        $apexY = self::VIEW_H;
        $w = self::VIEW_W;

        // Left and right wedges kept as separate subpaths so they never overlap at the apex.
        return 'M0 '.$to.' L'.$apexX.' '.$apexY.' L0 '.$from.' Z '
            .'M'.$w.' '.$from.' L'.$apexX.' '.$apexY.' L'.$w.' '.$to.' Z';
    }

    /** Scalloped band with staggered rows of circles rising off it — the "Circles" shape. */
    private static function circleRows(): string
    {
        // Base band, scalloped along its top edge.
        $d = 'M0 120 L0 96 L1200 96 L1200 120 Z ';
        $r = 14;
        for ($i = 0; $i <= 44; $i++) {
            $cx = $i * 28;
            $d .= 'M'.($cx - $r).' 96 A'.$r.' '.$r.' 0 0 1 '.($cx + $r).' 96 Z ';
        }
        // Rows above: wider than tall, each smaller than the row below and offset half a step.
        // [centre y, x radius, y radius] — ry stays well under rx so the rows never merge.
        foreach ([[68, 30, 12], [40, 26, 11], [16, 21, 9]] as $row => $spec) {
            [$cy, $rx, $ry] = $spec;
            $pitch = 74;
            $offset = ($row % 2) ? $pitch / 2 : 0;
            for ($i = -1; $i * $pitch + $offset <= 1200 + $pitch; $i++) {
                $d .= self::ellipse($i * $pitch + $offset, $cy, $rx, $ry).' ';
            }
        }

        return trim($d);
    }

    /** A lattice of diamonds thinning towards the top — the "Crystal" shape. */
    private static function diamondField(bool $solid): string
    {
        $d = $solid ? 'M0 120 L0 92 L1200 92 L1200 120 Z ' : '';
        // [row centre y, half width, half height, pitch, offset]. The bottom row is centred on
        // the band edge and its half-width equals half the pitch, so those diamonds meet corner
        // to corner and cut a continuous zigzag into it.
        $rows = [[92, 40, 28, 80, 0], [50, 24, 17, 80, 40], [18, 15, 11, 80, 0]];
        foreach ($rows as $ri => [$cy, $hw, $hh, $pitch, $offset]) {
            for ($i = -1; $i * $pitch + $offset <= 1200 + $pitch; $i++) {
                // Alternate diamonds go to the translucent layer, the rest to the solid one.
                $lit = (($i + $ri) % 3) !== 0;
                if ($lit === $solid) {
                    $d .= self::diamond($i * $pitch + $offset, $cy, $hw, $hh).' ';
                }
            }
        }

        return trim($d);
    }

    /**
     * A rugged rocky skyline — the "Mountains" shape.
     *
     * Hand-plotted rather than generated on an even step: equal spacing reads as sawteeth,
     * whereas rock needs long shallow shoulders broken by the occasional sharp peak.
     */
    private static function ridge(): string
    {
        return 'M0 120 L0 66 L26 60 L44 64 L70 46 L92 58 L104 56 L128 34 L150 54 L168 50'
            .' L186 62 L214 56 L232 60 L256 40 L274 54 L296 58 L318 52 L340 62 L358 58'
            .' L376 30 L398 50 L416 56 L440 62 L462 58 L484 44 L506 56 L524 60 L548 52'
            .' L570 58 L592 64 L614 38 L636 54 L654 58 L678 48 L700 60 L722 56 L744 62'
            .' L766 42 L788 56 L806 52 L830 60 L852 58 L874 32 L896 52 L918 58 L940 54'
            .' L962 62 L984 46 L1006 58 L1028 54 L1050 60 L1072 56 L1094 36 L1116 52'
            .' L1138 58 L1160 54 L1182 60 L1200 56 L1200 120 Z';
    }

    /** Equaliser bars for the "Music" shape. */
    private static function bars(): string
    {
        $tops = [60, 30, 78, 44, 14, 66, 36, 84, 24, 54, 72, 20, 62, 40, 86, 28, 68, 46, 16, 74, 34, 58, 80, 26];
        $d = '';
        foreach ($tops as $i => $top) {
            $x = 8 + ($i * 50);
            $d .= 'M'.$x.' '.$top.' h34 v'.(self::VIEW_H - $top).' h-34 Z ';
        }

        return trim($d);
    }
}
