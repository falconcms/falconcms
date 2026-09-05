<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\TocStyles;
use FalconCms\Core\Tests\TestCase;

/**
 * The Table of Contents element.
 *
 * This element is unusual: its list is built in the browser, because a table of
 * contents lists the headings AROUND it and those live in sibling elements the
 * front-end renderer never sees. TocStyles explains that at length.
 *
 * What that makes worth testing is the contract between the two sides. PHP and the
 * browser must slug a heading into an id the same way, or every link points at
 * nothing; the script must survive being on a page twice; and the element must render
 * nothing at all when there is nothing to list, rather than a heading over an empty
 * box.
 */
class TocTest extends TestCase
{
    /**
     * The Table of Contents is a Pro element, and the builder decides that from one
     * list. Left out of it, the element would be free on every site while the pricing
     * page said otherwise — and the gate is not only cosmetic: the same list stops a
     * locked element being edited, moved or dragged.
     */
    public function test_the_toc_is_gated_behind_pro(): void
    {
        $scripts = file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        preg_match('/const proElementTypes = \[(.*?)\];/s', $scripts, $m);
        $this->assertNotEmpty($m, 'the Pro element list is gone');
        $this->assertStringContainsString("'toc'", $m[1], 'the Table of Contents element is not gated behind Pro');
    }

    // ---- anchors ---------------------------------------------------------------

    /** The rule itself: lowercase, one dash between words, none at either end. */
    public function test_a_heading_becomes_a_slug(): void
    {
        $this->assertSame('getting-started', TocStyles::slug('Getting Started'));
        $this->assertSame('install-setup', TocStyles::slug('Install & Setup'));
        $this->assertSame('hello-world', TocStyles::slug('  Hello  --  World  '));
        $this->assertSame('2-6-5-release', TocStyles::slug('2.6.5 release'));
        $this->assertSame('', TocStyles::slug('   '));
    }

    /**
     * Non-Latin headings must stay addressable.
     *
     * Stripping to ASCII would slug every heading on a Bengali page to the empty string,
     * and a contents list whose links all point at the same nothing is worse than no
     * list at all. The combining marks matter too — without them a Bengali heading is
     * slugged down to its bare consonants, which is a different word.
     */
    public function test_a_non_latin_heading_keeps_its_letters(): void
    {
        $this->assertSame('ইনস্টলেশন-গাইড', TocStyles::slug('ইনস্টলেশন গাইড'));
        $this->assertSame('café', TocStyles::slug('Café'));
    }

    /**
     * Two sections with the same name is not a mistake an author should have to notice,
     * so the second gets its own id rather than a duplicate no browser would scroll to.
     */
    public function test_repeated_headings_get_their_own_anchors(): void
    {
        $out = TocStyles::outline([
            ['level' => 2, 'text' => 'Install'],
            ['level' => 2, 'text' => 'Install'],
            ['level' => 2, 'text' => 'Install'],
        ]);

        $this->assertSame(['h-install', 'h-install-2', 'h-install-3'], array_column($out, 'id'));
    }

    /** A heading that slugs to nothing still has to be linkable. */
    public function test_a_heading_with_no_letters_falls_back_to_its_position(): void
    {
        $out = TocStyles::outline([['level' => 2, 'text' => '★ ★ ★']]);

        $this->assertSame('section-1', $out[0]['id']);
    }

    /** Only the levels asked for are listed, and H1 is never one of them. */
    public function test_the_outline_keeps_only_the_levels_asked_for(): void
    {
        $headings = [
            ['level' => 1, 'text' => 'Page title'],
            ['level' => 2, 'text' => 'Section'],
            ['level' => 3, 'text' => 'Subsection'],
            ['level' => 4, 'text' => 'Detail'],
        ];

        $this->assertSame(['Section', 'Subsection'], array_column(TocStyles::outline($headings, 2, 3), 'text'));
        $this->assertSame(['Section'], array_column(TocStyles::outline($headings, 2, 2), 'text'));
        $this->assertSame(['Subsection', 'Detail'], array_column(TocStyles::outline($headings, 3, 4), 'text'));
        $this->assertNotContains('Page title', array_column(TocStyles::outline($headings, 2, 6), 'text'));
    }

