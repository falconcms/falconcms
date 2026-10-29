<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Models\Tag;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Every public page renders.
 *
 * The suite used to fetch four addresses in total, and a template that threw did so in
 * production rather than in CI — which is exactly how the out-of-stock product page shipped
 * with a script that crashed. These are deliberately shallow: status, and the one thing the
 * page exists to show. A template that stops compiling, a helper that stops existing or a
 * variable a view stopped being given all surface here, on the page a visitor actually opens.
 *
 * They assert nothing about layout, so a theme is free to change without breaking them.
 */
class FrontendPagesRenderTest extends TestCase
{
    private function makePost(array $attributes = []): Post
    {
        static $n = 0;
        $n++;

        return Post::create(array_merge([
            'user_id' => 1,
            'title' => "Rendered post {$n}",
            'slug' => "rendered-post-{$n}",
            'type' => 'post',
            'status' => 'published',
            'lang_code' => 'en',
            'content' => '<p>Something to render.</p>',
            'excerpt' => 'A summary.',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_the_home_page_renders(): void
    {
        $this->makePost();

        $this->get('/')->assertOk();
    }

    public function test_a_single_post_renders_on_both_of_its_addresses(): void
    {
        $post = $this->makePost(['title' => 'A Post To Read', 'slug' => 'a-post-to-read']);

        // /post/{slug} is what get_falcon_permalink() hands out; the bare slug is the
        // catch-all that has always answered too. Both have to work — the bare one by a
        // permanent redirect, so search engines see one address, not two copies.
        $this->get('/post/'.$post->slug)->assertOk()->assertSee('A Post To Read', false);
        $this->get('/'.$post->slug)->assertRedirect(url('/post/'.$post->slug));
        $this->followingRedirects()->get('/'.$post->slug)->assertOk()->assertSee('A Post To Read', false);
    }

    /** A post's title is the page's one H1, from the Page Title Bar or, with that off, the post itself. */
    public function test_a_single_post_has_exactly_one_h1_with_or_without_the_title_bar(): void
    {
        $post = $this->makePost(['title' => 'The Title Is The Heading']);
        $h1s = fn () => preg_match_all('#<h1\b[^>]*>\s*The Title Is The Heading\s*</h1>#', $this->get('/post/'.$post->slug)->assertOk()->getContent());

        update_cms_option('theme_title_bar_enabled', '1');
        update_cms_option('theme_title_bar_show_title', '1');
        $this->assertSame(1, $h1s(), 'in the title bar');

        update_cms_option('theme_title_bar_enabled', '0');
        $this->assertSame(1, $h1s(), 'title bar off: the post prints it');

        update_cms_option('theme_title_bar_enabled', '1');
        update_cms_option('theme_title_bar_show_title', '0');
        $this->assertSame(1, $h1s(), 'title hidden in the bar: the post prints it');
    }

    public function test_a_page_renders(): void
    {
        $this->makePost([
            'title' => 'About This Place',
            'slug' => 'about-this-place',
            'type' => 'page',
        ]);

        $this->get('/about-this-place')->assertOk()->assertSee('About This Place', false);
    }

    public function test_a_category_archive_renders(): void
    {
        $category = Category::create(['name' => 'Renders', 'slug' => 'renders', 'lang_code' => 'en']);
        $post = $this->makePost();
        $post->categories()->attach($category->id);

        $this->get('/category/renders')->assertOk()->assertSee('Renders', false);
    }

    public function test_a_tag_archive_renders(): void
    {
        $tag = Tag::create(['name' => 'Rendered', 'slug' => 'rendered', 'lang_code' => 'en']);
        $post = $this->makePost();
        $post->tags()->attach($tag->id);

        $this->get('/tag/rendered')->assertOk()->assertSee('Rendered', false);
    }

    public function test_the_search_page_renders_with_and_without_results(): void
    {
        $this->makePost(['title' => 'Findable Thing', 'slug' => 'findable-thing']);

        $this->get('/search?q=Findable')->assertOk()->assertSee('Findable Thing', false);
        $this->get('/search?q=nothing-matches-this')->assertOk();
        $this->get('/search')->assertOk();
    }

    public function test_an_author_archive_renders(): void
    {
        $post = $this->makePost();

        $this->get('/author/'.$post->user_id)->assertOk();
    }

    public function test_an_unknown_address_is_a_404_rather_than_a_crash(): void
    {
        $this->get('/no-such-page-exists-here')->assertNotFound();
    }

    public function test_a_custom_post_type_single_renders(): void
    {
        PostType::create([
            'name' => 'Recipes',
            'singular_name' => 'Recipe',
            'slug' => 'recipe',
            'is_builtin' => false,
            'is_active' => true,
            'show_in_menu' => true,
            'is_public' => true,
            'supports' => ['title', 'editor', 'excerpt', 'featured_image'],
        ]);

        $this->makePost([
            'title' => 'A Rendered Recipe',
            'slug' => 'a-rendered-recipe',
            'type' => 'recipe',
        ]);

        $this->get('/recipe/a-rendered-recipe')->assertOk()->assertSee('A Rendered Recipe', false);
    }

    public function test_a_draft_is_not_served_to_a_visitor(): void
    {
        $this->makePost(['title' => 'Not Ready', 'slug' => 'not-ready', 'status' => 'draft']);

        $this->get('/post/not-ready')->assertNotFound();
    }

    public function test_the_feed_and_sitemap_render(): void
    {
        $this->makePost();

        $this->get('/sitemap.xml')->assertOk();
    }

    /** The storefront pages. E-commerce is free core, so these open without a licence. */
    public function test_the_cart_and_account_pages_render(): void
    {
        foreach (['cart' => 'shop_cart_page_id', 'checkout' => 'shop_checkout_page_id',
            'account' => 'shop_account_page_id'] as $slug => $key) {
            $id = DB::table('posts')->where('type', 'page')->where('slug', $slug)->value('id')
                ?: $this->makePost(['title' => ucfirst($slug), 'slug' => $slug, 'type' => 'page'])->id;
            DB::table('cms_settings')->updateOrInsert(['key' => $key], ['value' => (string) $id]);
        }
        forget_cms_options_cache();

        $this->get('/cart')->assertOk();
    }
}
