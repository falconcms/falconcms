<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Support\NotFoundLog;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The 404 log: missing pages are counted with where they were linked from, listed on the
 * Redirects screen and redirected in one click. Missing files are not pages and are left out.
 */
class NotFoundLogTest extends TestCase
{
    private function admin(): User
    {
        return User::where('email', 'admin@example.test')->first() ?? User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    public function test_a_missing_page_is_counted_with_its_referrer(): void
    {
        $this->get('/no-such-page', ['referer' => 'https://blog.example/post'])->assertNotFound();
        $this->get('/no-such-page')->assertNotFound();

        $row = DB::table('cms_not_found_log')->first();
        $this->assertSame('/no-such-page', $row->path);
        $this->assertSame(2, (int) $row->hits);
        $this->assertSame('https://blog.example/post', $row->referrer);
    }

    public function test_missing_files_and_admin_urls_are_not_logged(): void
    {
        $this->get('/missing/style.css');
        $this->get('/missing/photo.webp');

        $this->assertSame(0, DB::table('cms_not_found_log')->count());
    }

    public function test_it_can_be_turned_off(): void
    {
        $this->setCmsOptions(['seo_404_log' => '0']);
        $this->get('/no-such-page');

        $this->assertSame(0, DB::table('cms_not_found_log')->count());
    }

    public function test_the_redirects_screen_lists_404s_and_redirecting_one_clears_it(): void
    {
        NotFoundLog::record('/old-pricing', 'https://example.com/');
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/seo/redirects')->assertOk()->assertSee('404 errors')->assertSee('/old-pricing');
        $this->actingAs($admin)->get('/admin/seo/redirects?from=/old-pricing')->assertSee('value="/old-pricing"', false);

        $this->actingAs($admin)->post('/admin/seo/redirects', ['old_url' => '/old-pricing', 'new_url' => '/pricing', 'status_code' => 301])->assertRedirect();

        $this->assertSame(0, DB::table('cms_not_found_log')->count());
        $this->get('/old-pricing')->assertRedirect('/pricing');
    }

    public function test_entries_can_be_dismissed(): void
    {
        NotFoundLog::record('/a');
        NotFoundLog::record('/b');

        $this->actingAs($this->admin())->post('/admin/seo/redirects/not-found/forget', ['path' => '/a']);
        $this->assertSame(['/b'], DB::table('cms_not_found_log')->pluck('path')->all());

        $this->actingAs($this->admin())->post('/admin/seo/redirects/not-found/forget', ['all' => 1]);
        $this->assertSame(0, DB::table('cms_not_found_log')->count());
    }
}