    /**
     * PHP and the browser must agree on every anchor.
     *
     * This is the whole contract. If the two slug rules drift, the links the script
     * writes point at ids that do not exist and nothing scrolls — with nothing in the
     * page or the log to say why. The script's own slug function is lifted out of the
     * template and run in node against the same headings.
     */
    public function test_php_and_the_browser_slug_headings_identically(): void
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('node is not on PATH; cannot check cross-engine parity');
        }

        $template = (string) file_get_contents(
            __DIR__.'/../../../resources/views/frontend/builder/elements/toc.blade.php'
        );

        $this->assertSame(1, preg_match('/\n    function slug\(text\) \{.*?\n    \}\n/s', $template, $slugFn),
            'the slug function is no longer where the test can find it');
        $this->assertSame(1, preg_match('/\n    function anchorId\(s, seen, index\) \{.*?\n    \}\n/s', $template, $idFn),
            'the anchorId function is no longer where the test can find it');

        $headings = [
            'Getting Started', 'Install & Setup', 'ইনস্টলেশন গাইড', 'Café',
            '2.6.5 release', '  Hello  --  World  ', 'Install', 'Install', '★ ★ ★',
            'Why <em>this</em> works', "Tabs\tand spaces",
        ];

        $dir = sys_get_temp_dir().'/fc-toc-'.getmypid();
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/headings.json', json_encode($headings, JSON_UNESCAPED_UNICODE));
        file_put_contents($dir.'/run.js',
            $slugFn[0]."\n".$idFn[0]."\n"
            ."const fs = require('fs');\n"
            ."const headings = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));\n"
            ."const seen = {};\n"
            ."const out = headings.map((text, i) => {\n"
            ."    const s = slug(text);\n"
            ."    const count = seen[s] || 0;\n"
            ."    seen[s] = count + 1;\n"
            ."    return anchorId(s, count, i);\n"
            ."});\n"
            ."process.stdout.write(JSON.stringify(out));\n"
        );

        $out = shell_exec(
            escapeshellarg($node).' '.escapeshellarg($dir.'/run.js').' '.escapeshellarg($dir.'/headings.json').' 2>&1'
        );

        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);

        $js = json_decode((string) $out, true);
        $this->assertIsArray($js, "the browser's slug rule did not return JSON:\n".$out);

        $php = array_column(
            TocStyles::outline(array_map(static fn ($t) => ['level' => 2, 'text' => $t], $headings), 2, 2),
            'id'
        );

        $this->assertSame($php, $js, 'PHP and the browser disagree about a heading anchor');
    }

    /**
     * The canvas preview must read both spellings of a heading's tag.
     *
     * The two heading-ish elements disagree and always have: the Heading stores its tag
     * as `tag`, the Title as `htmlTag`. Reading only one of them made every Heading on
     * the page look like an H2 in the preview, so the list came out flat however the
     * author had nested their sections — while the published page, which reads the right
     * key, nested it correctly. The two previews disagreed about the shape of the page.
     */
    public function test_the_canvas_preview_reads_both_heading_tag_keys(): void
    {
        $scripts = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        $this->assertSame(1, preg_match('/function fcTocScan\(\)[\s\S]*?
            \}/', $scripts, $m),
            'the canvas heading scan is gone');

        $this->assertStringContainsString('s.tag || s.htmlTag', $m[0],
            'the canvas reads only one of the two tag keys, so one element previews flat');
    }

    /**
     * The section being read is worked out from where the headings are, not from a band.
     *
     * This was an IntersectionObserver watching a strip a little way down the viewport,
     * which is the cheaper design and the wrong one: a heading only counted while it sat
     * inside the strip. Clicking a link puts that heading at the very top — above the
     * strip — so the section the reader had just jumped to was not the highlighted one;
     * at the top of a page the highlight sat on the SECOND heading for the same reason;
     * and nothing was highlighted at all while the reader was in the middle of a section
     * longer than the strip. Position answers the question directly and is true however
     * the reader got there.
     */
    public function test_the_spy_is_position_based_rather_than_a_viewport_band(): void
    {
        $script = $this->script($this->render([]));

        $this->assertStringContainsString('function wireScrollState', $script,
            'the scroll-driven state handler is gone');
        $this->assertStringNotContainsString('new IntersectionObserver', $script,
            'the spy is back on a viewport band, which loses the section on every jump');

        // The current section is the last heading the reader has scrolled past.
        $this->assertStringContainsString('getBoundingClientRect().top <= line()', $script);

        // With nothing scrolled past yet, the first section is the one being read.
        $this->assertStringContainsString('items.length ? items[0].id : null', $script);
    }

    /**
     * Entry hover has to reach the canvas, which means it has to be in a stylesheet.
     *
     * The canvas paints each entry with an inline style, and a hover cannot be written
     * inline at all — so the Design tab's Entry hover colour did nothing there while
     * working perfectly on the published page, which is the exact failure the Table's
     * row hover had. An inline colour would beat a hover rule even once one existed, so
     * the colour has to leave the inline style as well; only the indentation stays.
     */
    public function test_the_canvas_puts_the_entry_colours_in_a_stylesheet(): void
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('node is not on PATH; cannot run the canvas helpers');
        }

        $scripts = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        $lifted = [];
        foreach (['fcTocCss\(el\)', 'fcTocItemStyle\(el, item\)', 'fcTocTypoCss\(el, prefix\)'] as $sig) {
            $this->assertSame(1, preg_match('/
            function '.$sig.' \{[\s\S]*?
            \}
/', $scripts, $m),
                "the canvas helper {$sig} is no longer where the test can find it");
            $lifted[] = $m[0];
        }

        $dir = sys_get_temp_dir().'/fc-toccanvas-'.getmypid();
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/run.js',
            'const P = '.json_encode(TocStyles::presets()).';
'
            ."function fcTocPreset(e) { return P[(e.settings || {}).preset || 'card'] || P.card; }
"
            .'function fcTocVal(e, k) { const s = e.settings || {};'
            ." return (s[k] !== undefined && s[k] !== null && s[k] !== '') ? s[k] : fcTocPreset(e)[k]; }
"
            ."function fcTocMarkerKind(e) { return fcTocVal(e, 'marker') || 'none'; }
"
            .'function fcTblTypo() { return {}; }
'
            ."const fcTocScopeId = (e) => 'fc-toc-canvas-' + String(e.id || '');
"
            .implode('', $lifted)
            ."const el = { id: 'e1', settings: { preset: 'card', linkColor: '#434E5A',"
            ." hoverColor: '#CC0000', activeColor: '#00AA00' } };
"
            .'process.stdout.write(JSON.stringify({'
            .' css: fcTocCss(el),'
            ." itemStyle: fcTocItemStyle(el, { depth: 1, text: 'x' })"
            .'}));
'
        );

        $out = shell_exec(escapeshellarg($node).' '.escapeshellarg($dir.'/run.js').' 2>&1');
        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);

        $result = json_decode((string) $out, true);
        $this->assertIsArray($result, 'the canvas helpers did not run:
'.$out);

        $this->assertStringContainsString('.fc-toc-item:hover { color:#CC0000; }', $result['css'],
            'the canvas has no hover rule, so Entry hover cannot show there');
        $this->assertStringContainsString('.fc-toc-item.is-active { color:#00AA00;', $result['css'],
            'the canvas has no active rule');
        $this->assertStringContainsString('color:#434E5A', $result['css'],
            'the entry colour is not in the stylesheet');

        // The active rule is written after the hover rule, so the section being read
        // still reads as active while the pointer is elsewhere in the list.
        $this->assertGreaterThan(
            strpos($result['css'], ':hover'),
            strpos($result['css'], 'is-active'),
            'the hover rule is written after the active one and would win over it'
        );

        // And nothing about colour is left inline, or it would beat both rules.
        $this->assertArrayNotHasKey('color', $result['itemStyle']);
        $this->assertArrayHasKey('paddingLeft', $result['itemStyle'],
            'the per-entry indentation is no longer applied');
    }

    /** The canvas template has to carry the class and the stylesheet those rules need. */
    public function test_the_canvas_template_is_wired_for_those_rules(): void
    {
        $canvas = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/components/elements/toc.blade.php'
        );

        $this->assertStringContainsString('v-text="fcTocCss(el)"', $canvas,
            'the canvas no longer emits the stylesheet');
        $this->assertStringContainsString('class="fc-toc-item"', $canvas,
            'the entries do not carry the class the rules target');
        $this->assertStringContainsString('fcTocScopeId(el)', $canvas,
            'the rules have nothing to be scoped to');
    }

    // ---- presets ---------------------------------------------------------------

    /** A preset short of a value leaves that rule unwritten in the stylesheet. */
    public function test_every_preset_is_complete(): void
    {
        $keys = ['name', 'bg', 'borderColor', 'borderWidth', 'radius', 'padY', 'padX',
            'titleColor', 'titleSize', 'titleWeight', 'linkColor', 'activeColor',
            'hoverColor', 'fontSize', 'itemGap', 'indent', 'guide', 'marker'];

        foreach (TocStyles::presets() as $slug => $preset) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $preset, "preset {$slug} has no {$key}");
            }
        }
    }

    /** An unknown preset must still render something rather than an unstyled list. */
    public function test_an_unknown_preset_falls_back(): void
    {
        $this->assertSame('Card', TocStyles::preset('nonsense')['name']);
        $this->assertSame('Card', TocStyles::preset(null)['name']);
    }

    /**
     * The Scope and Skip fields take a selector, which is what makes the element work on
     * a theme it has never met — and a selector is free text going into an HTML
     * attribute.
     */
    public function test_a_selector_cannot_break_out_of_its_attribute(): void
    {
        $out = TocStyles::safeSelector('article" onload="alert(1)');

        $this->assertStringNotContainsString('"', $out);
        $this->assertStringNotContainsString('<', $out);

        // The selectors these fields are actually for survive intact.
        $this->assertSame('.entry-content', TocStyles::safeSelector('.entry-content'));
        $this->assertSame('main > article, #docs', TocStyles::safeSelector('main > article, #docs'));
        $this->assertSame('[data-toc-skip]', TocStyles::safeSelector('[data-toc-skip]'));
    }

    // ---- shortcode round trip --------------------------------------------------

    /** Everything an author sets must come back. */
    public function test_the_shortcode_round_trip_keeps_every_setting(): void
    {
        $settings = [
            'preset' => 'sidebar', 'title' => 'Contents',
            'minLevel' => 2, 'maxLevel' => 4,
            'scope' => '.entry-content', 'exclude' => '.no-toc',
            'collapsible' => true, 'openByDefault' => false,
            'sticky' => true, 'stickyTop' => 40, 'maxHeight' => 420,
            'scrollSpy' => false, 'smoothScroll' => false, 'scrollOffset' => 120,
            'progress' => true, 'backToTop' => true, 'minHeadings' => 3,
            'marker' => 'decimal',
            'linkColor' => '#434E5A', 'activeColor' => '#B9720F',
            'fontSize' => 15, 'itemGap' => 8, 'indent' => 16,
            'cssClass' => 'my-toc', 'cssId' => 'toc-1',
        ];

        $back = $this->roundTrip($settings);

        foreach ($settings as $key => $expected) {
            $this->assertSame($expected, $back[$key] ?? null, "the {$key} setting was lost");
        }
    }

    /**
     * The guide line is three-state — on, off, or "follow the preset" — and the round
     * trip has to keep all three. A plain boolean would turn an untouched element into a
     * permanent off the first time it was saved, and the preset could never move it again.
     */
    public function test_the_guide_keeps_its_third_state(): void
    {
        $this->assertSame('', $this->roundTrip(['guide' => ''])['guide'] ?? null);
        $this->assertTrue($this->roundTrip(['guide' => true])['guide'] ?? null);
        $this->assertFalse($this->roundTrip(['guide' => false])['guide'] ?? null);
    }

    /** A cleared title must stay cleared rather than growing back on the next save. */
    public function test_a_cleared_title_survives_the_round_trip(): void
    {
        $this->assertSame('', $this->roundTrip(['title' => ''])['title'] ?? null);
    }

    /** No body, so the shortcode is self-closing — there is nothing to put in one. */
    public function test_the_shortcode_is_self_closing(): void
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'toc', 'settings' => ['preset' => 'card']]]]],
        ]]));

        $this->assertStringContainsString('[falcon_toc', $sc);
        $this->assertStringContainsString('/]', $sc);
        $this->assertStringNotContainsString('[/falcon_toc]', $sc);
    }

    // ---- rendering -------------------------------------------------------------

    /** The element ships an empty list and the configuration the script needs to fill it. */
    public function test_it_renders_an_empty_list_and_its_configuration(): void
    {
        $html = $this->render(['title' => 'On this page', 'minLevel' => 2, 'maxLevel' => 4, 'scope' => '.entry-content']);

        $this->assertStringContainsString('data-fc-toc-list', $html);
        $this->assertStringContainsString('On this page', $html);

        $this->assertSame(1, preg_match('/data-fc-toc="([^"]*)"/', $html, $m));
        $config = json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true);

        $this->assertSame(2, $config['min']);
        $this->assertSame(4, $config['max']);
        $this->assertSame('.entry-content', $config['scope']);
    }

    /**
     * Nothing is shown until the script has filled it.
     *
     * A reader with JavaScript off must not get a heading over an empty box, and neither
     * must a reader on a page whose headings all turned out to be excluded — the script
     * simply never sets the attribute, and the rule below keeps the element invisible.
     */
    public function test_nothing_is_shown_until_the_list_is_filled(): void
    {
        $html = $this->render(['title' => 'On this page']);

        $this->assertMatchesRegularExpression('/#fc-toc-e1:not\(\[data-ready\]\)\s*\{\s*display:\s*none/', $html);

        // Only the script may set it, and only once it has entries to show. The rule
        // above names the attribute too, so the check is on the element, not the page.
        $this->assertSame(1, preg_match('/<nav[^>]*>/', $html, $tag));
        $this->assertStringNotContainsString('data-ready', $tag[0]);
    }

    /** An out-of-range level in a hand-written shortcode must not reach the page. */
    public function test_an_impossible_level_range_is_corrected(): void
    {
        $html = $this->render(['minLevel' => 9, 'maxLevel' => 1]);

        $this->assertSame(1, preg_match('/data-fc-toc="([^"]*)"/', $html, $m));
        $config = json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true);

        $this->assertContains($config['min'], TocStyles::LEVELS);
        $this->assertContains($config['max'], TocStyles::LEVELS);
        $this->assertGreaterThanOrEqual($config['min'], $config['max']);
    }

    /**
     * The script is guarded by a window flag rather than @once.
     *
     * @once is not reliable here: the theme layout renders builder content twice per
     * request, once to scan it for icon libraries, so the directive has already fired by
     * the time the visible pass runs and the script never reaches the page. The Code
     * Block element hit exactly this.
     */
    public function test_the_script_is_emitted_and_guarded(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('window.__falconToc', $html);
        $this->assertStringNotContainsString('@once', $html);
    }

    /** Two on one page must not run the script twice, and both must still be filled. */
    public function test_two_on_a_page_share_one_script(): void
    {
        $a = $this->render(['cssId' => 'a']);
        $b = $this->render(['cssId' => 'b']);
        $twice = $a.$b;

        $this->assertSame(2, substr_count($this->markup($a).$this->markup($b), 'data-fc-toc-list'));
        // The flag is what makes the second copy a no-op, so both may be emitted.
        $this->assertSame(2, substr_count($twice, 'if (window.__falconToc) return;'));
    }

    /**
     * The heading search must not stop at the first ancestor that looks like content.
     *
     * A table of contents is usually beside the article or in a row above it, so its
     * nearest matching ancestor very often contains the list and none of the headings.
     * Taking that one and stopping is how the element renders nothing on exactly the
     * layout it is most used in, with nothing on the page to say why. The script keeps
     * walking up until one of the candidates actually holds enough headings.
     */
    public function test_the_scope_search_widens_until_it_finds_headings(): void
    {
        $script = $this->script($this->render([]));

        $this->assertStringContainsString('function scopeCandidates', $script,
            'the scope search is gone');
        $this->assertStringNotContainsString('function findScope', $script,
            'the old single-answer scope search is back');

        // Every candidate is collected, not just the first that matches.
        $this->assertMatchesRegularExpression('/for \(var c = 0; c < candidates\.length; c\+\+\)/', $script,
            'the script no longer tries more than one scope');
        $this->assertStringContainsString('document.body', $script,
            'the widest scope is no longer a fallback');
    }

    /**
     * An explicit scope is an instruction, not a hint.
     *
     * An author who scoped the list to one region meant it, so widening past it when it
     * turns out to be empty would list headings they had deliberately excluded.
     */
    public function test_an_explicit_scope_is_not_widened_past(): void
    {
        $script = $this->script($this->render(['scope' => '.entry-content']));

        $this->assertMatchesRegularExpression(
            '/if \(cfg\.scope\) \{\s*var picked = document\.querySelector\(cfg\.scope\);\s*if \(picked\) return \[picked\];/',
            $script,
            'an explicit scope no longer wins outright'
        );
    }

    /** Sticky, progress and back-to-top each have to reach the page when asked for. */
    public function test_the_advanced_options_reach_the_page(): void
    {
        $plain = $this->markup($this->render([]));
        $this->assertStringNotContainsString('data-fc-toc-bar', $plain);
        $this->assertStringNotContainsString('data-fc-toc-top', $plain);
        $this->assertStringNotContainsString('position: sticky', $plain);

        $full = $this->render(['sticky' => true, 'stickyTop' => 40, 'progress' => true, 'backToTop' => true]);
        $this->assertStringContainsString('data-fc-toc-bar', $this->markup($full));
        $this->assertStringContainsString('data-fc-toc-top', $this->markup($full));
        $this->assertStringContainsString('position: sticky; top: 40px', $full);
    }

    /** Collapsible is <details>, so it works with a keyboard and with find-in-page. */
    public function test_collapsible_renders_a_details_element(): void
    {
        $open = $this->render(['title' => 'Contents', 'collapsible' => true, 'openByDefault' => true]);
        $this->assertStringContainsString('<details', $open);
        $this->assertStringContainsString('<summary', $open);
        $this->assertMatchesRegularExpression('/<details[^>]*\sopen/', $open);

        $plain = $this->render(['title' => 'Contents']);
        $this->assertStringContainsString('<nav', $plain);
        $this->assertStringNotContainsString('<details', $plain);
    }

    /** An emptied colour must not reach the page as `color: ;`, which drops the rule. */
    public function test_an_emptied_colour_falls_back_to_the_preset(): void
    {
        $html = $this->render(['preset' => 'card', 'activeColor' => '', 'linkColor' => '']);

        $this->assertStringNotContainsString('color: ;', $html);
        $this->assertStringContainsString('--fc-toc-active: '.TocStyles::preset('card')['activeColor'], $html);
    }

    /** Typography from the shared control has to reach the stylesheet. */
    public function test_typography_reaches_the_rendered_list(): void
    {
        $html = $this->render(['toc_title_transform' => 'uppercase', 'toc_item_letter_spacing' => '0.01em']);

        $this->assertStringContainsString('text-transform: uppercase', $html);
        $this->assertStringContainsString('letter-spacing: 0.01em', $html);
    }

    // ---- helpers ---------------------------------------------------------------

    /** @return array<string, mixed> */
    private function roundTrip(array $settings): array
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'toc', 'settings' => $settings]]]],
        ]]));

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);

        return $back[0]['columns'][0]['elements'][0]['settings'] ?? [];
    }

    private function render(array $settings): string
    {
        return view('falcon-cms::frontend.builder.elements.toc', ['el' => [
            'id' => 'e1', 'type' => 'toc', 'settings' => $settings,
        ]])->render();
    }

    /**
     * The rendered element without its script.
     *
     * The script names every attribute it is going to set — data-ready, the progress
     * bar, the list — so searching the whole output for one of them finds the code that
     * writes it rather than the markup that has it. This is the markup half.
     */
    private function markup(string $html): string
    {
        $at = strpos($html, '<script>');

        return $at === false ? $html : substr($html, 0, $at);
    }

    /** The script half, which is what the two scope tests are about. */
    private function script(string $html): string
    {
        $at = strpos($html, '<script>');

        return $at === false ? '' : substr($html, $at);
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
