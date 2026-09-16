<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The first segment of a taxonomy archive is a setting.
 *
 * /category/food and /tag/quick-meals were written into the route file, so a site that wanted
 * /post-category/food had nowhere to say so and no way to get it short of editing the package.
 * The bases are settings now; these pin what a base is allowed to be, because these routes are
 * registered at boot and a value the sanitiser let through would take the whole front end down
 * rather than one page.
 */
class TaxonomyBaseTest extends TestCase
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
        DB::table('cms_settings')->where('key', 'login_url')->update(['value' => 'falcon-admin']);
        forget_cms_options_cache();
        parent::tearDown();
    }

    public function test_the_defaults_are_what_the_cms_always_shipped(): void
    {
        $this->assertSame('category', falcon_taxonomy_base('category'));
        $this->assertSame('tag', falcon_taxonomy_base('tag'));
        $this->assertSame('product-category', falcon_taxonomy_base('product_category'));
        $this->assertSame('product-tag', falcon_taxonomy_base('product_tag'));
    }

    public function test_a_base_can_be_changed(): void
    {
        $this->setBase('category', 'post-category');

        $this->assertSame('post-category', falcon_taxonomy_base('category'));
    }

    public function test_a_base_is_tidied_rather_than_taken_literally(): void
    {
        $this->setBase('category', '/Topics/');

        $this->assertSame('topics', falcon_taxonomy_base('category'));
    }

    public function test_an_empty_base_falls_back_instead_of_claiming_every_url(): void
    {
        $this->setBase('category', '   ');

        $this->assertSame('category', falcon_taxonomy_base('category'));
    }

    public function test_a_base_with_a_slash_in_it_falls_back(): void
    {
        // Two segments would need a route pattern these archives do not have.
        $this->setBase('category', 'blog/topics');

        $this->assertSame('category', falcon_taxonomy_base('category'));
    }

    public function test_a_base_with_characters_a_url_cannot_carry_falls_back(): void
    {
        $this->setBase('tag', 'ta gs?');

        $this->assertSame('tag', falcon_taxonomy_base('tag'));
    }

    #[DataProvider('reservedPaths')]
    public function test_a_reserved_path_cannot_be_taken_over(string $reserved): void
    {
        $this->setBase('category', $reserved);

        $this->assertSame('category', falcon_taxonomy_base('category'));
    }

    public static function reservedPaths(): array
    {
        return [
            ['admin'],
            ['falcon-admin'],
            ['api'],
            ['search'],
            ['author'],
            ['storage'],
        ];
    }

    public function test_the_sites_own_login_address_is_reserved_too(): void
    {
        // The login slug is a setting, so the reserved list cannot be a fixed list.
        DB::table('cms_settings')->updateOrInsert(['key' => 'login_url'], ['value' => 'way-in']);
        forget_cms_options_cache();

        $this->setBase('category', 'way-in');

        $this->assertSame('category', falcon_taxonomy_base('category'));
    }

    public function test_two_taxonomies_cannot_share_one_base(): void
    {
        $this->setBase('tag', 'topics');
        $this->setBase('category', 'topics');

        // Whichever route registered first would have answered for both, and the other
        // archive would have served the wrong terms with nothing to say so. Neither takes it:
        // both fall back, both archives keep working, and the admin can see what happened.
        $this->assertSame('tag', falcon_taxonomy_base('tag'));
        $this->assertSame('category', falcon_taxonomy_base('category'));
    }

    public function test_a_taxonomy_may_keep_its_own_default_even_if_another_holds_it(): void
    {
        // Nonsense in practice, but it must not lock a taxonomy out of its own default.
        $this->setBase('tag', 'category');

        $this->assertSame('category', falcon_taxonomy_base('category'));
    }
}
