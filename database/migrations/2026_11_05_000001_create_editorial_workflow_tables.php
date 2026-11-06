<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The editorial workflow: who is editing what right now, each item's review history, and the
 * permission that decides who may publish.
 *
 * Every role keeps what it could do the day before, except Contributor, Subscriber and Customer:
 * those never had publishing as their job, and Contributor is described as unable to publish.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_post_locks')) {
            Schema::create('cms_post_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('post_id')->primary();
                $table->unsignedBigInteger('user_id');
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('heartbeat_at')->nullable()->index();
            });
        }

        if (!Schema::hasTable('cms_post_reviews')) {
            Schema::create('cms_post_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('post_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 20);
                $table->text('note')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_permission')) {
            return;
        }

        $slug = 'publish_content';
        DB::table('permissions')->insertOrIgnore([
            'name' => 'Publish & Review Content',
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

        // Only roles already set up. On a fresh install the seeder gives each role its permissions,
        // and only to a role holding none: granting this first would make it skip them all. And
        // only roles that can write something: a shop role a plugin added has nothing to publish.
        $rows = DB::table('roles')
            ->whereNotIn('slug', ['contributor', 'subscriber', 'customer'])
            ->whereIn('id', DB::table('role_permission')
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->where(fn ($q) => $q->whereIn('permissions.slug', ['manage_posts', 'manage_pages', 'manage_products', 'manage_content'])
                    ->orWhere('permissions.slug', 'like', 'access_add_new_%')
                    ->orWhere('permissions.slug', 'like', 'manage_%')->where('permissions.slug', 'not like', 'manage_options_%')
                    ->whereNotIn('permissions.slug', ['manage_users', 'manage_roles', 'manage_settings', 'manage_media', 'manage_analytics', 'manage_tools', 'manage_seo', 'manage_plugins', 'manage_comments', 'manage_forms', 'manage_appearance', 'manage_acpt', 'manage_categories', 'manage_tags']))
                ->select('role_permission.role_id'))
            ->pluck('id')
            ->map(fn ($roleId) => ['role_id' => $roleId, 'permission_id' => $permissionId])
            ->all();

        if ($rows !== []) {
            DB::table('role_permission')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_post_locks');
        Schema::dropIfExists('cms_post_reviews');

        if (Schema::hasTable('permissions')) {
            $id = DB::table('permissions')->where('slug', 'publish_content')->value('id');
            if ($id) {
                if (Schema::hasTable('role_permission')) {
                    DB::table('role_permission')->where('permission_id', $id)->delete();
                }
                DB::table('permissions')->where('id', $id)->delete();
            }
        }
    }
};
