<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Edits to live content waiting for review: only the fields that differ from what readers see.
 *
 * Contributor also gets what its description has always promised. Its seeded permissions had
 * neither the posts list nor Add New, so a contributor could not write anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_post_changes')) {
            Schema::create('cms_post_changes', function (Blueprint $table) {
                $table->unsignedBigInteger('post_id')->primary();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->longText('data');
                $table->text('note')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('role_permission')) {
            return;
        }

        $roleId = DB::table('roles')->where('slug', 'contributor')->value('id');
        if (!$roleId) {
            return;
        }

        $held = DB::table('role_permission')->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role_id', $roleId)->pluck('permissions.slug')->all();

        // Left alone once somebody has given it either: then it is a choice, not the old seed.
        if (!in_array('manage_posts', $held, true) || array_intersect(['access_all_posts_posts', 'access_add_new_posts'], $held) !== []) {
            return;
        }

        foreach (['access_all_posts_posts', 'access_add_new_posts'] as $slug) {
            DB::table('permissions')->insertOrIgnore([
                'name' => ucwords(str_replace('_', ' ', $slug)), 'slug' => $slug, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_post_changes');
    }
};
