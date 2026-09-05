<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Models\NavigationMenu;
use FalconCms\Core\Models\NavigationMenuItem;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\PrevNext;
use FalconCms\Core\Tests\TestCase;

/**
 * The Previous / Next element.
 *
 * The links are the easy part. What decides whether this element is any good is where
 * the ORDER comes from, so that is what most of this file is about: a menu read the way
 * a reader goes through it, a page found in that list by path rather than by string, and
 * nothing at all for a page that is not in the sequence.
 */
class PrevNextTest extends TestCase
{
    /**
     * Previous / Next is a Pro element, and the builder decides that from one list. Left
     * out of it the element would be free on every site while the pricing page said
     * otherwise — and the same list stops a locked element being edited or dragged.
     */
    public function test_it_is_gated_behind_pro(): void
    {
        $scripts = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        preg_match('/const proElementTypes = \[(.*?)\];/s', $scripts, $m);
        $this->assertNotEmpty($m, 'the Pro element list is gone');
        $this->assertStringContainsString("'prev_next'", $m[1]);
    }

    // ---- reading a menu --------------------------------------------------------

    /**
     * A menu is flattened depth first: an item, then everything under it, then the next
     * item. That is what a sidebar means — clicking down the list and continuing into
     * each section — and it is the only reading of a nested menu that gives a sequence.
     */
    public function test_a_menu_is_flattened_in_reading_order(): void
    {
        $menu = $this->menu([
            ['Introduction', '/docs/intro', []],
            ['Guides', '/docs/guides', [
                ['Installing', '/docs/install'],
                ['Configuring', '/docs/config'],
            ]],
            ['Reference', '/docs/reference', []],
        ]);

        $this->assertSame(
            ['Introduction', 'Guides', 'Installing', 'Configuring', 'Reference'],
            array_column(PrevNext::flatten($menu->id), 'title')
        );
    }

    /**
     * A parent whose only job is to open a submenu is saved with "#" or nothing, and
     * stepping onto it would take the reader nowhere.
     */
    public function test_items_that_go_nowhere_are_skipped(): void
    {
        $menu = $this->menu([
            ['Section', '#', [
                ['First', '/docs/first'],
                ['Second', '/docs/second'],
            ]],
        ]);

        $this->assertSame(['First', 'Second'], array_column(PrevNext::flatten($menu->id), 'title'));
    }

    /** An empty or missing menu is an empty list, not an error on the page. */
    public function test_a_missing_menu_is_simply_empty(): void
    {
        $this->assertSame([], PrevNext::flatten(null));
        $this->assertSame([], PrevNext::flatten(999999));
    }

    // ---- finding the page ------------------------------------------------------

    /**
     * The page is found by path.
     *
     * A menu holds whatever an editor pasted — absolute one day, relative the next, with
     * or without a trailing slash — while the page being rendered always knows its own,
     * fully qualified. Comparing them as strings almost never matched.
     */
    public function test_the_current_page_is_matched_by_path(): void
    {
        $list = [
            ['title' => 'One', 'url' => 'https://example.test/docs/one'],
            ['title' => 'Two', 'url' => '/docs/two/'],
            ['title' => 'Three', 'url' => 'https://example.test/docs/three'],
        ];

        foreach (['/docs/two', 'https://example.test/docs/two', '/docs/two/'] as $current) {
            $pair = PrevNext::neighbours($list, $current);

            $this->assertSame('One', $pair['prev']['title'] ?? null, "spelled as {$current}");
            $this->assertSame('Three', $pair['next']['title'] ?? null, "spelled as {$current}");
        }
    }

    /** The ends of the list have one neighbour each, not a wrap-around. */
    public function test_the_first_and_last_entries_have_one_side_only(): void
    {
        $list = [
            ['title' => 'One', 'url' => '/one'],
            ['title' => 'Two', 'url' => '/two'],
            ['title' => 'Three', 'url' => '/three'],
        ];

        $first = PrevNext::neighbours($list, '/one');
        $this->assertNull($first['prev']);
        $this->assertSame('Two', $first['next']['title']);

        $last = PrevNext::neighbours($list, '/three');
        $this->assertSame('Two', $last['prev']['title']);
        $this->assertNull($last['next']);
    }

