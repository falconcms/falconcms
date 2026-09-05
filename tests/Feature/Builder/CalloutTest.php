<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\CalloutStyles;
use FalconCms\Core\Support\InlineMarkup;
use FalconCms\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Callout element.
 *
 * Three things decide whether this element holds up. The body must format identically
 * in the canvas and on the page, or an author writes against one set of rules and
 * publishes another. What an author types must never be able to become markup, because
 * this box is prose and prose is where people paste things. And it has to survive the
 * shortcode round trip — including a trip through the classic editor, which shreds a
 * multi-line body unless it is written to withstand it.
 */
class CalloutTest extends TestCase
{
    /**
     * The Callout is a Pro element, and the builder decides that from one list. Left out
     * of it, the element would be free on every site while the pricing page said
     * otherwise — and the gate is not only cosmetic: the same list stops a locked
     * element being edited, moved or dragged.
     */
    public function test_the_callout_is_gated_behind_pro(): void
    {
        $scripts = file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        preg_match('/const proElementTypes = \[(.*?)\];/s', $scripts, $m);
        $this->assertNotEmpty($m, 'the Pro element list is gone');
        $this->assertStringContainsString("'callout'", $m[1], 'the Callout element is not gated behind Pro');
    }

    // ---- variants and presets --------------------------------------------------

