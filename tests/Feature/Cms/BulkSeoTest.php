<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Settings → Bulk SEO: every page's search title, description, focus keyword and noindex on one
 * screen, filtered to what is missing. Only the rows changed are saved, and an empty field
 * goes back to the default rather than storing an empty string.
 */
class BulkSeoTest extends TestCase
{
    private function admin(): User
    {
        return User::forceCreate(['name' => 'Admin', 'email' => 'bulk@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    private function make(string $title, array $seo = [], string $type = 'post'): Post
    {
        return Post::forceCreate(['title' => $title, 'slug' => str($title)->slug(), 'type' => $type, 'status' => 'published',
            'content' => '<p>x</p>', 'lang_code' => 'en', 'seo_meta' => $seo]);
    }

    public function test_the_screen_lists_pages_and_filters_to_what_is_missing(): void
    {
        $this->make('Complete post', ['title' => 'Complete', 'description' => 'Has both']);
        $this->make('No description', ['title' => 'Only a title']);
        $this->make('About page', [], 'page');
        DB::table('posts')->insert(['title' => 'Header layout', 'slug' => 'hdr', 'type' => 'falcon_header', 'status' => 'published', 'lang_code' => 'en']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings.seo-bulk'))->assertOk()
            ->assertSee('Complete post')->assertSee('About page')->assertDontSee('Header layout');

        $this->actingAs($admin)->get(route('admin.settings.seo-bulk', ['missing' => 'description']))->assertOk()
            ->assertSee('No description')->assertSee('About page')->assertDontSee('Complete post');

        $this->actingAs($admin)->get(route('admin.settings.seo-bulk', ['type' => 'page']))->assertOk()
            ->assertSee('About page')->assertDontSee('No description');
    }

    public function test_saving_writes_the_changed_rows_and_keeps_the_rest_of_seo_meta(): void
    {
        $post = $this->make('Pricing', ['title' => 'Old title', 'og_title' => 'Kept', 'noindex' => '1']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.seo-bulk.update'), ['rows' => [
            $post->id => ['seo_title' => '', 'description' => 'Plans & prices', 'focus_keyword' => 'cms pricing', 'noindex' => '0'],
        ]])->assertRedirect()->assertSessionHas('success', '1 page saved.');

        $seo = $post->fresh()->seo_meta;
        $this->assertArrayNotHasKey('title', $seo, 'empty goes back to the page title');
        $this->assertSame('Plans & prices', $seo['description']);
        $this->assertSame('cms pricing', $seo['focus_keyword']);
        $this->assertArrayNotHasKey('noindex', $seo);
        $this->assertSame('Kept', $seo['og_title'], 'fields this screen does not show are untouched');
    }

    public function test_it_needs_the_settings_permission(): void
    {
        $subscriber = User::forceCreate(['name' => 'Sub', 'email' => 'sub@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'subscriber')->value('id')]);

        $this->actingAs($subscriber)->get(route('admin.settings.seo-bulk'))->assertStatus(403);
    }
}