    /**
     * A page that is not in the list has no neighbours in it.
     *
     * Guessing — the first two entries, say — would put a Previous and Next on a landing
     * page that is not part of the sequence at all, and the author would have no way to
     * tell that from the element working.
     */
    public function test_a_page_outside_the_list_gets_nothing(): void
    {
        $pair = PrevNext::neighbours([
            ['title' => 'One', 'url' => '/one'],
            ['title' => 'Two', 'url' => '/two'],
        ], '/somewhere-else');

        $this->assertNull($pair['prev']);
        $this->assertNull($pair['next']);
    }

    // ---- overrides -------------------------------------------------------------

    /**
     * A link set by hand wins its own side — that is the escape hatch for the one page in
     * a set that goes somewhere else, and it has to be per side, because wanting to
     * override one of them is not wanting to type both.
     */
    public function test_a_manual_link_overrides_only_its_own_side(): void
    {
        $menu = $this->menu([
            ['One', '/one', []],
            ['Two', '/two', []],
            ['Three', '/three', []],
        ]);

        $post = $this->page('two');

        $pair = PrevNext::resolve([
            'source' => 'menu', 'menuId' => $menu->id,
            'nextUrl' => '/reference', 'nextTitle' => 'API reference',
        ], $post);

        $this->assertSame('One', $pair['prev']['title'], 'the untouched side stopped following the menu');
        $this->assertSame('API reference', $pair['next']['title']);
        $this->assertSame('/reference', $pair['next']['url']);
    }

    /** A title on its own renames what was found rather than replacing it. */
    public function test_a_title_without_a_url_only_renames(): void
    {
        $menu = $this->menu([['One', '/one', []], ['Two', '/two', []]]);

        $pair = PrevNext::resolve([
            'source' => 'menu', 'menuId' => $menu->id, 'prevTitle' => 'Back to the start',
        ], $this->page('two'));

        $this->assertSame('Back to the start', $pair['prev']['title']);
        $this->assertStringContainsString('/one', $pair['prev']['url'], 'the renamed link lost its target');
    }

    /** The manual source ignores the menu entirely. */
    public function test_the_manual_source_uses_only_what_was_typed(): void
    {
        $menu = $this->menu([['One', '/one', []], ['Two', '/two', []]]);

        $pair = PrevNext::resolve([
            'source' => 'manual', 'menuId' => $menu->id,
            'prevUrl' => '/a', 'nextUrl' => '/b',
        ], $this->page('two'));

        $this->assertSame('/a', $pair['prev']['url']);
        $this->assertSame('/b', $pair['next']['url']);
    }

    // ---- presets ---------------------------------------------------------------

