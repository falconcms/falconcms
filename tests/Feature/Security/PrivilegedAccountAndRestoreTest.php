<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * User management must not be a way to take the site from its administrators, and the backup
 * permissions must not be a way to replace the database.
 *
 * Before: UserController::destroy() and toggleBlock() protected only Super Admin accounts, and
 * bulk() protected nobody, so a non-admin user manager could delete or block administrators,
 * Super Admins and anyone stronger than themselves. BackupController::restore() accepted
 * manage_settings, access_backup_restore, access_backups or access_tools, so any of them could
 * restore a dump that rewrote users, roles and permissions.
 */
class PrivilegedAccountAndRestoreTest extends TestCase
{
    use MakesShopFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // The menus decide who reaches a screen (AdminMiddleware), so the real ones are seeded.
        (new MenuSeeder)->run();
    }

    private function roleId(string $slug): int
    {
        return (int) DB::table('roles')->where('slug', $slug)->value('id')
            ?: (int) Role::create(['name' => ucfirst($slug), 'slug' => $slug])->getKey();
    }

    private function role(string $slug, array $permissions): Role
    {
        $role = Role::create(['name' => ucfirst($slug), 'slug' => $slug]);
        foreach ($permissions as $p) {
            DB::table('role_permission')->insert(['role_id' => $role->id, 'permission_id' => Permission::firstOrCreate(['slug' => $p], ['name' => $p])->id]);
        }

        return $role;
    }

    private const MANAGER = ['manage_users', 'access_users', 'access_all_users_users', 'access_add_new_users', 'manage_posts'];

    /** A non-admin user manager: may list, create, edit, delete and block users. */
    private function manager(): User
    {
        return $this->makeUser(['role_id' => $this->role('user-manager-'.uniqid(), self::MANAGER)->id]);
    }

    private function admin(): User
    {
        return $this->makeUser(['role_id' => $this->roleId('administrator')]);
    }

    private function superAdmin(): User
    {
        return $this->makeUser(['role_id' => $this->roleId('super-admin')]);
    }

    private function subscriber(): User
    {
        return $this->makeUser(['role_id' => $this->roleId('subscriber')]);
    }

    /** A subscriber by its main role, an administrator by an extra one (role_user). */
    private function adminByExtraRole(): User
    {
        $user = $this->subscriber();
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $this->roleId('administrator')]);

        return $user;
    }

    /** A non-admin stronger than the manager: it can install plugins. */
    private function strongerNonAdmin(): User
    {
        return $this->makeUser(['role_id' => $this->role('plugin-person-'.uniqid(), ['manage_plugins'])->id]);
    }

    private function exists(User $user): bool
    {
        return DB::table('users')->where('id', $user->id)->exists();
    }

    private function blocked(User $user): bool
    {
        return (bool) DB::table('users')->where('id', $user->id)->value('is_blocked');
    }

    private function block(User $user): void
    {
        DB::table('users')->where('id', $user->id)->update(['is_blocked' => true]);
    }

    public static function protectedTargets(): array
    {
        return [
            'an administrator' => ['admin'],
            'a Super Admin' => ['superAdmin'],
            'an administrator by an extra role (role_user)' => ['adminByExtraRole'],
            'a non-admin stronger than the manager' => ['strongerNonAdmin'],
        ];
    }

    // ── Deleting ──────────────────────────────────────────────────────────

    #[DataProvider('protectedTargets')]
    public function test_a_non_admin_cannot_delete_a_stronger_account(string $target): void
    {
        $victim = $this->{$target}();

        $this->actingAs($this->manager())->from('/admin/users')->delete("/admin/users/{$victim->id}")
            ->assertSessionHas('error');

        $this->assertTrue($this->exists($victim), "a non-admin deleted {$target}");
    }

    // ── Blocking and unblocking ───────────────────────────────────────────

    #[DataProvider('protectedTargets')]
    public function test_a_non_admin_cannot_block_or_unblock_a_stronger_account(string $target): void
    {
        $manager = $this->manager();
        $victim = $this->{$target}();

        $this->actingAs($manager)->post("/admin/users/{$victim->id}/toggle-block")->assertSessionHas('error');
        $this->assertFalse($this->blocked($victim), "a non-admin blocked {$target}");

        // One an administrator blocked stays blocked.
        $this->block($victim);
        $this->actingAs($manager)->post("/admin/users/{$victim->id}/toggle-block")->assertSessionHas('error');
        $this->assertTrue($this->blocked($victim), "a non-admin unblocked {$target}");
    }

    // ── Bulk ──────────────────────────────────────────────────────────────

    public static function bulkActions(): array
    {
        return ['delete' => ['delete'], 'block' => ['block'], 'unblock' => ['unblock']];
    }

    #[DataProvider('bulkActions')]
    public function test_a_mixed_bulk_selection_is_refused_as_a_whole(string $action): void
    {
        $manager = $this->manager();
        $ordinary = $this->subscriber();
        $protected = [$this->admin(), $this->superAdmin(), $this->adminByExtraRole()];
        foreach ($protected as $victim) {
            if ($action === 'unblock') {
                $this->block($victim);
            }
            if ($action === 'unblock') {
                $this->block($ordinary);
            }

            $this->actingAs($manager)->from('/admin/users')
                ->post('/admin/users/bulk', ['ids' => [(string) $ordinary->id, (string) $victim->id], 'action' => $action])
                ->assertSessionHas('error', fn ($m) => str_contains((string) $m, 'Nothing was changed'));

            // Nothing happened to either account.
            $this->assertTrue($this->exists($ordinary) && $this->exists($victim), "bulk {$action} deleted someone");
            $this->assertSame($action === 'unblock', $this->blocked($ordinary), "bulk {$action} changed the ordinary account");
            $this->assertSame($action === 'unblock', $this->blocked($victim), "bulk {$action} changed the protected account");
        }
    }

    public function test_a_manager_still_manages_accounts_they_could_have_made(): void
    {
        $manager = $this->manager();
        [$a, $b, $c] = [$this->subscriber(), $this->subscriber(), $this->subscriber()];

        $this->actingAs($manager)->post("/admin/users/{$a->id}/toggle-block")->assertSessionHas('success');
        $this->assertTrue($this->blocked($a));
        $this->actingAs($manager)->post("/admin/users/{$a->id}/toggle-block")->assertSessionHas('success');
        $this->assertFalse($this->blocked($a));

        $this->actingAs($manager)->from('/admin/users')->post('/admin/users/bulk', ['ids' => [$b->id, $c->id, $manager->id], 'action' => 'block'])
            ->assertSessionHas('success');
        $this->assertTrue($this->blocked($b) && $this->blocked($c));
        $this->assertFalse($this->blocked($manager), 'the manager blocked themselves');

        $this->actingAs($manager)->from('/admin/users')->post('/admin/users/bulk', ['ids' => [$b->id, $c->id], 'action' => 'delete'])
            ->assertSessionHas('success');
        $this->assertFalse($this->exists($b) || $this->exists($c));

        $this->actingAs($manager)->delete("/admin/users/{$a->id}")->assertSessionHas('success');
        $this->assertFalse($this->exists($a));
    }

    public function test_administrators_and_super_admins_keep_their_workflows(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        $super = $this->superAdmin();

        // An administrator manages other administrators, never a Super Admin.
        $this->actingAs($admin)->post("/admin/users/{$otherAdmin->id}/toggle-block")->assertSessionHas('success');
        $this->assertTrue($this->blocked($otherAdmin));
        $this->actingAs($admin)->post("/admin/users/{$super->id}/toggle-block")->assertSessionHas('error');
        $this->assertFalse($this->blocked($super));
        $this->actingAs($admin)->from('/admin/users')->post('/admin/users/bulk', ['ids' => [$otherAdmin->id, $super->id], 'action' => 'delete'])
            ->assertSessionHas('error');
        $this->assertTrue($this->exists($super) && $this->exists($otherAdmin));
        $this->actingAs($admin)->delete("/admin/users/{$otherAdmin->id}")->assertSessionHas('success');
        $this->assertFalse($this->exists($otherAdmin));

        // A Super Admin manages everyone else, Super Admins included.
        $otherSuper = $this->superAdmin();
        $this->actingAs($super)->post("/admin/users/{$admin->id}/toggle-block")->assertSessionHas('success');
        $this->actingAs($super)->from('/admin/users')->post('/admin/users/bulk', ['ids' => [$admin->id, $otherSuper->id], 'action' => 'delete'])
            ->assertSessionHas('success');
        $this->assertFalse($this->exists($admin) || $this->exists($otherSuper));
        $this->assertTrue($this->exists($super), 'the Super Admin deleted themselves');
    }

    // ── Restoring a backup ────────────────────────────────────────────────

    /** A database backup that, restored, writes a marker; returns its file name. */
    private function backupFile(): string
    {
        DB::table('cms_settings')->updateOrInsert(['key' => 'restore_marker'], ['value' => 'before']);
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $file = 'restore-auth-'.uniqid().'.sql';
        file_put_contents("{$dir}/{$file}", "-- Falcon CMS Backup\n-- Database: test\n-- Date: now\n\n"
            ."UPDATE cms_settings SET value = 'after' WHERE \"key\" = 'restore_marker';\n");

        return $file;
    }

    private function marker(): ?string
    {
        return DB::table('cms_settings')->where('key', 'restore_marker')->value('value');
    }

    /** Someone who reaches Tools → Backup & Restore with one of the permissions the screen accepts. */
    private function backupOperator(string $permission): User
    {
        // The screen's own menu permission (so AdminMiddleware lets them in) and exactly one of
        // the permissions BackupController::checkAccess() accepts.
        return $this->makeUser(['role_id' => $this->role('backup-'.$permission.'-'.uniqid(), ['access_dashboard', 'access_backup_restore_tools', $permission])->id]);
    }

    public static function backupPermissions(): array
    {
        return [
            'manage_settings' => ['manage_settings'],
            'access_backup_restore' => ['access_backup_restore'],
            'access_backups' => ['access_backups'],
            'access_tools' => ['access_tools'],
        ];
    }

    #[DataProvider('backupPermissions')]
    public function test_a_backup_permission_alone_cannot_restore_the_database(string $permission): void
    {
        $file = $this->backupFile();
        $operator = $this->backupOperator($permission);
        try {
            // They still reach the screen, and their backups…
            $this->actingAs($operator)->get('/admin/tools/backup')->assertOk();
            $this->actingAs($operator)->get("/admin/tools/backup/download/{$file}")->assertOk();
            // …but never put one back, a database dump or a full (zip) backup alike.
            $this->actingAs($operator)->from('/admin/tools/backup')->post("/admin/tools/backup/restore/{$file}")->assertForbidden();
            $this->actingAs($operator)->from('/admin/tools/backup')->post('/admin/tools/backup/restore/whatever.zip')->assertForbidden();
        } finally {
            @unlink(storage_path("app/backups/{$file}"));
        }

        $this->assertSame('before', $this->marker(), "{$permission} restored the database");
    }

    public static function restoringAdministrators(): array
    {
        return ['an administrator' => ['admin'], 'a Super Admin' => ['superAdmin'], 'an administrator by an extra role' => ['adminByExtraRole']];
    }

    #[DataProvider('restoringAdministrators')]
    public function test_administrators_restore(string $who): void
    {
        $file = $this->backupFile();
        try {
            $this->actingAs($this->{$who}())->from('/admin/tools/backup')->post("/admin/tools/backup/restore/{$file}")
                ->assertSessionHas('success');
        } finally {
            @unlink(storage_path("app/backups/{$file}"));
        }

        $this->assertSame('after', $this->marker());
    }

    // ── The core rule ─────────────────────────────────────────────────────

    public function test_assignment_error_refuses_a_missing_actor(): void
    {
        $this->assertNotNull(Role::assignmentError(null, [$this->roleId('subscriber')]));
        $this->assertNotNull(Role::assignmentError(null, [$this->roleId('administrator')]));
        $this->assertNotNull(Role::assignmentError(null, []));
        // An object that cannot say what it may do is no better than nobody.
        $this->assertNotNull(Role::assignmentError(new \stdClass, [$this->roleId('subscriber')]));
    }
}
