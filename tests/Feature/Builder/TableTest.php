<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\TableStyles;
use FalconCms\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Table element.
 *
 * Two things decide whether this element is any good. Cells must format identically
 * in the canvas and on the page — the same bet the Code Block makes, and the same
 * failure if it breaks: an author styles a table against one set of rules and
 * publishes another, with nothing in the request path to say so. And a Markdown
 * paste must survive intact, because the documentation already holds well over a
 * thousand Markdown table rows and a lossy importer means retyping all of them.
 */
class TableTest extends TestCase
{
    /**
     * The Table is a Pro element, and the builder decides that from one list. Left out
     * of it, the element would be free on every site while the pricing page said
     * otherwise — and the gate is not only cosmetic: the same list stops a locked
     * element being edited, moved or dragged.
     */
    public function test_the_table_is_gated_behind_pro(): void
    {
        $scripts = file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        preg_match('/const proElementTypes = \[(.*?)\];/s', $scripts, $m);
        $this->assertNotEmpty($m, 'the Pro element list is gone');
        $this->assertStringContainsString("'table'", $m[1], 'the Table element is not gated behind Pro');
    }

    // ---- cell formatting -------------------------------------------------------

    /** Cells are content, not markup: nothing an author types may become live HTML. */
    public function test_a_cell_can_never_carry_script(): void
    {
        $out = TableStyles::cell('<script>alert(1)</script><img src=x onerror=alert(1)>');

        // The words survive as text — that is the point, a cell showing markup should
        // show it. What must not survive is a tag the browser would act on.
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $out);
    }

    public function test_a_javascript_link_is_defused(): void
    {
        $out = TableStyles::cell('[click](javascript:alert(1))');

        $this->assertStringContainsString('href="#"', $out);
        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function test_the_inline_markup_renders(): void
    {
        $this->assertSame('<code>preset</code>', TableStyles::cell('`preset`'));
        $this->assertSame('<strong>six</strong>', TableStyles::cell('**six**'));
        $this->assertSame('<em>maybe</em>', TableStyles::cell('*maybe*'));
        $this->assertSame('a<br>b', TableStyles::cell('a<br>b'));
        $this->assertSame(
            '<a href="https://falconcms.com/">docs</a>',
            TableStyles::cell('[docs](https://falconcms.com/)')
        );
    }

    /** Backticks win, so a code span showing literal asterisks stays literal. */
    public function test_code_spans_are_not_reformatted_inside(): void
    {
        $this->assertSame('<code>**not bold**</code>', TableStyles::cell('`**not bold**`'));
    }

    /**
     * cell() escapes "/" when it compiles a rule, so a rule may hold one literally — the
     * self-closing slash of <br/> does. A rule that escaped its own would arrive doubled,
     * and would reach the canvas, which uses no delimiters at all, as a stray backslash.
     */
    public function test_no_rule_pre_escapes_its_delimiter(): void
    {
        foreach (TableStyles::inlineRules() as [$name, $pattern, $_]) {
            $this->assertStringNotContainsString('\\/', $pattern,
                "{$name} escapes its own slash; the compiler escapes it again");
        }
    }

    /**
     * The same rules, the same cells, both engines.
     *
     * PCRE and JavaScript agree on the subset the rules are written in, but only as
     * long as they stay inside it. This runs the shared rules through node the way the
     * canvas does and compares the output, so a pattern that quietly means something
     * different in one engine fails here rather than on a customer's page.
     */
    public function test_php_and_javascript_format_cells_identically(): void
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('node is not on PATH; cannot check cross-engine parity');
        }

        $cells = [
            '`preset`', '**six** presets', '*maybe*', 'a<br>b',
            '[docs](https://falconcms.com/a_b)', '`**not bold**`',
            'plain text', '', 'a & b < c > d', '"quoted" and \'single\'',
            'mixed `code` with **bold** and [a link](https://x.test/p?q=1)',
            '<script>alert(1)</script>', '[x](javascript:alert(1))',
            'a | b', '100%', 'v2.10.0',
        ];

        $dir = sys_get_temp_dir().'/fc-tbl-'.getmypid();
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/rules.json', json_encode(TableStyles::inlineRules(), JSON_UNESCAPED_SLASHES));
        file_put_contents($dir.'/cells.json', json_encode($cells, JSON_UNESCAPED_SLASHES));
        file_put_contents($dir.'/run.js', $this->mirrorScript());

        $out = shell_exec(
            escapeshellarg($node).' '.escapeshellarg($dir.'/run.js')
            .' '.escapeshellarg($dir.'/rules.json').' '.escapeshellarg($dir.'/cells.json').' 2>&1'
        );

        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);

        $js = json_decode((string) $out, true);
        $this->assertIsArray($js, "the JavaScript mirror did not return JSON:\n".$out);

        foreach ($cells as $i => $cell) {
            $this->assertSame(
                TableStyles::cell($cell),
                $js[$i] ?? null,
                "PHP and JavaScript disagree on: {$cell}"
            );
        }
    }

    /**
     * Comparison and pricing tables are mostly ticks and crosses, so those two have
     * their own token and anything else takes a Font Awesome class.
     */
    public function test_cells_can_carry_icons(): void
    {
        foreach (['[check]', '[yes]', '[tick]'] as $token) {
            $this->assertSame('<i class="fas fa-check fc-tbl-yes"></i>', TableStyles::cell($token));
        }
        foreach (['[cross]', '[no]', '[x]'] as $token) {
            $this->assertSame('<i class="fas fa-times fc-tbl-no"></i>', TableStyles::cell($token));
        }
        $this->assertSame('<i class="fas fa-star"></i>', TableStyles::cell('[icon fas fa-star]'));
    }

    /**
     * The icon class is the one place an author's text reaches an attribute. Limiting
     * it to letters, digits, spaces, dashes and underscores is what stops the value
     * from closing the attribute and opening another.
     */
    public function test_an_icon_class_cannot_break_out_of_its_attribute(): void
    {
        foreach ([
            '[icon fas" onload="alert(1)]',
            "[icon fa'><script>alert(1)</script>]",
            '[icon fa><img src=x>]',
        ] as $attempt) {
            $out = TableStyles::cell($attempt);

            // None of these match the rule, so they stay as escaped text. What matters
            // is that no tag was built from them — the words themselves are harmless
            // once escaped, and a cell showing markup should show it.
            $this->assertStringNotContainsString('<script', $out, $attempt);
            $this->assertStringNotContainsString('<img', $out, $attempt);
            $this->assertStringNotContainsString('<i class', $out, $attempt);
            $this->assertStringNotContainsString('"', $out, $attempt);
        }
    }

    /** A link still wins where it is genuinely a link, icons never eat one. */
    public function test_icons_do_not_swallow_links(): void
    {
        $this->assertSame(
            '<a href="https://x.test/a">check</a>',
            TableStyles::cell('[check](https://x.test/a)')
        );
    }

    public function test_highlight_spec_accepts_numbers_and_ranges(): void
    {
        $this->assertSame([2, 5, 6], TableStyles::parseSpec('2, 5-6'));
        $this->assertSame([3], TableStyles::parseSpec('3'));
        $this->assertSame([], TableStyles::parseSpec(''));
        $this->assertSame([], TableStyles::parseSpec(null));
        $this->assertSame([4, 5, 6], TableStyles::parseSpec('6-4'), 'a reversed range is a typo, not an error');
    }

    /**
     * A picked-out row has to beat the stripe. The rules are emitted after the stripe
     * and hover rules for exactly that reason — written first, a highlighted row would
     * lose its colour on every second line.
     */
    public function test_highlighted_rows_and_columns_are_styled_after_the_stripes(): void
    {
        $html = $this->render([
            'rows' => [['A', 'B', 'C'], ['1', '2', '3'], ['4', '5', '6']],
            'headerRow' => true,
            'stripe' => true,
            'highlightRows' => '2',
            'highlightCols' => '3',
            'highlightBg' => '#FFF3E0',
        ]);

        $this->assertStringContainsString('tbody tr:nth-child(2) > *', $html);
        $this->assertStringContainsString('tbody tr > *:nth-child(3)', $html);

        $stripeAt = strpos($html, 'tbody tr:nth-child(even) td');
        $hlAt = strpos($html, 'tbody tr:nth-child(2) > *');
        $this->assertNotFalse($stripeAt);
        $this->assertNotFalse($hlAt);
        $this->assertGreaterThan($stripeAt, $hlAt, 'the highlight is written before the stripe, so the stripe wins');
    }

    /** Typography comes from the shared control every other element uses. */
    public function test_typography_reaches_the_rendered_table(): void
    {
        $html = $this->render([
            'rows' => [['A'], ['1']],
            'headerRow' => true,
            'tbl_head_family' => 'Poppins, sans-serif',
            'tbl_head_weight' => '700',
            'tbl_body_size' => '13',
            'tbl_body_line_height' => '1.9',
        ]);

        $this->assertStringContainsString('font-family: Poppins, sans-serif', $html);
        $this->assertStringContainsString('font-weight: 700', $html);
        $this->assertStringContainsString('font-size: 13px', $html, 'a bare number should be read as px');
        $this->assertStringContainsString('line-height: 1.9', $html);
    }

    /**
     * An empty override means "use the preset", not "use nothing".
     *
     * The Design tab's reset button and the element's own defaults both write '', and
     * a plain ?? only falls through on null — so the stylesheet got `color: ;`, the
     * rule was dropped, and the preset's colour went with it. Body text did nothing on
     * the page while the canvas, which checks for '' as well, showed it working.
     */
    public function test_an_emptied_colour_falls_back_to_the_preset(): void
    {
        $blank = $this->render(['rows' => [['A'], ['1']], 'headerRow' => true,
            'textColor' => '', 'bodyBg' => '', 'headerBg' => '', 'borderColor' => '']);

        $this->assertStringContainsString('color: #434E5A', $blank, 'the preset body colour was lost');
        $this->assertStringNotContainsString('color: ;', $blank);
        $this->assertStringNotContainsString('background: ;', $blank);

        $dark = $this->render(['rows' => [['A'], ['1']], 'headerRow' => true,
            'preset' => 'dark', 'textColor' => '']);
        $this->assertStringContainsString('color: #C3CCD8', $dark, 'the dark preset lost its own body colour');

        $set = $this->render(['rows' => [['A'], ['1']], 'headerRow' => true, 'textColor' => '#123456']);
        $this->assertStringContainsString('color: #123456', $set, 'an explicit colour must still win');
    }

    /**
     * The body colour has to be set on the cells, not only on the table.
     *
     * Inheritance loses to any rule that matches an element directly, however low its
     * specificity, and a theme — or Custom CSS carrying a stylesheet from elsewhere —
     * very often ships a plain `tbody td { color: … }`. With the colour only on
     * <table> that rule won every time: Body text did nothing on the published page
     * while the canvas, which paints each cell inline, showed it working.
     */
    public function test_the_body_colour_is_set_on_the_cells_not_only_the_table(): void
    {
        $html = $this->render(['rows' => [['A'], ['1']], 'headerRow' => true, 'textColor' => '#FF0000']);

        $this->assertMatchesRegularExpression(
            '/#fc-tbl-e1 th, #fc-tbl-e1 td \{[^}]*color:\s*#FF0000/s',
            $html,
            'the body colour is only inherited from the table, so any td rule on the page beats it'
        );

        // And the header must still keep its own colour, which is written afterwards
        // and matches more specifically.
        $headAt = strpos($html, '#fc-tbl-e1 thead th {');
        $cellAt = strpos($html, '#fc-tbl-e1 th, #fc-tbl-e1 td {');
        $this->assertNotFalse($headAt);
        $this->assertNotFalse($cellAt);
        $this->assertGreaterThan($cellAt, $headAt, 'the header rule is written first, so the body colour would override it');
    }

    /** An untouched typography field must not emit anything, or it overrides the preset. */
    public function test_empty_typography_is_left_to_the_preset(): void
    {
        $html = $this->render([
            'rows' => [['A'], ['1']],
            'headerRow' => true,
            'tbl_head_family' => '',
            'tbl_body_family' => 'inherit',
            'tbl_body_transform' => 'none',
        ]);

        $this->assertStringNotContainsString('font-family: inherit', $html);
        $this->assertStringNotContainsString('text-transform: none', $html);
    }

    // ---- importing -------------------------------------------------------------

    /**
     * The importer is the whole reason this element is usable at scale. A GitHub-style
     * alignment row sets the column alignments and is dropped rather than becoming a
     * row of dashes.
     */
    public function test_a_markdown_table_imports_with_its_alignments(): void
    {
        $md = "| Setting | Type | Default |\n"
            ."|:--------|:----:|--------:|\n"
            ."| `preset` | string | `docs` |\n"
            .'| `sortable` | bool | `false` |';

        $parsed = TableStyles::parseMarkdown($md);

        $this->assertNotNull($parsed);
        $this->assertSame(['left', 'center', 'right'], $parsed['align']);
        $this->assertCount(3, $parsed['rows'], 'the alignment row should not become a row');
        $this->assertSame(['Setting', 'Type', 'Default'], $parsed['rows'][0]);
        $this->assertSame(['`preset`', 'string', '`docs`'], $parsed['rows'][1]);
    }

    /** A pipe inside a cell is content, not a column break. */
    public function test_an_escaped_pipe_stays_in_the_cell(): void
    {
        $parsed = TableStyles::parseMarkdown("| A | B |\n|---|---|\n| a \\| b | c |");

        $this->assertSame(['a | b', 'c'], $parsed['rows'][1]);
    }

    /** A ragged paste must not render rows with cells missing off the end. */
    public function test_short_rows_are_padded(): void
    {
        $parsed = TableStyles::parseMarkdown("| A | B | C |\n|---|---|---|\n| 1 |\n| 1 | 2 | 3 |");

        foreach ($parsed['rows'] as $row) {
            $this->assertCount(3, $row);
        }
    }

    /** A spreadsheet paste is tab separated; a file is usually comma separated. */
    public function test_delimited_text_detects_its_separator(): void
    {
        $tsv = TableStyles::parseDelimited("A\tB\tC\n1\t2\t3");
        $this->assertSame([['A', 'B', 'C'], ['1', '2', '3']], $tsv);

        $csv = TableStyles::parseDelimited("A,B,C\n1,2,3");
        $this->assertSame([['A', 'B', 'C'], ['1', '2', '3']], $csv);
    }

    // ---- presets ---------------------------------------------------------------

    /**
     * Every preset must define every key the renderer reads, or a table silently falls
     * back to nothing for that property. The dark preset in particular has to carry its
     * own text colour: seeded from the light default it drew dark text on its own dark
     * stripes and the table could not be read.
     */
    public function test_every_preset_is_complete(): void
    {
        $keys = ['name', 'textColor', 'bodyBg', 'headerBg', 'headerColor', 'headerWeight',
            'borderColor', 'borders', 'stripe', 'stripeBg', 'hover', 'hoverBg',
            'cellPaddingY', 'cellPaddingX', 'fontSize', 'radius'];

        foreach (TableStyles::presets() as $slug => $preset) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $preset, "preset {$slug} is missing {$key}");
            }
            $this->assertContains($preset['borders'], ['all', 'horizontal', 'none'], $slug);
        }
    }

    // ---- the element -----------------------------------------------------------

    /**
     * Cells hold pipes, brackets and quotes — the very characters shortcodes are made
     * of — so the grid travels as base64 JSON in the body.
     */
    public function test_shortcode_round_trip_keeps_every_cell(): void
    {
        $rows = [
            ['Setting', 'Type', 'Notes'],
            ['`preset`', 'string', 'one of **six** — see [docs](https://x.test/a)'],
            ['a | b', 'mixed', 'line one<br>line two'],
            ['[falcon_row]', 'string', 'a literal shortcode in a cell'],
        ];

        $layout = [[
            'id' => 'r1', 'settings' => [],
            'columns' => [[
                'id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%',
                'settings' => [],
                'elements' => [[
                    'id' => 'e1', 'type' => 'table', 'settings' => [
                        'rows' => $rows,
                        'cols' => [['align' => 'left', 'width' => '30%'], ['align' => 'center', 'width' => ''], ['align' => 'right', 'width' => '']],
                        'preset' => 'striped',
                        'headerRow' => true,
                        'headerCol' => true,
                        'caption' => 'A caption',
                        'sortable' => true,
                        'stickyHeader' => true,
                        'maxHeight' => 420,
                        'responsive' => 'stack',
                        'visibility' => ['mobile' => false, 'tablet' => true, 'desktop' => true],
                    ],
                ]],
            ]],
        ]];

        $shortcode = BuilderShortcodeConverter::jsonToShortcodes(json_encode($layout));
        $this->assertStringContainsString('[falcon_table', $shortcode);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($shortcode), true);
        $el = $back[0]['columns'][0]['elements'][0] ?? [];

        $this->assertSame('table', $el['type'] ?? null);
        $this->assertSame($rows, $el['settings']['rows'] ?? null, 'a cell did not survive the round trip');

        foreach (['preset', 'headerRow', 'headerCol', 'caption', 'sortable', 'stickyHeader', 'maxHeight', 'responsive', 'cols', 'visibility'] as $key) {
            $this->assertSame(
                $layout[0]['columns'][0]['elements'][0]['settings'][$key],
                $el['settings'][$key] ?? '<missing>',
                "setting {$key} changed"
            );
        }
    }

    /**
     * The body is a Markdown table, not a blob.
     *
     * A shortcode is a format people open, diff and edit by hand, and base64 makes all
     * three impossible — the grid was unreadable in the very place it is stored. Only
     * the one string that would truncate the body falls back, and says so.
     */
    public function test_the_body_is_a_readable_markdown_table(): void
    {
        $shortcode = $this->toShortcode([
            'rows' => [['Capability', 'Core'], ['Page builder', '[check]']],
            'cols' => [['align' => 'left', 'width' => '40%'], ['align' => 'center', 'width' => '']],
            'headerRow' => true,
        ]);

        $this->assertStringContainsString('| Capability | Core |', $shortcode);
        $this->assertStringContainsString('| Page builder | [check] |', $shortcode);
        $this->assertStringContainsString('| :--- | :---: |', $shortcode);
        $this->assertStringContainsString('col_align="left,center"', $shortcode);
        $this->assertStringContainsString('col_width="40%,"', $shortcode);
        $this->assertStringNotContainsString('enc="b64"', $shortcode);
    }

    /**
     * A pipe, a backslash and a newline all have meaning in a Markdown row, so all
     * three are escaped — and a cell ending in a backslash must not swallow the
     * separator after it, which is why the splitter scans rather than using a lookbehind.
     */
    public function test_awkward_cells_survive_the_markdown_body(): void
    {
        $rows = [
            ['A', 'B'],
            ['a | b', 'back\\slash'],
            ["two\nlines", 'ends with \\'],
            ['`code`', '**bold**'],
        ];

        $back = $this->roundTrip(['rows' => $rows, 'headerRow' => true]);

        $this->assertSame($rows, $back['rows'], 'a cell was mangled by the Markdown body');
    }

    /**
     * The only content that cannot live in a raw body is this element's own closing
     * tag — the parser stops at it. Such a table falls back to base64 and marks itself.
     */
    public function test_a_cell_holding_the_closing_tag_falls_back_to_base64(): void
    {
        $rows = [['A'], ['before [/falcon_table] after']];

        $shortcode = $this->toShortcode(['rows' => $rows, 'headerRow' => true]);
        $this->assertStringContainsString('enc="b64"', $shortcode);

        $back = $this->roundTrip(['rows' => $rows, 'headerRow' => true]);
        $this->assertSame($rows, $back['rows'], 'the escape hatch lost the cell it exists for');
    }

    /** Alignment survives even with no header row for an alignment line to sit under. */
    public function test_alignment_survives_without_a_header_row(): void
    {
        $back = $this->roundTrip([
            'rows' => [['a', 'b', 'c'], ['1', '2', '3']],
            'cols' => [['align' => 'right', 'width' => ''], ['align' => 'center', 'width' => ''], ['align' => 'left', 'width' => '']],
            'headerRow' => false,
        ]);

        $this->assertSame(
            ['right', 'center', 'left'],
            array_column($back['cols'], 'align')
        );
        $this->assertSame([['a', 'b', 'c'], ['1', '2', '3']], $back['rows'], 'the first row was eaten as a header');
    }

    /**
     * A readable body has to survive the classic editor.
     *
     * This is what a base64 blob bought and a Markdown body has to earn back. The
     * editor is HTML-oriented: it turns newlines into <br>, wraps blocks in <p>,
     * indents with &nbsp; and escapes stray characters as entities. Opened and saved
     * once, a table came back with its rows shredded — the element was still there, so
     * nothing looked broken until the content was read.
     */
    #[DataProvider('editorManglings')]
    public function test_a_table_survives_the_rich_editor(string $_name, callable $mangle): void
    {
        $rows = [
            ['Capability', 'Core'],
            ['Page builder', '[check]'],
            ['Multi-language', '[cross]'],
        ];

        $shortcode = $this->toShortcode([
            'rows' => $rows,
            'cols' => [['align' => 'left', 'width' => ''], ['align' => 'center', 'width' => '']],
            'headerRow' => true,
        ]);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($mangle($shortcode)), true);
        $el = $back[0]['columns'][0]['elements'][0] ?? [];

        $this->assertSame('table', $el['type'] ?? null);
        $this->assertSame($rows, $el['settings']['rows'] ?? null, 'the editor shredded the rows');
    }

    /** @return array<string, array{0: string, 1: callable}> */
    public static function editorManglings(): array
    {
        return [
            'untouched' => ['untouched', static fn (string $x) => $x],
            // The editor replaces the newline rather than adding to it, which is what
            // makes "no newlines left" a safe signal that a <br> was once one.
            'newlines to br' => ['newlines to br', static fn (string $x) => str_replace("\n", '<br />', $x)],
            'wrapped in p' => ['wrapped in p', static fn (string $x) => '<p>'.str_replace("\n\n", "</p>\n<p>", $x).'</p>'],
            'nbsp indents' => ['nbsp indents', static fn (string $x) => str_replace("\n", "\n&nbsp;", $x)],
            'entity pipes' => ['entity pipes', static fn (string $x) => str_replace('|', '&#124;', $x)],
            // Not something an editor does, but what a paste into a single-line field
            // leaves behind. The column count from col_align is what puts it back.
            'newlines collapsed' => ['newlines collapsed', static fn (string $x) => preg_replace('/\s*\n\s*/', ' ', $x)],
        ];
    }

    /** An empty cell inside a row is content and must survive the same recovery. */
    public function test_recovery_keeps_genuinely_empty_cells(): void
    {
        $rows = [['A', 'B', 'C'], ['1', '', '3'], ['', '5', '']];

        $shortcode = $this->toShortcode([
            'rows' => $rows,
            'cols' => [['align' => 'left', 'width' => ''], ['align' => 'left', 'width' => ''], ['align' => 'left', 'width' => '']],
            'headerRow' => true,
        ]);

        $collapsed = preg_replace('/\s*\n\s*/', ' ', $shortcode);
        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($collapsed), true);

        $this->assertSame($rows, $back[0]['columns'][0]['elements'][0]['settings']['rows'] ?? null);
    }

    private function toShortcode(array $settings): string
    {
        return BuilderShortcodeConverter::jsonToShortcodes(json_encode([[
            'id' => 'r1', 'settings' => [],
            'columns' => [[
                'id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%',
                'settings' => [],
                'elements' => [['id' => 'e1', 'type' => 'table', 'settings' => $settings]],
            ]],
        ]]));
    }

    private function roundTrip(array $settings): array
    {
        $back = json_decode(
            BuilderShortcodeConverter::shortcodesToJson($this->toShortcode($settings)),
            true
        );

        return $back[0]['columns'][0]['elements'][0]['settings'] ?? [];
    }

    /** A shortcode typed by hand, holding a Markdown table, must still build a table. */
    public function test_a_hand_written_shortcode_reads_a_markdown_body(): void
    {
        $sc = '[falcon_section id="r1" type="container"][falcon_col id="c1" width="100%"]'
            ."[falcon_table id=\"e1\" preset=\"docs\"]| A | B |\n|---|---|\n| 1 | 2 |[/falcon_table]"
            .'[/falcon_col][/falcon_section]';

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);
        $el = $back[0]['columns'][0]['elements'][0] ?? [];

        $this->assertSame('table', $el['type'] ?? null);
        $this->assertSame([['A', 'B'], ['1', '2']], $el['settings']['rows'] ?? null);
    }

    // ---- rendering -------------------------------------------------------------

    public function test_it_renders_a_table_with_a_header_and_formatted_cells(): void
    {
        $html = $this->render([
            'rows' => [['Setting', 'Default'], ['`preset`', '`docs`']],
            'cols' => [['align' => 'left', 'width' => ''], ['align' => 'right', 'width' => '']],
            'headerRow' => true,
        ]);

        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<th scope="col"', $html);
        $this->assertStringContainsString('<code>preset</code>', $html);
        $this->assertStringContainsString('text-align: right', $html, 'the column alignment was not applied');
    }

    /** An element with no rows must render nothing rather than an empty shell. */
    public function test_an_empty_table_renders_nothing(): void
    {
        $this->assertSame('', trim($this->render(['rows' => []])));
    }

    /**
     * Stacking is for phones, where a five-column table cannot be read and scrolling
     * hides the first column — the one that says what the row is about. Each cell then
     * needs its column name, which comes from the header.
     */
    public function test_stacking_labels_each_cell_with_its_column(): void
    {
        $html = $this->render([
            'rows' => [['Setting', 'Default'], ['preset', 'docs']],
            'headerRow' => true,
            'responsive' => 'stack',
        ]);

        $this->assertStringContainsString('data-label="Setting"', $html);
        $this->assertStringContainsString('data-label="Default"', $html);
        $this->assertStringContainsString('@media (max-width:', $html);
    }

    /** Sorting needs a header to click, so it must not arm itself without one. */
    public function test_sorting_is_not_offered_without_a_header_row(): void
    {
        $with = $this->render(['rows' => [['A'], ['1']], 'headerRow' => true, 'sortable' => true]);
        $without = $this->render(['rows' => [['A'], ['1']], 'headerRow' => false, 'sortable' => true]);

        $this->assertStringContainsString('data-fc-sortable="1"', $with);
        $this->assertStringNotContainsString('data-fc-sortable="1"', $without);
    }

    /**
     * The sort script is shared and must reach the page. It is emitted per table with a
     * runtime guard rather than wrapped in @once, because the theme layout renders the
     * builder's content twice per request and @once is spent on the copy that is only
     * scanned — which is exactly how the Code Block shipped broken.
     */
    public function test_the_sort_script_is_emitted_and_guarded(): void
    {
        $html = $this->render(['rows' => [['A'], ['1']], 'headerRow' => true, 'sortable' => true]);

        $this->assertStringContainsString('__falconTableSort', $html);
        $this->assertStringNotContainsString('@once', $html);
    }

    private function render(array $settings): string
    {
        return view('falcon-cms::frontend.builder.elements.table', ['el' => [
            'id' => 'e1', 'type' => 'table', 'settings' => $settings,
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

    /** The canvas cell formatter, reduced to what parity needs. Mirrors TableStyles::cell(). */
    private function mirrorScript(): string
    {
        return <<<'JS'
const fs = require('fs');
const rules = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'))
    .map(([n, p, r]) => [n, new RegExp(p, 'y'), r]);
const cells = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));

const esc = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

// One left-to-right scan, first matching rule wins, output never re-scanned —
// the same shape as TableStyles::cell(). The sticky flag is JavaScript's version
// of PCRE's A modifier, which the PHP side applies to these very patterns.
function cell(text) {
    const input = esc(text);
    let out = '', i = 0;
    while (i < input.length) {
        let matched = false;
        for (const [name, re, replacement] of rules) {
            re.lastIndex = i;
            const m = re.exec(input);
            if (!m || m[0] === '') continue;
            if (name === 'link') {
                const plain = m[2].replace(/&amp;/g, '&').replace(/&#039;/g, "'").replace(/&quot;/g, '"');
                out += '<a href="' + (/^\s*javascript:/i.test(plain) ? '#' : m[2]) + '">' + m[1] + '</a>';
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

process.stdout.write(JSON.stringify(cells.map(cell)));
JS;
    }
}
