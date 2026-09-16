<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Tag;
use FalconCms\Core\Tests\TestCase;

/**
 * get_falcon_term_link() builds the address a term's archive is actually served on.
 *
 * Themes used to write these by hand — `?category={{ $term->slug }}` or a hard-coded
 * `/category/`. Both go stale the moment the base is a setting, and the query-string version
 * was never an archive at all: it only worked because one template happened to read it. This
 * helper asks the router, so a theme written once follows whatever the site is configured for.
 */
class TermLinkTest extends TestCase
{
    public function test_a_category_link_uses_the_category_archive(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'link-test-cat'], ['name' => 'Link Test', 'lang_code' => 'en']);

        $this->assertSame(url('/category/link-test-cat'), get_falcon_term_link($cat));
        $this->assertSame(url('/category/link-test-cat'), get_falcon_term_link($cat, 'category'));
    }

    public function test_a_tag_link_uses_the_tag_archive(): void
    {
        $tag = Tag::firstOrCreate(['slug' => 'link-test-tag'], ['name' => 'Link Test', 'lang_code' => 'en']);

        $this->assertSame(url('/tag/link-test-tag'), get_falcon_term_link($tag, 'tag'));
    }

    public function test_a_plain_slug_works_as_well_as_a_term(): void
    {
        $this->assertSame(url('/tag/quick-meals'), get_falcon_term_link('quick-meals', 'tag'));
    }

    public function test_a_term_with_no_slug_gives_a_dead_link_rather_than_a_wrong_one(): void
    {
        $this->assertSame('#', get_falcon_term_link(''));
        $this->assertSame('#', get_falcon_term_link((object) ['name' => 'No slug here']));
    }

    public function test_an_unknown_taxonomy_falls_through_to_the_category_archive(): void
    {
        // ACPT taxonomies have no route of their own; the category archive resolves their
        // terms out of taxonomy_terms when no category matches.
        $this->assertSame(url('/category/hardback'), get_falcon_term_link('hardback', 'book-format'));
    }

    public function test_the_archive_answers_on_the_address_the_helper_hands_out(): void
    {
        Category::firstOrCreate(['slug' => 'link-test-live'], ['name' => 'Live', 'lang_code' => 'en']);

        $this->get(get_falcon_term_link('link-test-live'))->assertOk();
    }
}
