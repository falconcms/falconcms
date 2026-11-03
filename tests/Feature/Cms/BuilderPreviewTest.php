<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * admin/falcon-builder/{id}/preview — a draft as visitors will see it.
 *
 * It printed the builder JSON ([{"id":…,"settings":…) inside an empty HTML page, so the only
 * way to look at a draft on falconcms.com was to publish it.
 */
class BuilderPreviewTest extends TestCase
{
    private function user(string $role): User
    {
        return User::forceCreate([
            'name' => ucfirst($role), 'email' => $role.'-'.uniqid().'@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', $role)->value('id'),
        ]);
    }

    private function draft(User $owner): Post
    {
        $layout = [[
            'id' => 'r1', 'type' => 'container', 'settings' => [],
            'columns' => [[
                'id' => 'c1', 'basis' => '100%', 'settings' => [],
                'elements' => [['id' => 'e1', 'type' => 'text_block', 'settings' => ['content' => '<p>Unpublished words for the preview.</p>']]],
            ]],
        ]];

        return Post::create([
            'title' => 'A draft page', 'slug' => 'a-draft-page', 'type' => 'page', 'status' => 'draft',
            'editor_type' => 'builder', 'content' => json_encode($layout), 'user_id' => $owner->id, 'lang_code' => 'en',
        ]);
    }

    public function test_an_editor_sees_the_draft_rendered_by_the_theme(): void
    {
        $admin = $this->user('administrator');
        $post = $this->draft($admin);

        $response = $this->actingAs($admin)->get('/admin/falcon-builder/'.$post->id.'/preview');

        $response->assertOk();
        $response->assertSee('Unpublished words for the preview.');
        $response->assertDontSee('"settings"', false);
        $this->assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('draft', $post->fresh()->status, 'previewing must not publish');
    }

    public function test_visitors_cannot_preview_a_draft(): void
    {
        $post = $this->draft($this->user('administrator'));

        $response = $this->get('/admin/falcon-builder/'.$post->id.'/preview');

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Unpublished words for the preview.', (string) $response->getContent());
    }
}
