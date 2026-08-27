<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * The Menu Item Options icon picker (resources/views/admin/menus/index.blade.php) offered a
 * hand-picked subset of about a hundred icons, while the Falcon Builder's own icon picker
 * (used for the same purpose everywhere else in the builder) offers every icon it ships with:
 * Font Awesome (Solid, Regular, Brands — ~2000) plus four more sets (Bootstrap, Remix,
 * Boxicons, Lucide). An icon existed the moment you were picking it for an element, but not
 * the moment you were picking it for a menu item — and fixing only the Font Awesome half of
 * that gap would still have left the other four sets missing.
 *
 * falcon_font_awesome_icons() is the Font Awesome catalog, read from one JSON data file
 * (resources/font-awesome-icons.json — same storage convention as the Google Fonts catalog).
 * falcon_all_builder_icons() adds the other four sets on top of it (parsed from their own
 * bundled CSS by falcon_icon_set_names(), the same way the builder's own picker gets them) —
 * that combined list is what the Menu Item Options picker actually reads, so every set stays
 * available wherever "all our icons" is meant.
 */
class FontAwesomeIconsTest extends TestCase
{
    public function test_the_catalog_returns_the_full_icon_set(): void
    {
        $icons = falcon_font_awesome_icons();

        $this->assertIsArray($icons);
        // Not an exact count — the data file can grow — but a hand-picked ~100-icon subset
        // must never be mistaken for "the full set" again.
        $this->assertGreaterThan(1000, count($icons),
            'the icon catalog looks like a curated subset again, not the full set');
    }

    public function test_the_catalog_includes_solid_regular_and_brand_icons(): void
    {
        $icons = falcon_font_awesome_icons();

        $this->assertContains('fas fa-house', $icons, 'no Solid icons in the catalog');
        $this->assertContains('far fa-heart', $icons, 'no Regular icons in the catalog');
        $this->assertContains('fab fa-facebook-f', $icons, 'no Brand icons in the catalog');
    }

    public function test_the_catalog_has_no_duplicates(): void
    {
        $icons = falcon_font_awesome_icons();

        $this->assertCount(count(array_unique($icons)), $icons,
            'the icon catalog contains duplicate entries');
    }

    /**
     * falcon_icon_set_names() parses each set's bundled CSS on demand rather than shipping a
     * static list — Bootstrap, Remix and Boxicons all exist and are configured in
     * falcon_icon_sets(), so this only fails if their asset files ever go missing from the
     * package, which would be a packaging bug worth catching here.
     */
    public function test_the_combined_catalog_includes_every_extra_icon_set(): void
    {
        $icons = falcon_all_builder_icons();

        foreach (falcon_icon_sets() as $slug => $def) {
            $setIcons = falcon_icon_set_names($slug);
            $this->assertNotEmpty($setIcons, "the {$def['label']} icon set parsed to zero icons — its asset file may be missing");
            $this->assertContains($setIcons[0], $icons,
                "the combined catalog is missing icons from the {$def['label']} set");
        }
    }

    public function test_the_combined_catalog_still_includes_font_awesome(): void
    {
        $this->assertContains('fas fa-house', falcon_all_builder_icons(),
            'the combined catalog dropped Font Awesome when the extra sets were added');
    }

    public function test_the_menu_item_icon_picker_reads_the_combined_catalog(): void
    {
        $source = file_get_contents(
            __DIR__.'/../../../resources/views/admin/menus/index.blade.php'
        );

        $this->assertStringContainsString('MI_ICONS = @json(falcon_all_builder_icons())', $source,
            'the menu item icon picker no longer reads the combined icon catalog');
        $this->assertStringNotContainsString("'fa fa-house','fa fa-home'", $source,
            'the old hand-picked ~100-icon list is still hardcoded alongside the shared catalog');
    }

    /**
     * Font Awesome loads unconditionally site-wide already, but Bootstrap/Remix/Boxicons/
     * Lucide only load on pages that use them (falcon_icon_set_links()) — the admin menus
     * screen isn't scanning its own rendered HTML for icon usage the way the front end does,
     * so the picker needs these four loaded directly or every icon from them renders blank.
     */
    public function test_the_extra_icon_set_stylesheets_are_loaded_on_the_menus_screen(): void
    {
        $source = file_get_contents(
            __DIR__.'/../../../resources/views/admin/menus/index.blade.php'
        );

        $this->assertStringContainsString('falcon_icon_sets() as $__iconSet', $source,
            'the menus admin screen no longer loads the extra icon set stylesheets — Bootstrap/Remix/Boxicons/Lucide icons would render blank in the picker');
    }

    /**
     * ~10,000 icons across five fonts rendered as DOM nodes at once — on open, before any
     * search — is a real jank problem, not a hypothetical one. A flat cutoff (render the first
     * 300 and stop) fixed the jank but made 9,800+ icons permanently unreachable without
     * knowing their exact search term, which is exactly what "I can only see about 300 icons"
     * was reporting. Pagination keeps the fast initial render and keeps every icon reachable
     * by paging, not just by search.
     */
    public function test_the_icon_grid_paginates_instead_of_hard_capping(): void
    {
        $source = file_get_contents(
            __DIR__.'/../../../resources/views/admin/menus/index.blade.php'
        );

        $this->assertStringNotContainsString('.slice(0, 300)', $source,
            'the icon grid is back to a hard cutoff — everything past the first page becomes unreachable again');
        $this->assertStringContainsString('miShowMoreIcons', $source,
            'the icon grid has no way to page past the first batch of icons');
        $this->assertStringContainsString('miIconLimit', $source,
            'the icon grid no longer tracks how many icons are currently shown');
    }

    /** Switching search terms must start back at page one, not carry over how far you'd paged. */
    public function test_a_new_search_resets_back_to_the_first_page(): void
    {
        $source = file_get_contents(
            __DIR__.'/../../../resources/views/admin/menus/index.blade.php'
        );

        $start = strpos($source, 'function renderIconGrid');
        $this->assertNotFalse($start, 'renderIconGrid has moved');

        $body = substr($source, $start, 700);
        $this->assertStringContainsString('miIconLimit = MI_ICON_PAGE', $body,
            'a changed search query no longer resets the paging limit — "load more" from a previous search would leak into a new one');
    }
}
