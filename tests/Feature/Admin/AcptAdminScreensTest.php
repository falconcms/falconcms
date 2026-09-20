<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Two admin screens that described a custom post type wrongly.
 *
 * The field-group location rule listed post and page twice — once from a hard-coded pair in
 * the view and once from the post_types table — so the same value appeared as "Post" and as
 * "Posts", and on the edit screen both carried `selected`.
 *
 * The SEO search preview printed url($post->slug), which is not where a post lives. A recipe
 * served at /recipe/overnight-oats was previewed as /overnight-oats, and a translation lost
 * its language prefix — on the one screen whose whole job is to show what a search result
 * will look like.
 */
class AcptAdminScreensTest extends TestCase
{
    private function administrator(): User
    {
        return User::forceCreate([
            'name' => 'Admin',
            'email' => 'acpt-screens@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function recipeType(): PostType
    {
        return PostType::create([
            'name' => 'Recipes',
            'singular_name' => 'Recipe',
            'slug' => 'recipe',
            'is_builtin' => false,
            'is_active' => true,
            'show_in_menu' => true,
            'is_public' => true,
            'supports' => ['title', 'editor', 'excerpt', 'featured_image'],
        ]);
    }

    /** The values of every option in the location-rule select, in order. */
    private function ruleOptionValues(string $html): array
    {
        if (!preg_match('/<select[^>]*name="rules\[post_type\]"[^>]*>(.*?)<\/select>/s', $html, $select)) {
            $this->fail('the location rule select is not on the page');
        }

        preg_match_all('/<option[^>]*value="([^"]*)"/', $select[1], $options);

        return array_values(array_filter($options[1], static fn ($v) => $v !== ''));
    }

    public function test_the_location_rule_lists_each_post_type_once_when_adding_a_group(): void
    {
        $this->recipeType();

        $html = $this->actingAs($this->administrator())
            ->get('/admin/acpt/fields/create')
            ->assertOk()
            ->getContent();

        $values = $this->ruleOptionValues($html);

        $this->assertSame(array_unique($values), $values, 'a post type is offered twice: '.implode(', ', $values));
        $this->assertContains('recipe', $values);
        $this->assertContains('post', $values);
    }

    public function test_the_location_rule_lists_each_post_type_once_when_editing_a_group(): void
    {
        $this->recipeType();

        $groupId = DB::table('custom_field_groups')->insertGetId([
            'title' => 'Recipe Details',
            'rules' => json_encode(['post_type' => 'recipe']),
            'is_active' => 1,
            'order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $html = $this->actingAs($this->administrator())
            ->get('/admin/acpt/fields/'.$groupId.'/edit')
            ->assertOk()
            ->getContent();

        $values = $this->ruleOptionValues($html);
        $this->assertSame(array_unique($values), $values, 'a post type is offered twice: '.implode(', ', $values));

        // One selected option, and it is the one the group was saved with. With the value
        // listed twice, both copies carried `selected`.
        preg_match_all('/<option[^>]*value="([^"]*)"[^>]*\sselected/', $html, $selected);
        $this->assertSame(['recipe'], $selected[1]);
    }

    public function test_the_search_preview_shows_where_a_custom_post_type_actually_lives(): void
    {
        $this->recipeType();

        $post = Post::create([
            'user_id' => 1,
            'title' => 'Overnight Oats',
            'slug' => 'overnight-oats',
            'type' => 'recipe',
            'status' => 'published',
            'lang_code' => 'en',
            'content' => '',
        ]);

        $html = $this->actingAs($this->administrator())
            ->get('/admin/posts/'.$post->id.'/edit')
            ->assertOk()
            ->getContent();

        preg_match('/id="preview-url"[^>]*>(.*?)</s', $html, $preview);
        $shown = trim($preview[1] ?? '');

        $this->assertSame(url('/recipe/overnight-oats'), $shown);
    }

    public function test_the_search_preview_agrees_with_the_permalink_for_an_ordinary_post(): void
    {
        $post = Post::create([
            'user_id' => 1,
            'title' => 'An Ordinary Post',
            'slug' => 'an-ordinary-post',
            'type' => 'post',
            'status' => 'published',
            'lang_code' => 'en',
            'content' => '',
        ]);

        $html = $this->actingAs($this->administrator())
            ->get('/admin/posts/'.$post->id.'/edit')
            ->assertOk()
            ->getContent();

        preg_match('/id="preview-url"[^>]*>(.*?)</s', $html, $preview);
        $shown = trim($preview[1] ?? '');

        // Whatever the Permalink line above it shows, the preview shows too. They were built
        // two different ways and disagreed.
        preg_match('/id="permalink-full-link"[^>]*>(.*?)<\/a>/s', $html, $permalink);
        $link = trim(strip_tags($permalink[1] ?? ''));

        $this->assertSame(rtrim($link, '/'), rtrim($shown, '/'));
    }
}
