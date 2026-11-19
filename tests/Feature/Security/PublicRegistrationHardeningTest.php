<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Support\PublicRegistration;
use FalconCms\Core\Support\SiteHealth;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * The public registration form must never hand a stranger a privileged account.
 *
 * Before: RegisterController gave whatever role Settings → "New User Default Role" named,
 * checked by nobody at registration time. Settings only checked the role when it was changed
 * (and the Customizer import wrote it without any check), so an administrator role, a role
 * holding the plugin installer, or a role that gained such a permission after it was chosen
 * was given to everyone who signed up.
 */
class PublicRegistrationHardeningTest extends TestCase
{
    private const PASSWORD = 'secret-password-1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setCmsOptions(['users_can_register' => '1', 'require_email_verification' => '0']);
    }

    private function roleId(string $slug): int
    {
        return (int) DB::table('roles')->where('slug', $slug)->value('id');
    }

    private function grant(Role|int $role, array $permissions): void
    {
        $roleId = $role instanceof Role ? (int) $role->getKey() : $role;
        foreach ($permissions as $slug) {
            $permissionId = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id;
            DB::table('role_permission')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    /** A role holding exactly $permissions. */
    private function role(string $slug, array $permissions): Role
    {
        $role = Role::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $this->grant($role, $permissions);

        return $role;
    }

    /** Signs a stranger up and returns the account, or null when none was made. */
    private function register(string $name): ?User
    {
        auth()->logout();
        $this->post(route('admin.register'), [
            'name' => ucfirst($name),
            'email' => "{$name}@example.test",
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);

        return User::where('email', "{$name}@example.test")->first();
    }

    private function defaultRole(string $slug): void
    {
        $this->setCmsOptions(['default_role' => $slug]);
    }

    private function assertSubscriber(?User $user): void
    {
        $this->assertNotNull($user, 'registration stopped working');
        $this->assertFalse($user->isAdmin(), 'a stranger became an administrator');
        $this->assertSame($this->roleId('subscriber'), (int) $user->getAttribute('role_id'));
    }

    // ── Administrator roles ───────────────────────────────────────────────

    public function test_default_role_administrator_does_not_make_an_administrator(): void
    {
        $this->defaultRole('administrator');

        $this->assertSubscriber($this->register('stranger'));
    }

    public function test_default_role_super_admin_does_not_make_a_super_admin(): void
    {
        Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $this->defaultRole('super-admin');

        $user = $this->register('stranger');

        $this->assertSubscriber($user);
        $this->assertFalse($user->hasRole('super-admin'));
    }

    public function test_the_legacy_admin_alias_is_an_administrator_role_too(): void
    {
        Role::create(['name' => 'Admin', 'slug' => 'admin']); // a hand-made legacy role, no permissions
        $this->defaultRole('admin');

        $this->assertSubscriber($this->register('stranger'));
    }

    public function test_a_differently_cased_slug_is_judged_by_the_role_it_finds(): void
    {
        // On MySQL "Administrator" finds the administrator row; whatever row the lookup finds
        // is the one judged, so case cannot sneak past the slug check.
        $role = Role::where('slug', 'Administrator')->first();
        $this->defaultRole('Administrator');

        $user = $this->register('stranger');

        $this->assertNotNull($user);
        $this->assertFalse($user->isAdmin());
        if ($role === null) {
            $this->assertSubscriber($user); // SQLite: no such role, so the fallback
        }
    }

    // ── Privileged permissions ────────────────────────────────────────────

    public function test_a_custom_role_with_privileged_permissions_is_not_given_publicly(): void
    {
        $this->role('helper', ['access_dashboard', 'manage_plugins']);
        $this->defaultRole('helper');

        $user = $this->register('stranger');

        $this->assertSubscriber($user);
        $this->assertFalse($user->hasPermission('manage_plugins'));
    }

    public function test_every_privileged_area_is_caught(): void
    {
        foreach ([
            'manage_users', 'access_all_users_users', 'access_roles_users', 'manage_roles', 'manage_settings',
            'access_general_settings', 'access_rest_api_settings', 'manage_plugins', 'access_add_new_plugins',
            'access_themes', 'access_backup_restore', 'access_backup_restore_tools', 'access_wordpress_import_tools',
            'manage_security', 'access_access_security', 'access_updates_dashboard', 'access_license_falcon_builder',
            'access_customizer_falcon_builder', 'manage_options_theme', 'access_settings_shop',
        ] as $slug) {
            $this->assertArrayHasKey($slug, PublicRegistration::privileged([$slug]), "{$slug} was not treated as privileged");
        }

        $this->assertSame([], PublicRegistration::privileged([
            'access_dashboard', 'access_overview_dashboard', 'access_your_profile', 'access_your_profile_users',
            'manage_posts', 'access_all_posts_posts', 'access_comments',
        ]));
    }

    public function test_a_plugin_can_add_a_privileged_permission_but_not_remove_one(): void
    {
        add_falcon_filter('falcon_privileged_permissions', fn ($list) => ['grant_refunds' => 'money'] + (array) $list);
        $this->role('helper', ['access_dashboard', 'grant_refunds']);
        $this->defaultRole('helper');

        $this->assertSubscriber($this->register('stranger'));
        $this->assertArrayHasKey('manage_plugins', PublicRegistration::privileged(['manage_plugins']));
    }

    // ── Drift ─────────────────────────────────────────────────────────────

    public function test_a_role_that_becomes_privileged_after_it_was_chosen_stops_being_given(): void
    {
        $member = $this->role('member', ['access_dashboard', 'access_overview_dashboard']);
        $this->defaultRole('member');

        $first = $this->register('first');
        $this->assertNotNull($first);
        $this->assertSame((int) $member->getKey(), (int) $first->getAttribute('role_id'), 'a safe role was refused');

        // Later, someone gives the role user management. Nothing re-saves the setting.
        $this->grant($member, ['manage_users']);

        $second = $this->register('second');
        $this->assertSubscriber($second);
        $this->assertFalse($second->hasPermission('manage_users'));
    }

    // ── Fallback and failing closed ───────────────────────────────────────

    public function test_a_missing_default_role_falls_back_to_a_safe_subscriber(): void
    {
        $this->defaultRole('no-such-role');

        $this->assertSubscriber($this->register('stranger'));
    }

    public function test_an_unsafe_subscriber_is_not_used_as_the_fallback_and_nobody_is_registered(): void
    {
        // Subscriber is not assumed safe: here it was given settings access.
        $this->grant($this->roleId('subscriber'), ['manage_settings']);
        $this->defaultRole('administrator');
        $before = User::count();

        $response = $this->post(route('admin.register'), [
            'name' => 'Stranger', 'email' => 'stranger@example.test',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame($before, User::count(), 'an account was made without a safe role');
        $this->assertGuest();
    }

    public function test_an_unsafe_subscriber_as_the_default_itself_fails_closed(): void
    {
        $this->grant($this->roleId('subscriber'), ['manage_users']);
        $this->defaultRole('subscriber');

        $this->assertNull($this->register('stranger'));
    }

    public function test_a_missing_role_and_no_subscriber_fails_closed(): void
    {
        DB::table('role_permission')->where('role_id', $this->roleId('subscriber'))->delete();
        DB::table('roles')->where('slug', 'subscriber')->delete();
        $this->defaultRole('no-such-role');

        $this->assertNull($this->register('stranger'));
    }

    public function test_a_refused_role_is_logged_without_the_registrants_details(): void
    {
        /** @var list<MessageLogged> $logged */
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
            if ($e->level === 'warning') {
                $logged[] = $e;
            }
        });
        $this->defaultRole('administrator');

        $this->register('stranger');

        $this->assertCount(1, $logged);
        $entry = $logged[0];
        $flat = $entry->message.' '.json_encode($entry->context);
        $this->assertStringContainsString('default role', $entry->message);
        $this->assertSame('administrator', $entry->context['configured_role'] ?? null);
        $this->assertSame('subscriber', $entry->context['used_role'] ?? null);
        $this->assertStringNotContainsString('stranger@example.test', $flat);
        $this->assertStringNotContainsString(self::PASSWORD, $flat);
    }

    // ── Normal registration ───────────────────────────────────────────────

    public function test_a_safe_custom_role_keeps_working(): void
    {
        $member = $this->role('member', ['access_dashboard', 'access_overview_dashboard', 'access_your_profile_users']);
        $this->defaultRole('member');
        $warnings = 0;
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$warnings) {
            $warnings += $e->level === 'warning' ? 1 : 0;
        });

        $user = $this->register('member1');

        $this->assertNotNull($user);
        $this->assertSame((int) $member->getKey(), (int) $user->getAttribute('role_id'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $warnings, 'a safe role was reported as refused');
    }

    public function test_the_seeded_subscriber_is_safe_and_keeps_working(): void
    {
        $this->defaultRole('subscriber');

        $this->assertSubscriber($this->register('stranger'));
    }

    // ── Site Health ───────────────────────────────────────────────────────

    private function healthCheck(): array
    {
        foreach (SiteHealth::checks() as $check) {
            if ($check['id'] === 'public_registration') {
                return $check;
            }
        }
        $this->fail('Site Health has no public registration check');
    }

    public function test_site_health_reports_the_default_role(): void
    {
        $this->defaultRole('subscriber');
        $this->assertSame(SiteHealth::GOOD, $this->healthCheck()['status']);

        $this->defaultRole('administrator');
        $check = $this->healthCheck();
        $this->assertSame(SiteHealth::CRITICAL, $check['status']);
        $this->assertStringContainsString('administrator role', $check['description']);

        $this->defaultRole('no-such-role');
        $this->assertStringContainsString('does not exist', $this->healthCheck()['description']);

        $this->grant($this->roleId('subscriber'), ['manage_users']);
        $check = $this->healthCheck();
        $this->assertSame(SiteHealth::CRITICAL, $check['status']);
        $this->assertSame('Public registration refuses every new account', $check['label']);

        // Registration off: still said, but not critical.
        $this->setCmsOptions(['users_can_register' => '0']);
        $this->assertSame(SiteHealth::RECOMMENDED, $this->healthCheck()['status']);
    }
}
