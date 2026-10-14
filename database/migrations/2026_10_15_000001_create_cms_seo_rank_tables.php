<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Falcon SEO's Rank Tracker (Pro, with DataForSEO): the keywords followed in Google, one row per
 * keyword, country and device, and the position found at each check. Its own permission comes
 * too, for SEO → Rank Tracker in Users → Roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_seo_rank_keywords')) {
            Schema::create('cms_seo_rank_keywords', function (Blueprint $table) {
                $table->id();
                $table->string('keyword', 200);
                $table->char('country', 2);
                $table->string('device', 10)->default('desktop');
                // Google's monthly searches and ranking difficulty, read once when it is added.
                $table->unsignedInteger('search_volume')->nullable();
                $table->unsignedTinyInteger('difficulty')->nullable();
                // The latest check: the site's best position in the top 100 (null: not in it).
                $table->unsignedSmallInteger('position')->nullable();
                $table->unsignedSmallInteger('previous_position')->nullable();
                $table->unsignedSmallInteger('best_position')->nullable();
                $table->string('url', 2048)->nullable();
                // What else the page of results shows: featured snippet, People also ask, videos…
                $table->text('serp_features')->nullable();
                $table->boolean('snippet_owned')->default(false);
                $table->string('leader', 255)->nullable();
                $table->string('error', 500)->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();
                $table->unique(['keyword', 'country', 'device']);
                $table->index('checked_at');
            });
        }

        if (!Schema::hasTable('cms_seo_rank_history')) {
            Schema::create('cms_seo_rank_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('keyword_id')->constrained('cms_seo_rank_keywords')->cascadeOnDelete();
                $table->date('checked_on');
                $table->unsignedSmallInteger('position')->nullable();
                $table->string('url', 2048)->nullable();
                $table->unique(['keyword_id', 'checked_on']);
            });
        }

        $this->permission();
    }

    /** SEO → Rank Tracker's permission: for the administrators, and every role that has SEO. */
    private function permission(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('role_permission')) {
            return;
        }
        $id = DB::table('permissions')->where('slug', 'access_seo_rank_tracker')->value('id')
            ?: DB::table('permissions')->insertGetId(['name' => 'SEO Rank Tracker', 'slug' => 'access_seo_rank_tracker', 'created_at' => now(), 'updated_at' => now()]);

        $roles = DB::table('role_permission')->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('permissions.slug', 'access_seo')->pluck('role_permission.role_id')
            ->merge(DB::table('roles')->whereIn('slug', ['administrator', 'super-admin'])->pluck('id'))
            ->unique();
        foreach ($roles as $roleId) {
            if (!DB::table('role_permission')->where('role_id', $roleId)->where('permission_id', $id)->exists()) {
                DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_seo_rank_history');
        Schema::dropIfExists('cms_seo_rank_keywords');
    }
};
