<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * falcon_term_archive_url() follows the configured archive base.
 *
 * It wrote "/category/" and "/product-category/" into the string itself, so on a site that had
 * changed a base — the whole point of the base being a setting — every term link pointed at the
 * old address. The link still arrived, because a changed base leaves a 301 behind, but it
 * arrived the long way round and never matched the archive it landed on. Custom (ACPT)
 * taxonomies feel it hardest: they have no route of their own and are served by the category
 * archive, so every chip on a custom post type went through the redirect.
 *
 * Sibling of [[TermLinkTest]]: get_falcon_term_link() asks the router and was already right,
 * and these two are expected to agree on every taxonomy.
 */
class TermArchiveUrlTest extends TestCase
{
    private function setBase(string $taxonomy, string $value): void
    {
        DB::table('cms_settings')->updateOrInsert(
            ['key' => $taxonomy.'_base'],
            ['value' => $value]
        );
        forget_cms_options_cache();
        cache()->flush();
    }

    protected function tearDown(): void
    {
        DB::table('cms_settings')->whereIn('key', [
            'category_base', 'tag_base', 'product_category_base', 'product_tag_base',
        ])->delete();
        forget_cms_options_cache();
        parent::tearDown();
    }

    public function test_the_defaults_are_what_the_cms_always_shipped(): void
    {
        $this->assertSame(url('/category/food'), falcon_term_archive_url('food', 'category'));
        $this->assertSame(url('/tag/quick'), falcon_term_archive_url('quick', 'tag'));
        $this->assertSame(
            url('/product-category/mugs'),
            falcon_term_archive_url('mugs', 'product_category', 'product')
        );
    }

    public function test_a_renamed_category_base_moves_the_link(): void
    {
        $this->setBase('category', 'post-category');

        $this->assertSame(url('/post-category/food'), falcon_term_archive_url('food', 'category'));
    }

    public function test_a_custom_taxonomy_follows_the_category_base_it_is_served_by(): void
    {
        // A recipe's "cuisine" has no archive route of its own — the category archive resolves
        // it out of taxonomy_terms — so it has to move when that base moves.
        $this->setBase('category', 'post-category');

        $this->assertSame(
            url('/post-category/japanese'),
            falcon_term_archive_url('japanese', 'cuisine', 'recipe')
        );
    }

    public function test_a_custom_taxonomy_on_a_product_follows_the_product_base(): void
    {
        $this->setBase('product_category', 'range');

        $this->assertSame(
            url('/range/stoneware'),
            falcon_term_archive_url('stoneware', 'material', 'product')
        );
    }

    public function test_it_agrees_with_the_link_helper(): void
    {
        // Under whatever bases this process booted with. The two read the base from different
        // places — the link helper asks the router, which bound it at boot, and this one reads
        // the setting — so a base changed mid-process is the one case they cannot be compared
        // on. That difference lasts until the next request rebuilds the routes.
        foreach ([['category', 'food'], ['tag', 'quick'], ['cuisine', 'japanese']] as [$taxonomy, $slug]) {
            $this->assertSame(
                get_falcon_term_link($slug, $taxonomy),
                falcon_term_archive_url($slug, $taxonomy),
                "the two helpers disagree on $taxonomy"
            );
        }
    }

    public function test_a_term_object_works_as_well_as_a_slug(): void
    {
        $this->assertSame(
            url('/category/vegan'),
            falcon_term_archive_url((object) ['slug' => 'vegan', 'name' => 'Vegan'], 'diet', 'recipe')
        );
    }

    public function test_a_term_with_no_slug_gives_nothing_rather_than_a_wrong_address(): void
    {
        $this->assertSame('', falcon_term_archive_url('', 'category'));
        $this->assertSame('', falcon_term_archive_url((object) ['name' => 'No slug'], 'category'));
    }
}
