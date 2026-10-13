<?php

namespace FalconCms\Core\Tests\Feature\Seo;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/** SEO's screens are granted in Users → Roles like any menu, one permission per screen. */
class SeoPermissionsTest extends TestCase
{
    use MakesShopFixtures;

    /** A user whose only role holds exactly these permissions. */
    private function userWith(array $permissions)
    {
        // Ids 1 and 6 are the administrators' (AdminMiddleware lets them through): stay clear of them.
        static $next = 900;
        $roleId = DB::table('roles')->insertGetId(['id' => ++$next, 'name' => 'Tester '.$next, 'slug' => 'tester-'.$next, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($permissions as $slug) {
            $id = DB::table('permissions')->where('slug', $slug)->value('id')
                ?: DB::table('permissions')->insertGetId(['name' => $slug, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $id]);
        }

        return $this->makeUser(['role_id' => $roleId]);
    }

    public function test_the_roles_screen_offers_each_seo_screen(): void
    {
        $this->actingAs($this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]));
        $editor = (int) DB::table('roles')->where('slug', 'editor')->value('id');

        $html = $this->get('/admin/roles/'.$editor.'/edit')->assertOk()->getContent();

        foreach (['access_seo', 'access_seo_settings', 'access_seo_site_analysis', 'access_seo_audit', 'access_seo_bulk', 'access_seo_redirects'] as $slug) {
            $this->assertStringContainsString('value="'.$slug.'"', $html, $slug.' can be granted');
        }
    }

    public function test_a_role_reaches_only_the_seo_screens_it_was_given(): void
    {
        $this->actingAs($this->userWith(['access_dashboard', 'access_seo', 'access_seo_audit', 'access_seo_redirects']));

        $this->get('/admin/seo/audit')->assertOk();
        $this->get('/admin/seo/redirects')->assertOk();
        $this->get('/admin/seo/settings')->assertForbidden();
        $this->get('/admin/seo/bulk')->assertForbidden();
        $this->get('/admin/seo/site-analysis')->assertForbidden();
        $this->post('/admin/seo/settings', ['seo_separator' => '|'])->assertForbidden();
    }

    public function test_the_editors_seo_tabs_answer_whoever_can_write_posts(): void
    {
        $post = Post::forceCreate(['title' => 'Draft', 'slug' => 'draft', 'type' => 'post', 'status' => 'draft', 'content' => '<p>Tea.</p>', 'lang_code' => 'en']);
        $payload = ['post_id' => $post->id, 'mode' => 'editor', 'keyword' => 'tea', 'check_links' => false];

        $this->actingAs($this->userWith(['access_dashboard', 'manage_posts']));
        $this->postJson('/admin/seo/page-analysis', $payload)->assertOk();
        $this->postJson('/admin/seo/keyword-suggestions', ['post_id' => $post->id, 'title' => 'Draft'])->assertOk();

        $this->actingAs($this->userWith(['access_dashboard']));
        $this->postJson('/admin/seo/page-analysis', $payload)->assertForbidden();
    }

    public function test_upgrading_carries_each_old_settings_permission_to_its_seo_one(): void
    {
        $user = $this->userWith(['access_dashboard', 'access_redirects_settings', 'access_seo_audit_settings']);
        $roleId = (int) $user->role_id;
        DB::table('role_permission')->where('role_id', $roleId)->whereIn('permission_id', DB::table('permissions')
            ->whereIn('slug', ['access_seo', 'access_seo_settings', 'access_seo_site_analysis', 'access_seo_audit', 'access_seo_bulk', 'access_seo_redirects'])->pluck('id'))->delete();

        (require dirname(__DIR__, 3).'/database/migrations/2026_10_14_000003_add_seo_permissions.php')->up();

        $slugs = DB::table('role_permission')->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_id', $roleId)->pluck('slug')->all();
        foreach (['access_seo', 'access_seo_redirects', 'access_seo_audit'] as $slug) {
            $this->assertContains($slug, $slugs);
        }
        $this->assertNotContains('access_seo_settings', $slugs, 'nothing it did not have before');
    }
}
