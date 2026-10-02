<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Customizer → Performance → Clear Cache.
 *
 * The clear itself is Laravel's; what is worth guarding is the writability audit that follows,
 * which used is_writable() and so — on Windows, where that reflects the read-only attribute and
 * not the real ACL — called perfectly writable cache files "stuck" and turned a clean clear into
 * an error telling the user to chown a folder that was never the problem.
 */
class CacheClearTest extends TestCase
{
    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    public function test_clearing_writable_caches_reports_success(): void
    {
        // A real file under the cache dir, writable — the common case. It must not be counted
        // as stuck, and the reply must be a clean success, not a chown warning.
        $dir = storage_path('framework/cache/data');
        @mkdir($dir, 0777, true);
        $probe = $dir.'/falcon-cache-probe.php';
        file_put_contents($probe, '<?php return 1;');

        try {
            $res = $this->actingAs($this->admin())
                ->postJson('/admin/customizer/action/clearCaches')
                ->assertOk();

            $res->assertJson(['success' => true]);
            $this->assertStringNotContainsString('chown', $res->json('message'));
            $this->assertStringContainsString('Cleared', $res->json('message'));
        } finally {
            @unlink($probe);
        }
    }

    public function test_the_write_probe_matches_real_permission_not_the_readonly_attribute(): void
    {
        $controller = new \FalconCms\Core\Http\Controllers\Admin\CustomizerController;
        $method = new \ReflectionMethod($controller, 'isReallyWritable');
        $method->setAccessible(true);

        $file = storage_path('framework/cache/data/falcon-probe-'.uniqid().'.tmp');
        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, 'x');
        try {
            $this->assertTrue($method->invoke($controller, $file), 'a normal file is writable');
            $this->assertFalse($method->invoke($controller, $file.'-does-not-exist'), 'a missing file is not');
        } finally {
            @unlink($file);
        }
    }
}
