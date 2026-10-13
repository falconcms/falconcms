<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEO's own permissions, now that it is a menu of its own (granted in Users → Roles).
 *
 * A role that could open an SEO screen under Settings keeps it: the permission the old menu
 * entry gave becomes the new one, and any of them brings the SEO menu itself.
 */
return new class extends Migration
{
    /** new permission => [its name, the permission the same screen had under Settings] */
    private const PERMISSIONS = [
        'access_seo' => ['SEO', null],
        'access_seo_settings' => ['SEO Settings', 'access_seo_settings'],
        'access_seo_site_analysis' => ['SEO Site Analysis', 'access_site_analysis_settings'],
        'access_seo_audit' => ['SEO Audit', 'access_seo_audit_settings'],
        'access_seo_bulk' => ['Bulk SEO', 'access_bulk_seo_settings'],
        'access_seo_redirects' => ['SEO Redirects', 'access_redirects_settings'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('role_permission')) {
            return;
        }

        $ids = [];
        foreach (self::PERMISSIONS as $slug => [$name]) {
            $ids[$slug] = DB::table('permissions')->where('slug', $slug)->value('id')
                ?: DB::table('permissions')->insertGetId(['name' => $name, 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
        }

        $grants = [];
        foreach (self::PERMISSIONS as $slug => [, $was]) {
            if (!$was) {
                continue;
            }
            $roles = DB::table('role_permission')->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->where('permissions.slug', $was)->pluck('role_permission.role_id');
            foreach ($roles as $roleId) {
                $grants[$roleId][] = $ids[$slug];
                $grants[$roleId][] = $ids['access_seo'];
            }
        }
        // The administrators' roles hold every permission there is.
        foreach (DB::table('roles')->whereIn('slug', ['administrator', 'super-admin'])->pluck('id') as $roleId) {
            $grants[$roleId] = array_values($ids);
        }

        foreach ($grants as $roleId => $permissionIds) {
            $have = DB::table('role_permission')->where('role_id', $roleId)->pluck('permission_id')->all();
            foreach (array_diff(array_unique($permissionIds), $have) as $permissionId) {
                DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        // The permissions stay: roles may have been given them since.
    }
};
