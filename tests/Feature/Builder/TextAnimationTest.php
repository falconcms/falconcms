<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\TextAnimations;
use FalconCms\Core\Tests\TestCase;

/**
 * The Extra tab's looping Text Animation.
 *
 * Three things can go wrong here and none of them are visible in a diff.
 *
 * A mode can exist in the table and not in the stylesheet, or the reverse — the element
 * then carries a class with no keyframes behind it and simply sits there, which reads as
 * "the animation does not work" rather than as a missing rule.
 *
 * A colour mode can land on the wrong node. Those modes paint the glyphs with a
 * background clipped to the letters; on an element that has a fill of its own that
 * clipping erases the fill. Measured on a Button: 8,552 filled pixels became 0.
 *
 * And the canvas can disagree with the page. Both renderers decide the classes and the
 * custom properties for themselves — PHP here, JavaScript in the builder — so the last
 * test runs the two against the same settings and compares what they produce.
 */
class TextAnimationTest extends TestCase
{
    /** Nothing is animated until a mode is chosen. */
    public function test_no_mode_means_no_animation(): void
    {
        $this->assertNull(TextAnimations::resolveFor('title', []));
        $this->assertNull(TextAnimations::resolveFor('title', ['textAnim' => '']));
    }

    /**
     * A mode the table does not define is refused rather than passed through. It would
     * otherwise reach the page as a class name of its own, and a hand-edited shortcode
     * could put any string at all into the element's class attribute.
     */
    public function test_an_unknown_mode_is_refused(): void
    {
        $this->assertNull(TextAnimations::resolveFor('title', ['textAnim' => 'wiggle']));
        $this->assertNull(TextAnimations::resolveFor('title', ['textAnim' => 'ib']));
        $this->assertFalse(TextAnimations::isMode('wiggle'));
    }

    /** And an element that does not offer the section gets nothing, whatever it asks for. */
    public function test_an_element_that_does_not_offer_it_gets_nothing(): void
    {
        $this->assertNull(TextAnimations::resolveFor('image', ['textAnim' => 'swing']));
        $this->assertNull(TextAnimations::resolveFor(null, ['textAnim' => 'swing']));
        $this->assertSame([], TextAnimations::groupsFor('image'));
    }

    /**
     * Every mode in the table has a class and keyframes in the stylesheet, and every
     * animated class in the stylesheet is a mode in the table.
     *
     * This is the drift the panel cannot show: a mode listed in the picker with no rule
     * behind it applies a class and animates nothing, and looks exactly like a mode that
     * is simply subtle.
     */
    public function test_every_mode_has_a_rule_and_every_rule_has_a_mode(): void
    {
        $css = (string) file_get_contents(
            __DIR__.'/../../../resources/views/components/frontend/text-anim-styles.blade.php'
        );

        foreach (array_keys(TextAnimations::MODES) as $mode) {
            $this->assertStringContainsString('.fa-tanim-'.$mode, $css,
                "the {$mode} mode has no class in the stylesheet, so it applies nothing");
            $this->assertStringContainsString('@keyframes fa-tanim-'.$mode, $css,
                "the {$mode} mode has no keyframes, so the class animates nothing");
        }

        // The other direction. Three classes are structural rather than modes.
        preg_match_all('/@keyframes fa-tanim-([a-z-]+)/', $css, $m);
        foreach (array_unique($m[1]) as $named) {
            $this->assertArrayHasKey($named, TextAnimations::MODES,
                "the stylesheet animates {$named}, which is not a mode anyone can choose");
        }
    }

    /** Every mode the picker offers is one the renderers will accept. */
    public function test_the_picker_can_only_offer_modes_that_exist(): void
    {
        foreach (TextAnimations::GROUPS as $group => $modes) {
            foreach ($modes as $mode) {
                $this->assertArrayHasKey($mode, TextAnimations::MODES,
                    "the {$group} group offers {$mode}, which the renderers do not know");
            }
        }

        // And no mode is left out of the picker — one defined but ungrouped can only be
        // reached by hand-editing a shortcode.
        $grouped = array_merge(...array_values(TextAnimations::GROUPS));
        $this->assertSame([], array_diff(array_keys(TextAnimations::MODES), $grouped),
            'a mode exists that no group offers, so the panel cannot reach it');
    }

