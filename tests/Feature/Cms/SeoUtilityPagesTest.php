<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\Breadcrumbs;
use FalconCms\Core\Tests\TestCase;
use FalconSeo\Support\SeoAudit;
use Illuminate\Support\Facades\DB;

/**
 * The pages no one should land on from a search (site search, empty archives, cart, checkout,
 * account) say noindex and stay out of the sitemap; a canonical keeps only the page number;
 * and every page carries its breadcrumb trail as a BreadcrumbList.
 */
class SeoUtilityPagesTest extends TestCase
{
    private function make(array $attrs): Post
    {
        return Post::forceCreate($attrs + ['type' => 'post', 'status' => 'published', 'content' => '<p>'.str_repeat('word ', 60).'</p>', 'lang_code' => 'en']);
    }

    private function robots(string $html): ?string
    {
        return preg_match('/<meta name="robots" content="([^"]+)">/', $html, $m) ? $m[1] : null;
    }

    private function graph(string $html): array
    {
        preg_match('#<script type="application/ld\+json">\s*(\{.*?"@graph".*?\})\s*</script>#s', $html, $m);

        return json_decode($m[1] ?? '{}', true)['@graph'] ?? [];
    }

    public function test_site_search_results_are_noindexed(): void
    {
        $this->make(['title' => 'Coffee guide', 'slug' => 'coffee-guide']);

        $this->assertStringStartsWith('noindex', (string) $this->robots($this->get('/search?s=coffee')->assertOk()->getContent()));
    }

    public function test_an_empty_category_is_noindexed_and_a_full_one_is_not(): void
    {
        $full = DB::table('categories')->insertGetId(['name' => 'Full', 'slug' => 'full', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('categories')->insertGetId(['name' => 'Empty', 'slug' => 'empty', 'created_at' => now(), 'updated_at' => now()]);
        $post = $this->make(['title' => 'In full', 'slug' => 'in-full']);
        $post->categories()->attach($full);

        $this->assertStringStartsWith('noindex', (string) $this->robots($this->get('/category/empty')->getContent()));
        $this->assertStringStartsWith('index', (string) $this->robots($this->get('/category/full')->assertOk()->getContent()));
    }

    public function test_cart_checkout_and_account_are_noindexed_and_left_out_of_the_sitemap_and_audit(): void
    {
        $ids = [];
        foreach (['cart' => 'shop_cart_page_id', 'checkout' => 'shop_checkout_page_id', 'account' => 'shop_account_page_id'] as $slug => $key) {
            $ids[$slug] = DB::table('posts')->where('type', 'page')->where('slug', $slug)->value('id')
                ?: $this->make(['title' => ucfirst($slug), 'slug' => $slug, 'type' => 'page'])->id;
            $this->setCmsOptions([$key => (string) $ids[$slug]]);
        }
        $this->make(['title' => 'About', 'slug' => 'about', 'type' => 'page']);

        $this->assertStringStartsWith('noindex', (string) $this->robots($this->get('/cart')->assertOk()->getContent()));

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString(url('/cart').'<', $sitemap);
        $this->assertStringContainsString(url('/about').'<', $sitemap);

        $audited = array_column(SeoAudit::posts(), 'id');
        $this->assertNotContains($ids['checkout'], $audited);
    }

    public function test_the_canonical_keeps_the_page_number_and_drops_everything_else(): void
    {
        $this->make(['title' => 'About', 'slug' => 'about', 'type' => 'page']);

        $html = $this->get('/about?utm_source=x&orderby=price')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/about').'">', $html);

        $html = $this->get('/about?page=2&orderby=price')->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/about').'?page=2">', $html);
    }

    public function test_a_post_carries_its_trail_as_a_breadcrumb_list(): void
    {
        $blog = $this->make(['title' => 'Blog', 'slug' => 'blog', 'type' => 'page']);
        $this->setCmsOptions(['blog_page_id' => (string) $blog->id]);
        $cat = DB::table('categories')->insertGetId(['name' => 'Recipes', 'slug' => 'recipes', 'created_at' => now(), 'updated_at' => now()]);
        $post = $this->make(['title' => 'Overnight oats', 'slug' => 'overnight-oats']);
        $post->categories()->attach($cat);

        $html = $this->get('/post/overnight-oats')->assertOk()->getContent();
        $list = collect($this->graph($html))->firstWhere('@type', 'BreadcrumbList');

        $this->assertSame(['Home', 'Blog', 'Recipes', 'Overnight oats'], array_column($list['itemListElement'], 'name'));
        $this->assertSame(url('/blog'), $list['itemListElement'][1]['item']);
        $this->assertSame(route('frontend.category', 'recipes'), $list['itemListElement'][2]['item']);
        // The visible title bar draws the same trail.
        $this->assertMatchesRegularExpression('#lazy-title-bar-breadcrumb.*?Blog.*?Recipes.*?Overnight oats#s', $html);
    }

    public function test_the_home_page_has_no_trail(): void
    {
        $home = $this->make(['title' => 'Home', 'slug' => 'home', 'type' => 'page']);
        $this->setCmsOptions(['home_page_id' => (string) $home->id]);

        $this->assertNull(collect($this->graph($this->get('/')->assertOk()->getContent()))->firstWhere('@type', 'BreadcrumbList'));
        $this->assertSame([], Breadcrumbs::trail($home));
    }
}
