<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The customer role, narrowed the same way the subscriber role was.
 *
 * A customer signs in to the storefront's account page, not to user management, and the
 * `manage_users` they were seeded with only ever drew them a Users menu that answered 403.
 *
 * As with the subscriber migration beside this one, a role that has been edited is left
 * exactly as its administrator set it.
 */
return new class extends Migration
{
    private const SEEDED = ['access_dashboard', 'manage_users', 'access_your_profile', 'access_your_profile_users'];

    private const WANTED = ['access_dashboard', 'access_overview_dashboard'];

    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('role_permission')) {
            return;
        }

        $roleId = DB::table('roles')->where('slug', 'customer')->value('id');
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
            return;
        }

        DB::table('role_permission')->where('role_id', $roleId)->delete();
        foreach ($ids as $id) {
            DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $id]);
        }
    }

    public function down(): void
    {
        // Not reinstated: manage_users on a customer was a menu that led nowhere.
    }
};