    /**
     * The paint modes go to the label of an element that has a background, and the
     * motion modes to the element itself.
     *
     * A clipped background on the Button's anchor takes the button's own fill with it,
     * leaving the letters floating on nothing. Motion has no such problem and reads far
     * better on the whole box, so the two are routed apart rather than both being moved
     * to the safe node.
     */
    public function test_a_paint_mode_lands_on_the_label_and_motion_on_the_box(): void
    {
        // Button and Callout carry a fill.
        foreach (['button', 'callout'] as $type) {
            $this->assertSame('text', TextAnimations::nodeFor($type, 'shimmer'),
                "a paint mode on a {$type} would clip away its background");
            $this->assertSame('text', TextAnimations::nodeFor($type, 'color-cycle'));
            $this->assertSame('box', TextAnimations::nodeFor($type, 'swing'),
                "motion on a {$type} should move the whole thing");
        }

        // A heading and a paragraph block ARE text; everything rides one node.
        foreach (['title', 'text_block'] as $type) {
            $this->assertSame('box', TextAnimations::nodeFor($type, 'shimmer'));
            $this->assertSame('box', TextAnimations::nodeFor($type, 'swing'));
        }
    }

    /**
     * And a template asking for one node gets only that node's mode, so the same
     * animation is never applied twice to a nested pair of elements.
     */
    public function test_each_node_is_offered_only_its_own_mode(): void
    {
        $shimmer = ['textAnim' => 'shimmer'];
        $this->assertNull(TextAnimations::resolveFor('button', $shimmer, 'box'),
            'the button box would clip its own fill away');
        $this->assertNotNull(TextAnimations::resolveFor('button', $shimmer, 'text'));

        $swing = ['textAnim' => 'swing'];
        $this->assertNotNull(TextAnimations::resolveFor('button', $swing, 'box'));
        $this->assertNull(TextAnimations::resolveFor('button', $swing, 'text'),
            'the label would swing inside a button that stayed still');
    }

    /** Each mode brings its own resting speed; one speed for all sixteen suits none of them. */
    public function test_a_mode_carries_its_own_default_speed(): void
    {
        $shake = TextAnimations::resolveFor('title', ['textAnim' => 'shake']);
        $flow = TextAnimations::resolveFor('title', ['textAnim' => 'gradient-flow']);

        $this->assertSame('900ms', $shake['vars']['--fa-tanim-dur']);
        $this->assertSame('4000ms', $flow['vars']['--fa-tanim-dur']);
        $this->assertSame('ease-in-out', $shake['vars']['--fa-tanim-ease']);
        $this->assertSame('linear', $flow['vars']['--fa-tanim-ease']);
    }

    /** The author's timing always wins over it. */
    public function test_the_authors_timing_wins(): void
    {
        $r = TextAnimations::resolveFor('title', [
            'textAnim' => 'shake', 'textAnimDuration' => 5000, 'textAnimDelay' => 250,
            'textAnimEasing' => 'linear', 'textAnimIteration' => 3,
        ]);

        $this->assertSame('5000ms', $r['vars']['--fa-tanim-dur']);
        $this->assertSame('250ms', $r['vars']['--fa-tanim-delay']);
        $this->assertSame('linear', $r['vars']['--fa-tanim-ease']);
        $this->assertSame('3', $r['vars']['--fa-tanim-iter']);
    }

    /**
     * A duration under 50ms is indistinguishable from no animation, and a repeat count
     * under 1 is none at all — so neither can silently switch the motion off from a
     * hand-edited shortcode.
     */
    public function test_a_duration_or_a_repeat_count_cannot_switch_it_off(): void
    {
        $r = TextAnimations::resolveFor('title', [
            'textAnim' => 'swing', 'textAnimDuration' => 0, 'textAnimIteration' => 0,
        ]);

        $this->assertSame('50ms', $r['vars']['--fa-tanim-dur']);
        $this->assertSame('1', $r['vars']['--fa-tanim-iter']);

        $r = TextAnimations::resolveFor('title', [
            'textAnim' => 'swing', 'textAnimDuration' => -400, 'textAnimIteration' => -2,
        ]);
        $this->assertSame('50ms', $r['vars']['--fa-tanim-dur']);
        $this->assertSame('1', $r['vars']['--fa-tanim-iter']);
    }

