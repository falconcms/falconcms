<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * The slash before a fragment.
 *
 * /pricing#plans and /pricing/#plans reach the same place, so this is about the address
 * bar rather than the navigation — but the two spellings are different pages to a search
 * engine and to analytics, and a site that serves its pages with a trailing slash looks
 * inconsistent the moment an anchor is involved.
 *
 * Every menu link on the site now goes through this, which is why the cases below are
 * mostly about what it must NOT touch. A helper that mangles a mailto: or drops a query
 * string would break navigation everywhere at once.
 */
class AnchorUrlTest extends TestCase
{
    public function test_it_puts_the_slash_before_the_fragment(): void
    {
        $this->assertSame('/pricing/#plans', falcon_anchor_url('/pricing#plans'));
        $this->assertSame('https://falconcms.com/pricing/#plans', falcon_anchor_url('https://falconcms.com/pricing#plans'));
    }

    /** A URL that already has one is left exactly as it was. */
    public function test_a_url_that_already_has_the_slash_is_unchanged(): void
    {
        $this->assertSame('/pricing/#plans', falcon_anchor_url('/pricing/#plans'));
        $this->assertSame('/#plans', falcon_anchor_url('/#plans'));
    }

    /**
     * A bare fragment is resolved against the page it is rendered on.
     *
     * This is how a landing page's menu items are actually saved — #pricing, #features —
     * and they were already going to that page's own section, so the link behaves
     * identically and only reads better.
     */
    public function test_a_bare_fragment_is_resolved_against_the_current_page(): void
    {
        $this->assertSame('/toc-check/#price', falcon_anchor_url('#price', '/toc-check'));
        $this->assertSame('/toc-check/#price', falcon_anchor_url('#price', 'toc-check'));
        $this->assertSame('/#price', falcon_anchor_url('#price', '/'));
    }

    /** The slash goes at the end of the path, not after the query. */
    public function test_a_query_string_keeps_its_place(): void
    {
        $this->assertSame('/search/?q=cms#results', falcon_anchor_url('/search?q=cms#results'));
    }

    /** A host with no path of its own still gets one. */
    public function test_a_bare_host_gets_a_root_path(): void
    {
        $this->assertSame('https://falconcms.com/#plans', falcon_anchor_url('https://falconcms.com#plans'));
    }

    /**
     * Everything without a fragment is left alone — which is most links on most sites,
     * and where a helper like this does its damage if it is careless.
     */
    public function test_links_with_no_fragment_are_untouched(): void
    {
        foreach ([
            '/pricing',
            '/pricing/',
            'https://falconcms.com/docs',
            'mailto:hello@falconcms.com',
            'tel:+880123456789',
            '/search?q=cms',
            '',
        ] as $url) {
            $this->assertSame($url, falcon_anchor_url($url), "{$url} was rewritten and should not have been");
        }
    }

    /**
     * A bare # is not a link to anywhere; it is the placeholder a menu item carries when
     * it has no link at all, usually because it only exists to open a submenu. Turning it
     * into a link to the current page would make it navigate.
     */
    public function test_a_bare_hash_is_left_as_a_placeholder(): void
    {
        $this->assertSame('#', falcon_anchor_url('#', '/toc-check'));
    }

    /** Null and whitespace are the shapes a missing menu URL actually arrives in. */
    public function test_nothing_in_nothing_out(): void
    {
        $this->assertSame('', falcon_anchor_url(null));
        $this->assertSame('', falcon_anchor_url('   '));
    }

    /**
     * Every menu renderer has to use it, or the site is inconsistent with itself — the
     * header saying /pricing/#plans while the footer says /pricing#plans.
     */
    public function test_every_menu_renderer_uses_it(): void
    {
        $root = __DIR__.'/../../../resources/views/';

        foreach ([
            'frontend/builder/elements/menu.blade.php',
            'frontend/widgets/nav_menu.blade.php',
            'themes/falcon-theme/partials/header.blade.php',
            'themes/falcon-theme/partials/footer.blade.php',
        ] as $file) {
            $source = (string) file_get_contents($root.$file);

            $this->assertStringContainsString('falcon_anchor_url', $source,
                "{$file} renders menu links without normalising their anchors");

            $this->assertDoesNotMatchRegularExpression('/href="\{\{ \$(?:item|child)->url \}\}"/', $source,
                "{$file} still has a raw menu URL going straight into an href");
        }
    }
}
