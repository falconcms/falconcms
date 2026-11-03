<?php

namespace FalconCms\Core\Tests\Feature\Seo;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The address the post screen shows: the SEO tab's search preview and the Permalink line.
 *
 * Posts live at /post/{slug}, but both printed /{slug} — on falconcms.com the preview read
 * "falconcms.com/falconcms-vs-filament", an address that only redirects.
 */
class SearchPreviewUrlTest extends TestCase
{
    public function test_a_posts_preview_and_permalink_show_its_real_address(): void
    {
        $post = Post::forceCreate(['title' => 'Hello there', 'slug' => 'hello-there', 'type' => 'post', 'status' => 'draft', 'content' => '', 'lang_code' => 'en']);
        $this->actingAs(User::forceCreate([
            'name' => 'Admin', 'email' => 'preview-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]));

        $html = $this->get('/admin/posts/'.$post->id.'/edit')->assertOk()->getContent();
        $permalink = get_falcon_permalink($post);
        $this->assertStringContainsString('/post/hello-there', $permalink);

        $this->assertMatchesRegularExpression('#id="preview-url"[^>]*>\s*'.preg_quote($permalink, '#').'\s*<#', $html);
        $this->assertMatchesRegularExpression('#id="permalink-full-link"[^>]*>'.preg_quote(substr($permalink, 0, -strlen('hello-there')), '#').'<span#', $html);
    }
}
