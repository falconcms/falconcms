<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A subscriber gets the Dashboard and its Overview, and nothing else.
 *
 * They were seeded with `manage_users`, which put a Users entry in their sidebar — and every
 * page behind it answered 403, because user management has its own guard. So the menu claimed
 * something the site then refused: not a way in, but a dead end that reads as one.
 *
 * Only a subscriber role that still looks seeded is touched. "Still seeded" means it holds
 * nothing beyond the three permissions the old seeder gave it; the moment an administrator has
 * added anything of their own, this leaves the role exactly as they set it.
 */
return new class extends Migration
{
    /** What the old seeder granted. A role holding only these has never been edited. */
    private const SEEDED = ['access_dashboard', 'manage_users', 'access_your_profile'];

    private const WANTED = ['access_dashboard', 'access_overview_dashboard'];

    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('role_permission')) {
            return;
        }

        $roleId = DB::table('roles')->where('slug', 'subscriber')->value('id');
        if (!$roleId) {
            return;
        }

        $held = DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role_id', $roleId)
            ->pluck('permissions.slug')
            ->all();

        if (array_diff($held, self::SEEDED)) {
            return; // customised — not ours to rewrite
        }

        $ids = DB::table('permissions')->whereIn('slug', self::WANTED)->pluck('id')->all();
        if (count($ids) !== count(self::WANTED)) {
            return; // the permissions this expects are not there yet; the seeder will handle it
        }

        DB::table('role_permission')->where('role_id', $roleId)->delete();
        foreach ($ids as $id) {
            DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $id]);
        }
    }

    public function down(): void
    {
        // Deliberately not reinstated: putting manage_users back would restore a menu that
        // leads nowhere, and anything an administrator has set since is theirs to keep.
    }
};