    /** Infinite is the default and survives being written out. */
    public function test_infinite_is_the_default_repeat(): void
    {
        $r = TextAnimations::resolveFor('title', ['textAnim' => 'swing']);
        $this->assertSame('infinite', $r['vars']['--fa-tanim-iter']);

        $r = TextAnimations::resolveFor('title', ['textAnim' => 'swing', 'textAnimIteration' => 'infinite']);
        $this->assertSame('infinite', $r['vars']['--fa-tanim-iter']);
    }

    /**
     * Every value reaching the style attribute is checked first.
     *
     * These land in `style="…"` unescaped, so a quote in an easing or a colour would end
     * the attribute and let whatever follows become markup. A rejected value falls back
     * to the mode's own rather than being dropped, so the animation still plays.
     */
    public function test_a_value_that_could_break_out_of_the_style_attribute_is_refused(): void
    {
        $r = TextAnimations::resolveFor('title', [
            'textAnim' => 'glow',
            'textAnimEasing' => 'linear" onload="alert(1)',
            'textAnimColor' => 'red;} body{display:none',
        ]);

        foreach ($r['vars'] as $prop => $value) {
            $this->assertStringNotContainsString('"', $value, "{$prop} can close the style attribute");
            $this->assertStringNotContainsString("'", $value, "{$prop} can close the style attribute");
            $this->assertStringNotContainsString('<', $value);
            $this->assertStringNotContainsString(';', $value, "{$prop} can start a second declaration");
        }

        $this->assertSame('ease-in-out', $r['vars']['--fa-tanim-ease'], 'the mode default should stand in');
    }

    /** The class list is the mode plus whatever the settings ask for, and nothing else. */
    public function test_hover_parks_the_animation_until_it_is_pointed_at(): void
    {
        $always = TextAnimations::resolveFor('title', ['textAnim' => 'swing']);
        $hover = TextAnimations::resolveFor('title', ['textAnim' => 'swing', 'textAnimTrigger' => 'hover']);

        $this->assertStringNotContainsString('fa-tanim-hover', $always['classes']);
        $this->assertStringContainsString('fa-tanim-hover', $hover['classes']);
        $this->assertStringContainsString('fa-tanim-swing', $hover['classes']);
    }

    /**
     * A transform needs a shrink-to-fit box to pivot around — but an ellipsis needs the
     * full column width to clip against, so the two cannot both be had. The heading that
     * is clipping keeps its width.
     */
    public function test_a_heading_that_is_clipping_keeps_its_width(): void
    {
        $plain = TextAnimations::resolveFor('title', ['textAnim' => 'swing']);
        $this->assertStringContainsString('fa-tanim-ib', $plain['classes']);

        foreach (['ellipsis', 'clip'] as $overflow) {
            $clipped = TextAnimations::resolveFor('title', ['textAnim' => 'swing', 'textOverflow' => $overflow]);
            $this->assertStringNotContainsString('fa-tanim-ib', $clipped['classes'],
                "shrinking a heading set to {$overflow} takes away the width it clips against");
        }

        // A paragraph block never shrinks: it would stop being a block.
        $block = TextAnimations::resolveFor('text_block', ['textAnim' => 'swing']);
        $this->assertStringNotContainsString('fa-tanim-ib', $block['classes']);
    }

    /**
     * The colour modes build their gradient from the element's real ink, so a sweep over
     * dark text does not arrive as a sweep over the default grey.
     */
    public function test_the_colour_modes_read_the_elements_own_colour(): void
    {
        $r = TextAnimations::resolveFor('title', ['textAnim' => 'shine', 'titleColor' => '#123456']);
        $this->assertSame('#123456', $r['vars']['--fa-tanim-base']);

        // A Title using Gradient Text has no flat colour to read, so the gradient's own
        // start colour stands in for it.
        $r = TextAnimations::resolveFor('title', [
            'textAnim' => 'shine', 'titleColor' => '#123456',
            'useGradient' => true, 'gradientStartColor' => '#ABCDEF',
        ]);
        $this->assertSame('#ABCDEF', $r['vars']['--fa-tanim-base']);

        // The Button keeps its text colour under two different names.
        $r = TextAnimations::resolveFor('button', ['textAnim' => 'shine', 'customTextColor' => '#0F0F0F'], 'text');
        $this->assertSame('#0F0F0F', $r['vars']['--fa-tanim-base']);
    }

