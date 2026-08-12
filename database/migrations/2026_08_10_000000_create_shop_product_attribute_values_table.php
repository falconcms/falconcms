<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A flat index of "this product offers this attribute value".
 *
 * Attributes themselves live in JSON (shop_products.attributes_data holds
 * `[{name, values: "Red | Green | Blue", visible, variation}]`), which is fine for editing but
 * unusable for filtering: matching would mean LIKE against a pipe-separated string, and the
 * attribute name would have to be interpolated into a JSON path — bound parameters cannot reach
 * there. One row per (product, attribute, value) makes the filter a plain indexed WHERE, keeps
 * every value a bound parameter, and makes the sidebar counts a single GROUP BY.
 *
 * It is a derived index, never a source of truth: it is rebuilt from the JSON on every save, and
 * `falcon:reindex-attributes` can rebuild the lot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('name', 60);           // as typed by the shop owner, for display
            $table->string('name_slug', 60);      // as it appears in the URL
            $table->string('value', 120);
            $table->string('value_slug', 120);
            $table->timestamps();

            // Filtering reads (name_slug, value_slug); the sidebar groups by them.
            $table->index(['name_slug', 'value_slug'], 'spav_name_value_idx');
            $table->index('post_id', 'spav_post_idx');

            // Two spellings of one value ("Blue" and "blue") must not both be indexed.
            $table->unique(['post_id', 'name_slug', 'value_slug'], 'spav_unique_idx');
        });

        // Deleting a product must not leave it filterable.
        if (Schema::hasTable('posts')) {
            try {
                Schema::table('shop_product_attribute_values', function (Blueprint $table) {
                    $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
                });
            } catch (\Throwable $e) {
                // SQLite and some hosted MySQL setups refuse this; the sync helper prunes instead.
            }
        }

        // Fill in what is already stored, so filters work without re-saving every product.
        if (function_exists('falcon_reindex_all_product_attributes')) {
            try {
                falcon_reindex_all_product_attributes();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Attribute backfill skipped: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_product_attribute_values');
    }
};
