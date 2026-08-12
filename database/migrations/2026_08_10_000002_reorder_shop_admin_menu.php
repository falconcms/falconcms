<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep Shop directly under Products in the admin sidebar.
 *
 * Products sits at 55 and Shop sat at 60, while custom post types were numbered `50 + id`.
 * Any post type with an id of 6 to 9 therefore landed in the gap and split the pair — which is
 * exactly what happened on sites with a few CPTs.
 *
 * Shop moves to 56 and the custom post types move to `60 + id`, so the band they occupy starts
 * above the pair instead of running through it. Only the Main group matters here: the sidebar
 * renders each group separately, so items in Advanced/System can never interleave.
 *
 * Ordering *within* the custom post types is preserved, since every one shifts by the same 10.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->whereNull('parent_id')
            ->where('title', 'Shop')
            ->where('group', 'Main')
            ->update(['order' => 56]);

        if (! Schema::hasTable('post_types')) {
            return;
        }

        // CPT menu rows are the ones pointing at the raw post list for a type; the built-in
        // Products/Posts/Pages items use named routes, so they are not touched.
        foreach (DB::table('post_types')->get(['id', 'slug']) as $postType) {
            DB::table('menus')
                ->whereNull('parent_id')
                ->where('route', '/admin/posts?type=' . $postType->slug)
                ->update(['order' => 60 + $postType->id]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->whereNull('parent_id')
            ->where('title', 'Shop')
            ->where('group', 'Main')
            ->update(['order' => 60]);

        if (! Schema::hasTable('post_types')) {
            return;
        }

        foreach (DB::table('post_types')->get(['id', 'slug']) as $postType) {
            DB::table('menus')
                ->whereNull('parent_id')
                ->where('route', '/admin/posts?type=' . $postType->slug)
                ->update(['order' => 50 + $postType->id]);
        }
    }
};