    /**
     * Shimmer's midpoint is blended in PHP rather than with CSS color-mix(), which an
     * engine without it would treat as an invalid gradient stop — invalidating the whole
     * declaration and leaving the glyphs transparent, which is worse than a plain sweep.
     */
    public function test_shimmer_blends_its_midpoint_here_rather_than_in_css(): void
    {
        $r = TextAnimations::resolveFor('title', [
            'textAnim' => 'shimmer', 'titleColor' => '#000000', 'textAnimColor' => '#ffffff',
        ]);

        $this->assertSame('#8c8c8c', $r['vars']['--fa-tanim-mid']);
        $this->assertStringNotContainsString('color-mix', implode(' ', $r['vars']));

        // A colour it cannot read simply skips the blend rather than producing nonsense.
        $r = TextAnimations::resolveFor('title', [
            'textAnim' => 'shimmer', 'titleColor' => 'var(--brand)', 'textAnimColor' => '#ffffff',
        ]);
        $this->assertSame('#ffffff', $r['vars']['--fa-tanim-mid']);
    }

    /** Gradient Flow needs three stops; the third falls back to the base, never to nothing. */
    public function test_gradient_flow_always_has_three_stops(): void
    {
        $r = TextAnimations::resolveFor('title', ['textAnim' => 'gradient-flow', 'titleColor' => '#111111']);

        foreach (['g1', 'g2', 'g3'] as $stop) {
            $this->assertNotSame('', $r['vars']['--fa-tanim-'.$stop] ?? '');
        }
        $this->assertSame('#111111', $r['vars']['--fa-tanim-g3'], 'the third stop has nothing to fall back to');
    }

    /** Only the colour modes carry colours; the motion modes have no use for them. */
    public function test_a_motion_mode_carries_no_colours(): void
    {
        $r = TextAnimations::resolveFor('title', ['textAnim' => 'bounce', 'textAnimColor' => '#ff0000']);

        $this->assertArrayNotHasKey('--fa-tanim-accent', $r['vars']);
        $this->assertArrayNotHasKey('--fa-tanim-base', $r['vars']);
    }

    /**
     * The stylesheet ships only for a page that animates something, so the walk that
     * decides it has to find a mode wherever the layout puts one — a container, a
     * column, a nested row, a card layout's own list.
     */
    public function test_the_stylesheet_only_ships_for_a_page_that_animates_something(): void
    {
        $this->assertFalse(TextAnimations::layoutHasAnimation([]));
        $this->assertFalse(TextAnimations::layoutHasAnimation([
            ['columns' => [['elements' => [['type' => 'title', 'settings' => ['title' => 'Hi']]]]]],
        ]));

        // Buried three levels down, inside a nested row.
        $this->assertTrue(TextAnimations::layoutHasAnimation([
            ['columns' => [['elements' => [
                ['type' => 'row', 'columns' => [['elements' => [
                    ['type' => 'title', 'settings' => ['textAnim' => 'swing']],
                ]]]],
            ]]]],
        ]));

        // A leftover empty value is not an animation.
        $this->assertFalse(TextAnimations::layoutHasAnimation([
            ['columns' => [['elements' => [['settings' => ['textAnim' => '']]]]]],
        ]));

        // Nor is a mode that no longer exists.
        $this->assertFalse(TextAnimations::layoutHasAnimation([
            ['columns' => [['elements' => [['settings' => ['textAnim' => 'wiggle']]]]]],
        ]));
    }