    /** A preset short of a value leaves that rule unwritten in the stylesheet. */
    public function test_every_preset_is_complete(): void
    {
        $keys = ['name', 'bg', 'borderColor', 'borderWidth', 'radius', 'padY', 'padX', 'gap',
            'labelColor', 'labelSize', 'titleColor', 'titleSize', 'titleWeight',
            'arrowColor', 'hoverBorder'];

        foreach (PrevNext::presets() as $slug => $preset) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $preset, "preset {$slug} has no {$key}");
            }
        }
    }

    /** An unknown preset still renders something rather than an unstyled pair. */
    public function test_an_unknown_preset_falls_back(): void
    {
        $this->assertSame('Cards', PrevNext::preset('nonsense')['name']);
        $this->assertSame('Cards', PrevNext::preset(null)['name']);
    }

    // ---- rendering -------------------------------------------------------------

    /**
     * A page with nowhere to go renders nothing at all.
     *
     * An empty pair of boxes under the content would leave an author to work out for
     * themselves that the element was fine and the menu was not.
     */
    public function test_nothing_renders_when_there_is_nowhere_to_go(): void
    {
        $this->assertSame('', trim($this->render(['source' => 'manual'], null)));
    }

    /** Both links, their labels and their arrows have to reach the page. */
    public function test_it_renders_both_links(): void
    {
        $html = $this->render([
            'source' => 'manual',
            'prevUrl' => '/docs/install', 'prevTitle' => 'Installing',
            'nextUrl' => '/docs/config', 'nextTitle' => 'Configuring',
        ], null);

        $this->assertStringContainsString('href="/docs/install"', $html);
        $this->assertStringContainsString('Installing', $html);
        $this->assertStringContainsString('href="/docs/config"', $html);
        $this->assertStringContainsString('Configuring', $html);

        // rel tells a browser and a crawler what the sequence is, which is the whole
        // point of marking these two links up rather than writing two plain ones.
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
    }

    /** A lone Next keeps the right-hand column, where a reader looks for it. */
    public function test_a_single_link_keeps_its_own_side(): void
    {
        $html = $this->render(['source' => 'manual', 'nextUrl' => '/b', 'nextTitle' => 'B'], null);

        $this->assertStringContainsString('.fc-pn-next:only-child { grid-column: 2; }', $html);
        $this->assertStringNotContainsString('fc-pn-prev', $html);
    }

    /** A cleared label shows only the page title rather than growing "Previous" back. */
    public function test_a_cleared_label_is_not_rendered(): void
    {
        $html = $this->render([
            'source' => 'manual', 'prevUrl' => '/a', 'prevTitle' => 'A', 'prevLabel' => '',
        ], null);

        // The class is always defined in the stylesheet; what must not exist is a tag
        // wearing it.
        $this->assertStringNotContainsString('<span class="fc-pn-label">', $html);
        $this->assertStringContainsString('<span class="fc-pn-title">A</span>', $html);
    }

    /** An emptied colour must not reach the page as `background: ;`. */
    public function test_an_emptied_colour_falls_back_to_the_preset(): void
    {
        $html = $this->render([
            'source' => 'manual', 'nextUrl' => '/b', 'nextTitle' => 'B', 'bg' => '', 'titleColor' => '',
        ], null);

        $this->assertStringNotContainsString('background: ;', $html);
        $this->assertStringContainsString('background: '.PrevNext::preset('cards')['bg'], $html);
    }

    /** Typography from the shared control has to reach the stylesheet. */
    public function test_typography_reaches_the_page(): void
    {
        $html = $this->render([
            'source' => 'manual', 'nextUrl' => '/b', 'nextTitle' => 'B',
            'pn_title_letter_spacing' => '2', 'pn_label_transform' => 'lowercase',
        ], null);

        $this->assertStringContainsString('letter-spacing: 2px', $html);
        $this->assertStringContainsString('text-transform: lowercase', $html);
    }

    // ---- shortcode round trip --------------------------------------------------

    public function test_the_shortcode_round_trip_keeps_every_setting(): void
    {
        $settings = [
            'preset' => 'soft', 'source' => 'type',
            'orderBy' => 'date', 'orderDir' => 'desc',
            'prevLabel' => 'Back', 'nextLabel' => 'Onwards',
            'showTitles' => false, 'showArrows' => false,
            'prevUrl' => '/a', 'prevTitle' => 'A', 'nextUrl' => '/b', 'nextTitle' => 'B',
            'gap' => 24, 'radius' => 12, 'padY' => 20, 'padX' => 22,
            'titleColor' => '#112233', 'arrowColor' => '#445566',
            'cssClass' => 'my-pn', 'cssId' => 'pn-1',
        ];

        $back = $this->roundTrip($settings);

        foreach ($settings as $key => $expected) {
            $this->assertSame($expected, $back[$key] ?? null, "the {$key} setting was lost");
        }
    }

    /** A cleared label must stay cleared through a save. */
    public function test_a_cleared_label_survives_the_round_trip(): void
    {
        $back = $this->roundTrip(['prevLabel' => '', 'nextLabel' => 'Next']);

        $this->assertSame('', $back['prevLabel'] ?? null);
    }

    /** No body: the links are worked out when the page renders. */
    public function test_the_shortcode_is_self_closing(): void
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'prev_next', 'settings' => ['preset' => 'cards']]]]],
        ]]));

        $this->assertStringContainsString('[falcon_prev_next', $sc);
        $this->assertStringContainsString('/]', $sc);
        $this->assertStringNotContainsString('[/falcon_prev_next]', $sc);
    }

    // ---- the canvas ------------------------------------------------------------

    /**
     * The canvas shows the page's real neighbours, not a sample.
     *
     * It cannot work them out for itself — the menus and the sibling pages are database
     * rows — so the answers are computed once on the server for every source the author
     * might switch to. That also means there is no second copy of the ordering in
     * JavaScript to drift away from this one.
     */
    public function test_the_canvas_is_handed_the_real_answers(): void
    {
        $menu = $this->menu([['One', '/one', []], ['Two', '/two', []], ['Three', '/three', []]]);
        $preview = PrevNext::previewFor($this->page('two'));

        $this->assertArrayHasKey((string) $menu->id, $preview['menus']);
        $this->assertSame('One', $preview['menus'][(string) $menu->id]['prev']['title']);
        $this->assertSame('Three', $preview['menus'][(string) $menu->id]['next']['title']);

        // Every ordering the panel offers has an answer waiting, so switching one shows
        // the real result rather than nothing.
        foreach (PrevNext::ORDERS as $order) {
            foreach (['asc', 'desc'] as $direction) {
                $this->assertArrayHasKey($order.':'.$direction, $preview['types']);
            }
        }

        $scripts = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );
        $this->assertStringContainsString('PrevNext::previewFor', $scripts,
            'the canvas no longer receives the real neighbours');
    }

    /** No post — a section or a mega menu being edited — must not blow up. */
    public function test_the_preview_survives_having_no_post(): void
    {
        $this->assertSame(['menus' => [], 'types' => []], PrevNext::previewFor(null));
    }

    // ---- helpers ---------------------------------------------------------------

    /** @param array<int, array{0: string, 1: string, 2: array<int, array{0: string, 1: string}>}> $items */
    private function menu(array $items): NavigationMenu
    {
        $menu = NavigationMenu::create(['name' => 'Docs', 'slug' => 'docs-'.uniqid(), 'lang_code' => 'en']);

        foreach ($items as $i => [$title, $url, $children]) {
            $parent = NavigationMenuItem::create([
                'navigation_menu_id' => $menu->id, 'title' => $title, 'url' => $url, 'order' => $i,
            ]);

            foreach ($children as $c => [$childTitle, $childUrl]) {
                NavigationMenuItem::create([
                    'navigation_menu_id' => $menu->id, 'parent_id' => $parent->id,
                    'title' => $childTitle, 'url' => $childUrl, 'order' => $c,
                ]);
            }
        }

        return $menu;
    }

    private function page(string $slug): Post
    {
        return Post::create([
            'title' => ucfirst($slug), 'slug' => $slug, 'type' => 'page',
            'status' => 'published', 'lang_code' => 'en', 'content' => '',
        ]);
    }

    /** @return array<string, mixed> */
    private function roundTrip(array $settings): array
    {
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'columns' => [['elements' => [['type' => 'prev_next', 'settings' => $settings]]]],
        ]]));

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);

        return $back[0]['columns'][0]['elements'][0]['settings'] ?? [];
    }

    private function render(array $settings, ?Post $post): string
    {
        return view('falcon-cms::frontend.builder.elements.prev-next', [
            'el' => ['id' => 'e1', 'type' => 'prev_next', 'settings' => $settings],
            'post' => $post,
        ])->render();
    }
}
