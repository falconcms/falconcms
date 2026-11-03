<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Holes an outside code audit (2026-11-03) reported and that turned out to be real, each
 * held in place by the request that used to get through.
 */
class AuditFixesTest extends TestCase
{
    use MakesShopFixtures;

    private function userWithRole(string $slug): User
    {
        return $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', $slug)->value('id')]);
    }

    /** A user whose only role holds exactly these permissions. */
    private function userWith(array $slugs): User
    {
        $role = Role::create(['name' => 'Audit role', 'slug' => 'audit-'.uniqid()]);
        $role->permissions()->sync(array_map(
            fn ($slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id,
            $slugs
        ));

        return $this->makeUser(['role_id' => $role->id]);
    }

    public function test_searching_categories_keeps_the_parent_dropdown(): void
    {
        Category::create(['name' => 'Parent topic', 'slug' => 'parent-topic', 'lang_code' => 'en']);

        $response = $this->actingAs($this->userWithRole('administrator'))->get('/admin/categories?s=Parent');

        $response->assertOk();
        $response->assertSee('Parent topic');
    }

    public function test_only_a_super_admin_changes_the_super_admin_role(): void
    {
        $super = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);

        $this->actingAs($this->userWithRole('administrator'))->put("/admin/roles/{$super->id}", [
            'name' => 'Hijacked', 'slug' => 'super-admin', 'description' => 'changed',
        ])->assertForbidden();

        $this->assertSame('Super Admin', $super->fresh()->name);
    }

    /**
     * No menu owns the builder's addresses, so AdminMiddleware lets only administrators in
     * today. A plugin may open them (falcon_admin_path_access); the controller then has to
     * hold each save to the type it writes, as the classic editor does.
     */
    public function test_the_builder_saves_only_types_the_user_may_write(): void
    {
        (new MenuSeeder)->run();
        add_falcon_filter('falcon_admin_path_access', fn ($decided, $path) => str_starts_with($path, 'admin/falcon-builder/') ? true : $decided, 10);
        $pagesOnly = $this->userWith(['manage_pages', 'access_all_pages_pages', 'access_add_new_pages']);
        $page = Post::create(['user_id' => $pagesOnly->id, 'title' => 'Mine', 'slug' => 'mine-'.uniqid(), 'type' => 'page', 'status' => 'draft', 'lang_code' => 'en', 'content' => '[]']);
        $post = Post::create(['user_id' => $pagesOnly->id, 'title' => 'Not mine', 'slug' => 'not-mine-'.uniqid(), 'type' => 'post', 'status' => 'published', 'lang_code' => 'en', 'content' => 'original']);

        $layout = ['layout' => [['id' => 'c', 'columns' => []]]];
        $this->actingAs($pagesOnly)->postJson("/admin/falcon-builder/{$page->id}/save", $layout)->assertOk();
        $this->actingAs($pagesOnly)->postJson("/admin/falcon-builder/{$post->id}/save", $layout)->assertForbidden();
        $this->actingAs($pagesOnly)->postJson("/admin/falcon-builder/{$post->id}/autosave", $layout)->assertForbidden();

        $this->assertSame('original', $post->fresh()->content);
    }

    public function test_a_theme_slug_cannot_climb_out_of_the_themes_folder(): void
    {
        $admin = $this->userWithRole('administrator');
        $probe = resource_path('views/audit-probe-'.uniqid());
        File::ensureDirectoryExists($probe);

        try {
            $this->actingAs($admin)->delete('/admin/themes/..');
            $this->actingAs($admin)->delete('/admin/themes/%2E%2E');
            $this->assertDirectoryExists($probe, 'deleting the theme ".." removed resources/views');
        } finally {
            File::deleteDirectory($probe);
        }
    }

    public function test_html_cannot_be_uploaded_to_the_media_library(): void
    {
        $this->assertContains('html', falcon_blocked_upload_extensions());
        $this->assertContains('htm', falcon_blocked_upload_extensions());

        $this->actingAs($this->userWithRole('administrator'))
            ->post('/admin/media', ['file' => UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>')], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_only_public_http_addresses_may_be_fetched(): void
    {
        foreach (['file:///etc/passwd', 'gopher://example.com/', 'http://127.0.0.1/', 'http://169.254.169.254/latest/meta-data/', 'http://10.0.0.5/x.zip', 'http://[::1]/', 'http://localhost/'] as $url) {
            $this->assertFalse(falcon_is_public_http_url($url), $url);
        }
        $this->assertTrue(falcon_is_public_http_url('https://93.184.216.34/plugin.zip'));
    }

    public function test_the_page_cache_does_not_keep_a_copy_per_query_string(): void
    {
        $this->setCmsOptions(['performance_static_caching' => '1']);

        $this->get('/?x=1')->assertOk()->assertHeaderMissing('X-Lazy-Cache');
        $this->assertFalse(Cache::has('page_cache_'.md5(url('/?x=1'))), 'an arbitrary query string kept its own copy');

        // Tracking parameters share the page's own copy.
        $this->get('/?utm_source=a')->assertOk()->assertHeader('X-Lazy-Cache', 'MISS');
        $this->get('/?utm_source=b&gclid=1')->assertOk()->assertHeader('X-Lazy-Cache', 'HIT');
        $this->assertTrue(Cache::has('page_cache_'.md5(url('/'))));
    }
}