    /** The settings survive being written to a shortcode and read back. */
    public function test_the_settings_survive_the_round_trip(): void
    {
        foreach (['title', 'text_block', 'button', 'callout'] as $type) {
            $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
                'columns' => [['elements' => [['type' => $type, 'settings' => [
                    'textAnim' => 'shimmer',
                    'textAnimTrigger' => 'hover',
                    'textAnimDuration' => 2500,
                    'textAnimDelay' => 100,
                    'textAnimIteration' => 4,
                    'textAnimEasing' => 'linear',
                    'textAnimColor' => '#ffd9a0',
                ]]]]],
            ]]));

            $this->assertStringContainsString('text_anim="shimmer"', $sc, "{$type} loses the mode");

            $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);
            $s = $back[0]['columns'][0]['elements'][0]['settings'] ?? [];

            $this->assertSame('shimmer', $s['textAnim'] ?? null, "{$type} loses the mode");
            $this->assertSame('hover', $s['textAnimTrigger'] ?? null, "{$type} loses the trigger");
            $this->assertSame(2500, $s['textAnimDuration'] ?? null, "{$type} loses the duration");
            $this->assertSame(100, $s['textAnimDelay'] ?? null, "{$type} loses the delay");
            $this->assertSame('linear', $s['textAnimEasing'] ?? null, "{$type} loses the easing");
            $this->assertSame('#ffd9a0', $s['textAnimColor'] ?? null, "{$type} loses the colour");
        }
    }

    /** An element with no animation writes none of it into the shortcode. */
    public function test_no_animation_writes_nothing(): void
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'title', 'settings' => ['title' => 'Hello']]]]],
        ]]));

        $this->assertStringNotContainsString('text_anim', $sc);
    }

    /** And the page renders the classes and the properties it was given. */
    public function test_the_page_renders_the_classes_and_the_properties(): void
    {
        $html = view('falcon-cms::frontend.builder.elements.title', ['el' => [
            'id' => 'e1', 'type' => 'title', 'settings' => [
                'title' => 'Hello', 'titleColor' => '#222222',
                'textAnim' => 'swing', 'textAnimDuration' => 1200,
            ],
        ]])->render();

        $this->assertStringContainsString('fa-tanim-swing', $html);
        $this->assertStringContainsString('--fa-tanim-dur: 1200ms', $html);
    }

    /**
     * The canvas and the page must agree.
     *
     * Both work the classes and the custom properties out for themselves — this class in
     * PHP, textAnimClass() and textAnimVars() in the builder's script — from the same two
     * tables. Drift between them is the failure this whole feature is most prone to,
     * and it shows up as an author styling something in the editor that arrives looking
     * different on the site. So both are run over the same settings and compared.
     */
    public function test_the_canvas_produces_what_the_page_produces(): void
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('node is not on PATH; cannot run the canvas helpers');
        }

        $scripts = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        $lifted = '';
        foreach ([
            'fcTanimMode', 'fcTanimNode', 'fcTanimModeAt', 'fcTanimToken', 'fcTanimHexRgb',
            'fcTanimMix', 'fcTanimInt', 'fcTanimBase', 'textAnimClass', 'textAnimVars',
        ] as $name) {
            $lifted .= $this->liftConst($scripts, $name)."\n";
        }

        // The cases worth comparing: one of each kind of mode, on each kind of element,
        // with and without the author's own values.
        $cases = [
            ['title', ['textAnim' => 'swing']],
            ['title', ['textAnim' => 'swing', 'textOverflow' => 'ellipsis']],
            ['title', ['textAnim' => 'shake', 'textAnimDuration' => 0, 'textAnimIteration' => 0]],
            ['title', ['textAnim' => 'shine', 'titleColor' => '#123456']],
            ['title', ['textAnim' => 'shimmer', 'titleColor' => '#000000', 'textAnimColor' => '#ffffff']],
            ['title', ['textAnim' => 'shimmer', 'titleColor' => 'var(--brand)', 'textAnimColor' => '#ffffff']],
            ['title', ['textAnim' => 'gradient-flow', 'titleColor' => '#111111']],
            ['title', ['textAnim' => 'glow', 'titleColor' => '#222', 'textAnimColor' => '#0f0']],
            ['title', ['textAnim' => 'shine', 'useGradient' => true, 'gradientStartColor' => '#ABCDEF']],
            ['title', ['textAnim' => 'swing', 'textAnimTrigger' => 'hover', 'textAnimEasing' => 'linear']],
            ['text_block', ['textAnim' => 'float', 'color' => '#333333']],
            ['text_block', ['textAnim' => 'color-cycle', 'color' => '#333333']],
            ['button', ['textAnim' => 'swing']],
            ['button', ['textAnim' => 'shimmer', 'customTextColor' => '#0F0F0F']],
            ['callout', ['textAnim' => 'tada']],
            ['callout', ['textAnim' => 'shine', 'titleColor' => '#654321']],
        ];

        $dir = sys_get_temp_dir().'/fc-tanim-'.getmypid();
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/run.js',
            'const FC_TANIM_MODES = '.json_encode(TextAnimations::modes()).";\n"
            .'const FC_TANIM_ELEMENTS = '.json_encode(TextAnimations::elements()).";\n"
            .$lifted
            .'const CASES = '.json_encode($cases).";\n"
            ."const out = CASES.map(([type, settings]) => {\n"
            ."    const el = { id: 'e1', type, settings };\n"
            ."    return ['box', 'text'].map((node) => ({\n"
            ."        classes: textAnimClass(el, node) || null,\n"
            ."        vars: textAnimVars(el, node) || null,\n"
            ."    }));\n"
            ."});\n"
            .'process.stdout.write(JSON.stringify(out));'
        );

        $out = shell_exec(escapeshellarg($node).' '.escapeshellarg($dir.'/run.js').' 2>&1');
        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);

        $canvas = json_decode((string) $out, true);
        $this->assertIsArray($canvas, "the canvas helpers did not run:\n".$out);

        foreach ($cases as $i => [$type, $settings]) {
            foreach (['box', 'text'] as $j => $node) {
                $php = TextAnimations::resolveFor($type, $settings, $node);
                $js = $canvas[$i][$j];

                $where = "{$type} / {$settings['textAnim']} / {$node}";

                if ($php === null) {
                    $this->assertNull($js['classes'],
                        "the canvas animates the {$node} node for {$where}; the page does not");

                    continue;
                }

                $this->assertNotNull($js['classes'],
                    "the page animates the {$node} node for {$where}; the canvas does not");
                $this->assertSame($php['classes'], $js['classes'], "the classes differ for {$where}");
                $this->assertSame($php['vars'], $js['vars'], "the custom properties differ for {$where}");
            }
        }
    }

    /**
     * Lift one `const name = (…) => { … };` out of the builder's script.
     *
     * Bounded by indentation rather than by counting brackets. Counting them means
     * reading JavaScript, and the first attempt at that swallowed half the file: the
     * quote inside the character class of `/["'<>;{}]/` opened a string that never
     * closed. Every helper here sits at one indent inside the setup function and ends on
     * a line that is exactly that indent and `};`, which nothing nested can look like.
     */
    private const INDENT = '            ';

    private function liftConst(string $source, string $name): string
    {
        $open = "\n".self::INDENT.'const '.$name.' = ';
        $start = strpos($source, $open);
        $this->assertNotFalse($start, "the builder no longer defines {$name} where the test can find it");

        // Two shapes end these: a plain arrow function closes with `};`, one wrapped in
        // computed() with `});`. Whichever comes first is this definition's end.
        $end = false;
        $close = '';
        foreach (['};', '});'] as $terminator) {
            $at = strpos($source, "\n".self::INDENT.$terminator, $start);
            if ($at !== false && ($end === false || $at < $end)) {
                $end = $at;
                $close = $terminator;
            }
        }
        $this->assertNotFalse($end, "the definition of {$name} does not end where the test can see it");

        $lifted = substr($source, $start, $end - $start + strlen("\n".self::INDENT.$close));

        // A one-liner has no closing brace of its own, so this runs on to the end of
        // whatever is defined after it and lifts that too. In node that reads as a
        // duplicate declaration rather than as a mistake here, which is how it was found.
        $this->assertSame(1, substr_count($lifted, "\n".self::INDENT.'const '),
            "{$name} is written on one line; lift it another way");

        return $lifted;
    }

    private function nodeBinary(): ?string
    {
        foreach (['node', 'node.exe'] as $bin) {
            $probe = shell_exec(escapeshellarg($bin).' -v 2>&1');
            if (is_string($probe) && preg_match('/^v\d+/', trim($probe))) {
                return $bin;
            }
        }

        return null;
    }
}