    /**
     * A variant carries a meaning, so it must carry everything that expresses one. A
     * variant missing its tint renders a box with no background; missing its ink,
     * a title in the wrong colour — neither of which shows up until a page is published.
     */
    public function test_every_variant_is_complete(): void
    {
        foreach (CalloutStyles::variants() as $slug => $variant) {
            foreach (['name', 'icon', 'accent', 'tint', 'ink'] as $key) {
                $this->assertArrayHasKey($key, $variant, "variant {$slug} has no {$key}");
                $this->assertNotSame('', trim((string) $variant[$key]), "variant {$slug} has an empty {$key}");
            }

            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $variant['accent'],
                "variant {$slug} has an accent the stylesheet cannot use");
        }
    }

    /** The same for presets: a preset short of a value leaves that rule unwritten. */
    public function test_every_preset_is_complete(): void
    {
        $keys = ['name', 'fill', 'barWidth', 'borderWidth', 'radius', 'padY', 'padX',
            'gap', 'iconStyle', 'iconSize', 'titleWeight', 'titleSize', 'bodySize'];

        foreach (CalloutStyles::presets() as $slug => $preset) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $preset, "preset {$slug} has no {$key}");
            }
        }
    }

    /**
     * An unknown variant or preset — an old shortcode, a hand-typed one, a name that has
     * since been renamed — must still render something. Falling through to nothing would
     * mean a box with no colour and no padding rather than a callout.
     */
    public function test_an_unknown_variant_or_preset_falls_back(): void
    {
        $this->assertSame('Note', CalloutStyles::variant('nonsense')['name']);
        $this->assertSame('Note', CalloutStyles::variant(null)['name']);
        $this->assertSame('Left bar', CalloutStyles::preset('nonsense')['name']);
        $this->assertSame('Left bar', CalloutStyles::preset(null)['name']);
    }

    /**
     * The icon field is free text, because a site can load any icon set — and free text
     * going into a class attribute is how a class field becomes an onclick.
     */
    public function test_an_icon_class_cannot_break_out_of_its_attribute(): void
    {
        $out = CalloutStyles::safeIcon('fas fa-star" onmouseover="alert(1)');

        $this->assertStringNotContainsString('"', $out);
        $this->assertStringNotContainsString('=', $out);
        $this->assertStringContainsString('fas fa-star', $out);

        // The characters real icon libraries actually use are kept.
        $this->assertSame('bi bi-gear-fill', CalloutStyles::safeIcon('bi bi-gear-fill'));
        $this->assertSame('icon:star_2', CalloutStyles::safeIcon('icon:star_2'));
    }

    /**
     * The Design tab offers the builder's icon picker, not a box to type a class into.
     *
     * An author who already knows the class gains nothing from typing it, and one who
     * does not had no way to find out what was available — the field was a text input
     * whose only documentation was a placeholder. The picker is the same shared partial
     * the Icon Box and the Section Separator use, so it lists every icon set the site
     * has loaded rather than only the ones someone remembered to mention.
     */
    public function test_the_design_tab_uses_the_shared_icon_picker(): void
    {
        $panel = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/components/elements/callout-design.blade.php'
        );

        $this->assertStringContainsString('partials.components.fields.icon', $panel,
            'the Callout no longer uses the shared icon picker');
        $this->assertDoesNotMatchRegularExpression('/v-model="editingElement\.settings\.icon"/', $panel,
            'the free-text icon class field is back alongside the picker');

        // Empty still means the variant's own icon, so the picker's preview is told to
        // show that rather than the generic star it shows everywhere else.
        $this->assertStringContainsString('fcCalVariantIcon(', $panel,
            'the picker would preview an empty field as a star, which is not what renders');
    }

    // ---- body formatting -------------------------------------------------------

    /** The body is content, not markup: nothing an author types may become live HTML. */
    public function test_a_body_can_never_carry_script(): void
    {
        $out = InlineMarkup::blocks('<script>alert(1)</script><img src=x onerror=alert(1)>');

        // Everything an author typed comes back as text: the words script and onerror
        // are still there, escaped, which is exactly what they should be — they are what
        // was written. What must not exist is a tag, and none does.
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $out);
    }

    /**
     * A blank line starts a paragraph and "- " starts a bullet, because that is what
     * every plain box a writer types into does.
     */
    public function test_the_body_understands_paragraphs_and_lists(): void
    {
        $out = InlineMarkup::blocks("First.\n\nSecond.\n\n- one\n- two\n\n1. step\n2. step");

        $this->assertSame(
            '<p>First.</p><p>Second.</p><ul><li>one</li><li>two</li></ul><ol><li>step</li><li>step</li></ol>',
            $out
        );
    }

    /** A single newline inside a paragraph is a break, not a new paragraph. */
    public function test_a_single_newline_is_a_break(): void
    {
        $this->assertSame('<p>one<br>two</p>', InlineMarkup::blocks("one\ntwo"));
    }

    /**
     * The list markers must not swallow the inline markup that looks like them. *italic*
     * has an asterisk at the start of the line too — the space after the marker is the
     * only thing that tells them apart, and getting it wrong turns every emphasised line
     * into a bullet.
     */
    public function test_italics_at_the_start_of_a_line_are_not_a_bullet(): void
    {
        $this->assertSame('<p><em>careful</em></p>', InlineMarkup::blocks('*careful*'));
        $this->assertSame('<ul><li>careful</li></ul>', InlineMarkup::blocks('* careful'));
    }

    /** The body takes the same inline markup as a table cell, buttons included. */
    public function test_the_body_takes_the_shared_inline_markup(): void
    {
        $out = InlineMarkup::blocks('Try `artisan`, read the [docs](https://x.test) or [button Buy](https://x.test/b).');

        $this->assertStringContainsString('<code>artisan</code>', $out);
        $this->assertStringContainsString('<a href="https://x.test">docs</a>', $out);
        $this->assertStringContainsString('class="fc-mk-btn fc-mk-btn-primary"', $out);
    }

    /** Empty in, empty out — a callout with no text must not render an empty paragraph. */
    public function test_an_empty_body_renders_nothing(): void
    {
        $this->assertSame('', InlineMarkup::blocks(''));
        $this->assertSame('', InlineMarkup::blocks("\n\n   \n"));
    }

    /**
     * The same rules, the same text, both engines.
     *
     * The canvas mirrors blocks() in JavaScript, and a rule that quietly means something
     * different in one engine is exactly the bug that cannot be seen: the author styles
     * the box against the canvas and publishes the other one. This runs the shared rules
     * through node the way the canvas does and compares.
     */
    public function test_php_and_javascript_split_blocks_identically(): void
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('node is not on PATH; cannot check cross-engine parity');
        }

        $bodies = [
            "First.\n\nSecond.",
            "- one\n- two",
            "1. step\n2) step",
            '*careful*',
            '* careful',
            "one\ntwo",
            'Try `artisan` and [docs](https://x.test/a_b).',
            '[button Buy](https://x.test/b)',
            'a & b < c > d',
            "  padded  \n\n  lines  ",
            '',
            "mixed\n- a\ntext after\n\n- b",
        ];

        $dir = sys_get_temp_dir().'/fc-cal-'.getmypid();
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/rules.json', json_encode(InlineMarkup::rules(), JSON_UNESCAPED_SLASHES));
        file_put_contents($dir.'/bodies.json', json_encode($bodies, JSON_UNESCAPED_SLASHES));
        file_put_contents($dir.'/run.js', $this->mirrorScript());

        $out = shell_exec(
            escapeshellarg($node).' '.escapeshellarg($dir.'/run.js')
            .' '.escapeshellarg($dir.'/rules.json').' '.escapeshellarg($dir.'/bodies.json').' 2>&1'
        );

        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);

        $js = json_decode((string) $out, true);
        $this->assertIsArray($js, "the JavaScript mirror did not return JSON:\n".$out);

        foreach ($bodies as $i => $body) {
            $this->assertSame(
                InlineMarkup::blocks($body),
                $js[$i] ?? null,
                'PHP and JavaScript disagree on: '.json_encode($body)
            );
        }
    }

    // ---- shortcode round trip --------------------------------------------------

    /** Everything an author sets must come back, including the text and its line breaks. */
    public function test_the_shortcode_round_trip_keeps_every_setting(): void
    {
        $settings = [
            'variant' => 'danger', 'preset' => 'card', 'title' => 'Do not do this',
            'body' => "First line.\n\n- one\n- two",
            'icon' => 'fas fa-skull', 'iconStyle' => 'badge',
            'accent' => '#C0392B', 'bgColor' => '#FDF0EE',
            'titleColor' => '#6B211A', 'bodyColor' => '#3C4652',
            'collapsible' => true, 'openByDefault' => false,
            'barWidth' => 6, 'radius' => 12, 'padY' => 20, 'padX' => 22,
            'cssClass' => 'my-callout', 'cssId' => 'warn-1',
        ];

        $back = $this->roundTrip($settings);

        foreach ($settings as $key => $expected) {
            $this->assertSame($expected, $back[$key] ?? null, "the {$key} setting was lost");
        }
    }

    /** A callout with no text at all is a self-closing shortcode, not an empty body. */
    public function test_an_empty_callout_is_self_closing(): void
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'callout', 'settings' => ['body' => '']]]]],
        ]]));

        $this->assertStringContainsString('[falcon_callout', $sc);
        $this->assertStringContainsString('/]', $sc);
        $this->assertStringNotContainsString('[/falcon_callout]', $sc);
    }

    /**
     * The body goes into the shortcode as readable text, not base64.
     *
     * That is the whole point of the format: a shortcode is something a person can open,
     * read and diff. It was worth checking, because the first version of the Table did
     * base64 everything and a page of them was unreadable.
     */
    public function test_the_body_is_readable_in_the_shortcode(): void
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'callout', 'settings' => [
                'body' => "Run **composer update** first.\n\n- clear the cache",
            ]]]]],
        ]]));

        $this->assertStringContainsString('Run **composer update** first.', $sc);
        $this->assertStringContainsString('- clear the cache', $sc);
        $this->assertStringNotContainsString('enc="b64"', $sc);
    }

    /**
     * A body holding this element's own closing tag is the one thing that would truncate
     * the shortcode, so such a callout falls back to base64 and says so.
     */
    public function test_a_body_holding_the_closing_tag_falls_back_to_base64(): void
    {
        $body = 'Write [/falcon_callout] to close it.';

        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'callout', 'settings' => ['body' => $body]]]]],
        ]]));

        $this->assertStringContainsString('enc="b64"', $sc);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);
        $this->assertSame($body, $back[0]['columns'][0]['elements'][0]['settings']['body'] ?? null);
    }

    /**
     * A cleared title must stay cleared.
     *
     * The renderer falls back to the variant's name only for a title that was never set,
     * which is what a freshly dropped element has. If the round trip turned an empty
     * title into an absent one, every callout an author deliberately left untitled would
     * grow a title back the next time the page was opened.
     */
    public function test_a_cleared_title_survives_the_round_trip(): void
    {
        $back = $this->roundTrip(['variant' => 'tip', 'title' => '', 'body' => 'Something.']);

        $this->assertSame('', $back['title'] ?? null, 'the cleared title came back as unset');
    }

    /**
     * The classic editor rewrites a multi-line body: paragraphs become <p>, newlines
     * become <br>, spaces become &nbsp;. A callout opened and saved in that editor has to
     * come back with its paragraphs and bullets intact.
     *
     * @param  callable(string): string  $mangle
     */
    #[DataProvider('editorMangling')]
    public function test_a_callout_survives_the_rich_editor(string $_name, callable $mangle): void
    {
        $body = "First line.\n\n- one\n- two";

        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'callout', 'settings' => ['body' => $body]]]]],
        ]]));

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($mangle($sc)), true);
        $el = $back[0]['columns'][0]['elements'][0] ?? [];

        $this->assertSame('callout', $el['type'] ?? null);
        $this->assertSame($body, $el['settings']['body'] ?? null);
    }

    /** @return array<string, array{0: string, 1: callable(string): string}> */
    public static function editorMangling(): array
    {
        return [
            'newlines to <br>' => ['newlines to <br>', static fn (string $sc) => str_replace("\n", '<br>', $sc)],
            'wrapped in <p>' => ['wrapped in <p>', static fn (string $sc) => '<p>'.str_replace("\n\n", '</p><p>', $sc).'</p>'],
            'spaces to &nbsp;' => ['spaces to &nbsp;', static fn (string $sc) => str_replace('- ', '-&nbsp;', $sc)],
        ];
    }

    // ---- rendering -------------------------------------------------------------

    /** The variant's colour and icon have to reach the page, not just the canvas. */
    public function test_it_renders_with_its_variant_colour_and_icon(): void
    {
        $html = $this->render(['variant' => 'warning', 'title' => 'Careful', 'body' => 'Mind the gap.']);

        $variant = CalloutStyles::variant('warning');
        $this->assertStringContainsString('--fc-cal-accent: '.$variant['accent'], $html);
        $this->assertStringContainsString($variant['icon'], $html);
        $this->assertStringContainsString('Careful', $html);
        $this->assertStringContainsString('<p>Mind the gap.</p>', $html);
    }

    /** An overridden colour must win over the variant's, and an emptied one must not. */
    public function test_an_emptied_colour_falls_back_to_the_variant(): void
    {
        $set = $this->render(['variant' => 'note', 'accent' => '#123456', 'body' => 'x']);
        $this->assertStringContainsString('--fc-cal-accent: #123456', $set);

        $cleared = $this->render(['variant' => 'note', 'accent' => '', 'body' => 'x']);
        $this->assertStringNotContainsString('--fc-cal-accent: ;', $cleared);
        $this->assertStringContainsString('--fc-cal-accent: '.CalloutStyles::variant('note')['accent'], $cleared);
    }

    /**
     * Collapsible is <details>, which is what makes it work with a keyboard and with the
     * browser's find-in-page. A div and a click handler would look the same and do none
     * of that — and a non-collapsible callout must NOT be a details, or a screen reader
     * announces a disclosure that never discloses anything.
     */
    public function test_collapsible_renders_a_details_element(): void
    {
        $open = $this->render(['title' => 'More', 'body' => 'x', 'collapsible' => true, 'openByDefault' => true]);
        $this->assertStringContainsString('<details', $open);
        $this->assertStringContainsString('<summary', $open);
        $this->assertMatchesRegularExpression('/<details[^>]*\sopen/', $open);

        $shut = $this->render(['title' => 'More', 'body' => 'x', 'collapsible' => true, 'openByDefault' => false]);
        $this->assertDoesNotMatchRegularExpression('/<details[^>]*\sopen/', $shut);

        $plain = $this->render(['title' => 'More', 'body' => 'x']);
        $this->assertStringNotContainsString('<details', $plain);
        $this->assertStringContainsString('role="note"', $plain);
    }

    /**
     * A button in the body must be readable.
     *
     * The body's own link rule matches a button too — a button is an <a> inside the
     * body — and `.fc-cal-body a` out-specifies a bare `.fc-mk-btn` whichever order they
     * are written in. Left that way the label took the accent colour on top of an
     * accent-coloured button and came out blank; the button was there, the words were
     * not.
     */
    public function test_a_button_in_the_body_is_not_overruled_by_the_link_colour(): void
    {
        $html = $this->render(['variant' => 'success', 'body' => '[button Read the docs](https://x.test/d)']);

        $this->assertStringContainsString('class="fc-mk-btn fc-mk-btn-primary"', $html);

        // Every button rule has to carry the same weight as the link rule beside it.
        $this->assertSame(1, preg_match('/#fc-cal-e1 \.fc-cal-body a\.fc-mk-btn-primary \{([^}]*)\}/', $html, $m));
        $this->assertStringContainsString('color: #FFFFFF', $m[1]);

        $this->assertDoesNotMatchRegularExpression(
            '/#fc-cal-e1 \.fc-mk-btn[\s,{]/', $html,
            'a button rule is written without the .fc-cal-body a prefix, so the link colour wins over it'
        );
    }

    /**
     * A bare number in Letter Spacing has to become a length.
     *
     * The control takes free text, so an author types 2 — and `letter-spacing: 2` is not
     * a length, so the browser throws the declaration away and the field does nothing at
     * all, on the page and in the canvas alike. Only font size was being given a unit.
     * Line height must NOT get one: it is a ratio, and a fixed line box stops following
     * the font size.
     */
    public function test_a_bare_letter_spacing_gets_a_unit(): void
    {
        $html = $this->render([
            'body' => 'x',
            'cal_body_letter_spacing' => '2',
            'cal_body_line_height' => '1.6',
            'cal_body_size' => '17',
        ]);

        $this->assertStringContainsString('letter-spacing: 2px', $html);
        $this->assertStringContainsString('line-height: 1.6;', $html);
        $this->assertStringNotContainsString('line-height: 1.6px', $html);
        $this->assertStringContainsString('font-size: 17px', $html);

        // A value that already carries a unit is left exactly as written.
        $withUnit = $this->render(['body' => 'x', 'cal_body_letter_spacing' => '0.05em']);
        $this->assertStringContainsString('letter-spacing: 0.05em', $withUnit);
    }

    /**
     * The canvas and the page must style the body's paragraphs and lists from the same
     * stylesheet.
     *
     * They cannot be inline styles — the body is a string of HTML, so there is no
     * element to bind one to — and the admin runs a CSS reset that strips list markers
     * and paragraph margins from everything. The canvas showed a bulleted list as plain
     * packed lines while the page showed it correctly, so the two previews disagreed
     * about something as basic as whether a bullet was a bullet.
     */
    public function test_the_body_stylesheet_is_shared_with_the_canvas(): void
    {
        $css = CalloutStyles::bodyCss('#x .fc-cal-body', '#112233', '#445566');

        $this->assertStringContainsString('#x .fc-cal-body ul { list-style-type: disc; }', $css);
        $this->assertStringContainsString('#x .fc-cal-body a { color: #112233; }', $css);
        $this->assertStringNotContainsString('{sel}', $css);
        $this->assertStringNotContainsString('{accent}', $css);
        $this->assertStringNotContainsString('{text}', $css);

        // The page renders from it...
        $this->assertStringContainsString('#fc-cal-e1 .fc-cal-body ul { list-style-type: disc; }',
            $this->render(['body' => '- one
- two']));

        // ...and the canvas is handed the very same template to fill in.
        $scripts = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );
        $this->assertStringContainsString('CalloutStyles::bodyCssTemplate()', $scripts,
            'the canvas no longer reads the shared body stylesheet, so the two will drift');

        $canvas = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/components/elements/callout.blade.php'
        );
        $this->assertStringContainsString('class="fc-cal-body"', $canvas,
            'the canvas body has no class for the shared rules to match');
    }

    /**
     * A list sits flush with the text above it.
     *
     * A callout is a short aside inside a box that is already indented from the page;
     * indenting again reads as a mistake, and the canvas and the page disagreed about it
     * because only one of them was styling the list at all.
     */
    public function test_a_list_is_not_indented(): void
    {
        $css = CalloutStyles::bodyCss('#x .fc-cal-body', '#000', '#111');

        $this->assertMatchesRegularExpression('/ul,[^{]*ol \{[^}]*padding-left: 0;/', $css);
        $this->assertStringContainsString('list-style-position: inside', $css);
    }

    /** An element with nothing in it renders nothing rather than an empty box. */
    public function test_an_empty_callout_renders_nothing(): void
    {
        $this->assertSame('', trim($this->render(['title' => '', 'body' => '', 'iconStyle' => 'none'])));
    }

    /** A title the author cleared must not come back as the variant's name on the page. */
    public function test_a_cleared_title_is_not_rendered(): void
    {
        $html = $this->render(['variant' => 'tip', 'title' => '', 'body' => 'Just text.']);

        // The class is always defined in the element's stylesheet; what must not exist
        // is a tag wearing it.
        $this->assertDoesNotMatchRegularExpression('/<(?:p|span)[^>]*class="fc-cal-title"/', $html);
        $this->assertStringContainsString('Just text.', $html);
    }

    /** Typography from the shared control has to reach the stylesheet. */
    public function test_typography_reaches_the_rendered_callout(): void
    {
        $html = $this->render([
            'title' => 'T', 'body' => 'b',
            'cal_title_transform' => 'uppercase',
            'cal_body_letter_spacing' => '0.02em',
        ]);

        $this->assertStringContainsString('text-transform: uppercase', $html);
        $this->assertStringContainsString('letter-spacing: 0.02em', $html);
    }

    // ---- helpers ---------------------------------------------------------------

    /** @return array<string, mixed> */
    private function roundTrip(array $settings): array
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'callout', 'settings' => $settings]]]],
        ]]));

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);

        return $back[0]['columns'][0]['elements'][0]['settings'] ?? [];
    }

    private function render(array $settings): string
    {
        return view('falcon-cms::frontend.builder.elements.callout', ['el' => [
            'id' => 'e1', 'type' => 'callout', 'settings' => $settings,
        ]])->render();
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

    /** The canvas block formatter, reduced to what parity needs. Mirrors InlineMarkup::blocks(). */
    private function mirrorScript(): string
    {
        return <<<'JS'
const fs = require('fs');
const rules = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'))
    .map(([n, p, r]) => [n, new RegExp(p, 'y'), r]);
const bodies = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));

