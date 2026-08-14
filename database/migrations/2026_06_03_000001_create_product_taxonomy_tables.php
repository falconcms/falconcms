<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives Product its own first-class Category & Tag taxonomies (mirroring Post's
 * dedicated categories/tags), and migrates the existing ACPT product_cat /
 * product_tag terms + their product links into the new tables. The old ACPT
 * taxonomies are deactivated (not deleted) so nothing is lost and it is reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Tables (mirror categories / tags) ---
        if (!Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('lang_code', 10)->default('en')->index();
                $table->unsignedBigInteger('origin_id')->nullable()->index();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->unique(['slug', 'lang_code']);
            });
        }

        if (!Schema::hasTable('product_tags')) {
            Schema::create('product_tags', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->string('lang_code', 10)->default('en')->index();
                $table->unsignedBigInteger('origin_id')->nullable()->index();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->unique(['slug', 'lang_code']);
            });
        }

        if (!Schema::hasTable('product_category_post')) {
            Schema::create('product_category_post', function (Blueprint $table) {
                $table->foreignId('product_category_id')->constrained('product_categories')->cascadeOnDelete();
                $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $table->primary(['product_category_id', 'post_id']);
            });
        }

        if (!Schema::hasTable('product_tag_post')) {
            Schema::create('product_tag_post', function (Blueprint $table) {
                $table->foreignId('product_tag_id')->constrained('product_tags')->cascadeOnDelete();
                $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $table->primary(['product_tag_id', 'post_id']);
            });
        }

        // --- Migrate existing ACPT data (idempotent: only on first, empty run) ---
        if (!Schema::hasTable('custom_taxonomies') || !Schema::hasTable('taxonomy_terms')) {
            return;
        }
        if (DB::table('product_categories')->count() > 0 || DB::table('product_tags')->count() > 0) {
            return; // already migrated
        }

        $catSlugs = ['product_cat', 'product_category', 'product_categories'];
        $tagSlugs = ['product_tag', 'product_tags'];

        $catTaxIds = DB::table('custom_taxonomies')->whereIn('slug', $catSlugs)->pluck('id')->all();
        $tagTaxIds = DB::table('custom_taxonomies')->whereIn('slug', $tagSlugs)->pluck('id')->all();

        // taxonomy_terms may be keyed by taxonomy_slug (older schema) or taxonomy_id — support both.
        $hasSlugCol = Schema::hasColumn('taxonomy_terms', 'taxonomy_slug');
        $fetchTerms = function (array $slugs, array $taxIds) use ($hasSlugCol) {
            if ($hasSlugCol) {
                return DB::table('taxonomy_terms')->whereIn('taxonomy_slug', $slugs)->orderBy('id')->get();
            }
            if (empty($taxIds)) {
                return collect();
            }

            return DB::table('taxonomy_terms')->whereIn('taxonomy_id', $taxIds)->orderBy('id')->get();
        };

        $now = now();

        // Categories (hierarchical) — two passes: insert, then remap parents.
        $catMap = []; // old taxonomy_term id => new product_category id

        $terms = $fetchTerms($catSlugs, $catTaxIds);
        foreach ($terms as $t) {
            $exists = DB::table('product_categories')
                ->where('slug', $t->slug)->where('lang_code', $t->lang_code ?? 'en')->first();
            if ($exists) {
                $catMap[$t->id] = $exists->id;

                continue;
            }
            $newId = DB::table('product_categories')->insertGetId([
                'parent_id' => null,
                'name' => $t->name,
                'slug' => $t->slug,
                'lang_code' => $t->lang_code ?? 'en',
                'origin_id' => $t->origin_id ?? null,
                'description' => $t->description ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $catMap[$t->id] = $newId;
        }
        // remap parents
        foreach ($terms as $t) {
            if (!empty($t->parent_id) && isset($catMap[$t->id], $catMap[$t->parent_id])) {
                DB::table('product_categories')->where('id', $catMap[$t->id])
                    ->update(['parent_id' => $catMap[$t->parent_id]]);
            }
        }
        // pivot links
        if (Schema::hasTable('post_taxonomy_term')) {
            $links = DB::table('post_taxonomy_term')
                ->whereIn('taxonomy_term_id', array_keys($catMap))->get();
            foreach ($links as $l) {
                if (!isset($catMap[$l->taxonomy_term_id])) {
                    continue;
                }
                DB::table('product_category_post')->insertOrIgnore([
                    'product_category_id' => $catMap[$l->taxonomy_term_id],
                    'post_id' => $l->post_id,
                ]);
            }
        }

        // Tags (flat)
        $tagMap = [];

        $terms = $fetchTerms($tagSlugs, $tagTaxIds);
        foreach ($terms as $t) {
            $exists = DB::table('product_tags')
                ->where('slug', $t->slug)->where('lang_code', $t->lang_code ?? 'en')->first();
            if ($exists) {
                $tagMap[$t->id] = $exists->id;

                continue;
            }
            $newId = DB::table('product_tags')->insertGetId([
                'name' => $t->name,
                'slug' => $t->slug,
                'lang_code' => $t->lang_code ?? 'en',
                'origin_id' => $t->origin_id ?? null,
                'description' => $t->description ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $tagMap[$t->id] = $newId;
        }
        if (Schema::hasTable('post_taxonomy_term')) {
            $links = DB::table('post_taxonomy_term')
                ->whereIn('taxonomy_term_id', array_keys($tagMap))->get();
            foreach ($links as $l) {
                if (!isset($tagMap[$l->taxonomy_term_id])) {
                    continue;
                }
                DB::table('product_tag_post')->insertOrIgnore([
                    'product_tag_id' => $tagMap[$l->taxonomy_term_id],
                    'post_id' => $l->post_id,
                ]);
            }
        }

        // Deactivate the old ACPT product taxonomies so they no longer appear as
        // duplicate cards in the menu builder / product editor (kept, not deleted).
        $oldTaxIds = array_merge($catTaxIds, $tagTaxIds);
        if (!empty($oldTaxIds)) {
            DB::table('custom_taxonomies')->whereIn('id', $oldTaxIds)->update(['is_active' => false]);
        }

        // Re-point the existing admin "Products → Categories/Tags" sidebar items to the
        // new dedicated routes (they previously pointed at the post category/tag pages).
        if (Schema::hasTable('menus')) {
            $productMenu = DB::table('menus')->where('title', 'Products')->whereNull('parent_id')->first();
            if ($productMenu) {
                DB::table('menus')->where('parent_id', $productMenu->id)->where('title', 'Categories')
                    ->update(['route' => 'admin.product-categories.index', 'params' => null]);
                DB::table('menus')->where('parent_id', $productMenu->id)->where('title', 'Tags')
                    ->update(['route' => 'admin.product-tags.index', 'params' => null]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tag_post');
        Schema::dropIfExists('product_category_post');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_categories');
        // Reactivate the old ACPT product taxonomies on rollback.
        if (Schema::hasTable('custom_taxonomies')) {
            DB::table('custom_taxonomies')
                ->whereIn('slug', ['product_cat', 'product_category', 'product_categories', 'product_tag', 'product_tags'])
                ->update(['is_active' => true]);
        }
    }
};
