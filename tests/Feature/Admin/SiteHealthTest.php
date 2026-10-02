<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Support\SiteHealth;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Settings → Site Health: a read-only report on the server, database, configuration,
 * security of the setup and of the site's own code, and the error log.
 */
class SiteHealthTest extends TestCase
{
    private function user(string $role = 'administrator'): User
    {
        return User::forceCreate([
            'name' => 'U', 'email' => $role.'@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', $role)->value('id'),
        ]);
    }

    public function test_the_page_renders_for_an_administrator(): void
    {
        $this->actingAs($this->user())->get('/admin/settings/site-health')
            ->assertOk()
            ->assertSee('Site Health', false)
            ->assertSee('Copy site info', false)
            ->assertSee(PHP_VERSION, false);
    }

    public function test_it_is_closed_to_users_who_cannot_manage_settings(): void
    {
        $subscriber = $this->user('subscriber');
        $this->actingAs($subscriber)->get('/admin/settings/site-health')->assertForbidden();
        $this->actingAs($subscriber)->getJson('/admin/settings/site-health/test/error_log')->assertForbidden();
    }

    public function test_the_slow_tests_answer_as_json_and_unknown_ones_404(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->getJson('/admin/settings/site-health/test/disk')->assertOk()->assertJsonStructure(['results' => [['id', 'label', 'status']]]);
        $this->actingAs($admin)->getJson('/admin/settings/site-health/test/nope')->assertNotFound();
    }

    public function test_every_fast_check_has_a_known_status(): void
    {
        foreach (SiteHealth::checks() as $check) {
            $this->assertContains($check['status'], ['good', 'recommended', 'critical'], $check['id']);
            $this->assertNotSame('', $check['label']);
        }
    }

    public function test_debug_mode_on_a_live_site_is_critical(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true]);

        $debug = collect(SiteHealth::checks())->firstWhere('id', 'app_debug');
        $this->assertSame('critical', $debug['status']);
    }

    public function test_an_env_file_in_public_is_reported(): void
    {
        $file = public_path('.env');
        @mkdir(public_path(), 0777, true);
        file_put_contents($file, 'APP_KEY=x');
        try {
            $check = collect(SiteHealth::checks())->firstWhere('id', 'exposed_files');
            $this->assertSame('critical', $check['status']);
            $this->assertContains('.env', $check['details']['files']);
        } finally {
            @unlink($file);
        }
    }

    public function test_affected_version_ranges(): void
    {
        $this->assertTrue(SiteHealth::versionAffected('13.23.0', '>=13.0.0,<13.30.0|<12.69.0'));
        $this->assertFalse(SiteHealth::versionAffected('13.30.0', '>=13.0.0,<13.30.0|<12.69.0'));
        $this->assertTrue(SiteHealth::versionAffected('12.1.0', '>=13.0.0,<13.30.0|<12.69.0'));
        $this->assertTrue(SiteHealth::versionAffected('v2.9.0', '>=2.0.0,<=2.10.1'));
        $this->assertFalse(SiteHealth::versionAffected('2.10.2', '>=2.0.0,<=2.10.1'));
        $this->assertFalse(SiteHealth::versionAffected('dev-main', '<9.0'));
    }

    public function test_the_dependency_audit_reports_affected_packages_only(): void
    {
        $installed = SiteHealth::installedPackages();
        $this->assertNotEmpty($installed, 'vendor/composer/installed.json is readable');
        [$name, $version] = [array_key_first($installed), reset($installed)];

        Http::fake(['packagist.org/*' => Http::response(['advisories' => [$name => [
            ['title' => 'Bad thing', 'cve' => 'CVE-1', 'affectedVersions' => '>=0.0.1,<99999', 'severity' => 'high', 'link' => 'https://x'],
            ['title' => 'Old thing', 'cve' => 'CVE-2', 'affectedVersions' => '<0.0.1', 'severity' => 'low', 'link' => null],
        ]]])]);

        $r = SiteHealth::asyncTest('dependencies')[0];
        $this->assertSame('critical', $r['status']);
        $this->assertCount(1, $r['details']['advisories']);
        $this->assertSame('CVE-1', $r['details']['advisories'][0]['cve']);
        $this->assertSame($version, $r['details']['advisories'][0]['installed']);
    }

    public function test_the_code_scan_finds_risky_lines_and_ignores_comments(): void
    {
        $dir = base_path('app/SiteHealthProbe');
        @mkdir($dir, 0777, true);
        $file = $dir.'/Probe.php';
        file_put_contents($file, "<?php\n// eval(\$x) in a comment is fine\n\$a = eval(\$code);\nDB::select(\"select * from t where id = {\$request->id}\");\n\$casts = ['password' => 'hashed'];\n");
        try {
            $r = SiteHealth::asyncTest('code_scan')[0];
            $found = collect($r['details']['findings'] ?? [])->where('file', 'app/SiteHealthProbe/Probe.php');
            $this->assertSame([3, 4], $found->pluck('line')->sort()->values()->all());
            $this->assertSame('critical', $r['status']);
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }

    public function test_the_error_log_groups_recent_errors(): void
    {
        $log = storage_path('logs/site-health-probe.log');
        @mkdir(dirname($log), 0777, true);
        $now = now()->format('Y-m-d H:i:s');
        file_put_contents($log, "[$now] testing.ERROR: Boom happened at /x/app/Foo.php:12)\n[$now] testing.ERROR: Boom happened at /x/app/Foo.php:12)\n[2001-01-01 00:00:00] testing.ERROR: Ancient\n");
        try {
            $r = collect(SiteHealth::asyncTest('error_log'))->firstWhere('id', 'error_log');
            $boom = collect($r['details']['errors'])->firstWhere('message', 'Boom happened at /x/app/Foo.php:12)');
            $this->assertSame(2, $boom['count']);
            $this->assertNull(collect($r['details']['errors'])->firstWhere('message', 'Ancient'), 'older than a week is left out');
        } finally {
            @unlink($log);
        }
    }

    public function test_the_info_inventory_has_the_database_and_php(): void
    {
        $info = SiteHealth::info();
        $this->assertSame(PHP_VERSION, $info['PHP']['Version']);
        $this->assertArrayHasKey('Database name', $info['Database']);
        $this->assertArrayHasKey('Server version', $info['Database']);
        $this->assertSame(app()->version(), $info['Laravel']['Version']);
    }
}
