<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriber and Customer lose `manage_users` when it is the only thing out of place.
 *
 * Older installs gave both roles exactly the dashboard, the profile screen and `manage_users`.
 * The 2026-09-20 narrowing migrations missed that set (the subscriber one did not know
 * `access_your_profile_users`), so the roles kept `manage_users`. It never opened anything (user
 * management has its own guard), but public registration now refuses any role holding it, so
 * with registration switched on nobody could sign up.
 *
 * Only a role holding exactly the four permissions below is touched, and only `manage_users` is
 * removed: the dashboard and profile stay. A role holding anything else has been set by an
 * administrator and is left exactly as it is; Site Health says how to change it by hand.
 */
return new class extends Migration
{
    private const ROLES = ['subscriber', 'customer'];

    /** The set older installs seeded. A role holding exactly this has never been edited. */
    private const SEEDED = ['access_dashboard', 'access_your_profile', 'access_your_profile_users', 'manage_users'];

    private const REMOVE = 'manage_users';

    public function up(): void
    {
        foreach (['roles', 'permissions', 'role_permission'] as $table) {
            if (!Schema::hasTable($table)) {
                return;
            }
        }

        $removeId = DB::table('permissions')->where('slug', self::REMOVE)->value('id');
        if (!$removeId) {
            return;
        }

        foreach (self::ROLES as $slug) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            if (!$roleId) {
                continue;
            }

            $held = DB::table('role_permission')
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->where('role_permission.role_id', $roleId)
                ->pluck('permissions.slug')
                ->unique()->sort()->values()->all();

            $seeded = self::SEEDED;
            sort($seeded);
            if ($held !== $seeded) {
                continue; // customised (or already narrowed) — not ours to change
            }

            DB::table('role_permission')->where('role_id', $roleId)->where('permission_id', $removeId)->delete();
        }
    }

    public function down(): void
    {
        // Deliberately not reinstated: manage_users on these roles opened nothing and blocks
        // public registration.
    }
};