const esc = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

function render(text) {
    const input = esc(text);
    let out = '', i = 0;
    while (i < input.length) {
        let matched = false;
        for (const [name, re, replacement] of rules) {
            re.lastIndex = i;
            const m = re.exec(input);
            if (!m || m[0] === '') continue;
            if (name === 'link' || name === 'button') {
                const isBtn = name === 'button';
                const label = isBtn ? m[2] : m[1];
                const raw = isBtn ? m[3] : m[2];
                const plain = raw.replace(/&amp;/g, '&').replace(/&#039;/g, "'").replace(/&quot;/g, '"');
                const href = /^\s*javascript:/i.test(plain) ? '#' : raw;
                const cls = isBtn ? ' class="fc-mk-btn fc-mk-btn-' + (m[1] || 'primary') + '"' : '';
                out += '<a' + cls + ' href="' + href + '">' + label + '</a>';
            } else {
                out += replacement.replace(/\$(\d)/g, (_, d) => m[+d] === undefined ? '' : m[+d]);
            }
            i += m[0].length;
            matched = true;
            break;
        }
        if (!matched) { out += input[i]; i++; }
    }
    return out;
}

// Mirrors InlineMarkup::blocks().
function blocks(text) {
    const lines = String(text == null ? '' : text).split(/\r\n|\r|\n/);
    let out = '', para = [], list = [], listTag = '';

    const flushPara = () => {
        if (para.length) { out += '<p>' + para.map(render).join('<br>') + '</p>'; para = []; }
    };
    const flushList = () => {
        if (list.length) {
            out += '<' + listTag + '>' + list.map(i => '<li>' + render(i) + '</li>').join('') + '</' + listTag + '>';
            list = []; listTag = '';
        }
    };

    lines.forEach(line => {
        const t = line.trim();
        if (t === '') { flushList(); flushPara(); return; }

        let m = t.match(/^[-*]\s+(.*)$/);
        if (m) {
            flushPara();
            if (listTag !== 'ul') { flushList(); listTag = 'ul'; }
            list.push(m[1]);
            return;
        }

        m = t.match(/^\d+[.)]\s+(.*)$/);
        if (m) {
            flushPara();
            if (listTag !== 'ol') { flushList(); listTag = 'ol'; }
            list.push(m[1]);
            return;
        }

        flushList();
        para.push(t);
    });

    flushList();
    flushPara();
    return out;
}

process.stdout.write(JSON.stringify(bodies.map(blocks)));
JS;
    }
}
