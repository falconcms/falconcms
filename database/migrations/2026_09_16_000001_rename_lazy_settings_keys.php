<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move three settings off the old brand's key names.
 *
 * These rows hold the builder library, the saved global sections and the mega menus — real
 * work, not preferences. The code reads the new names now and falls back to the old ones, so
 * nothing is lost either way round; this brings the stored rows across so the fallback stops
 * being needed.
 *
 * A row is only moved when the new key is free. If both exist, whatever is already under the
 * new name is the newer of the two and wins.
 */
return new class extends Migration
{
    private const RENAMES = [
        'lazy_builder_library' => 'falcon_builder_library',
        'lazy_global_sections' => 'falcon_global_sections',
        'lazy_mega_menus' => 'falcon_mega_menus',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('cms_settings')) {
            return;
        }

        foreach (self::RENAMES as $old => $new) {
            if (DB::table('cms_settings')->where('key', $new)->exists()) {
                continue;
            }
            DB::table('cms_settings')->where('key', $old)->update(['key' => $new]);
        }

        if (function_exists('forget_cms_options_cache')) {
            forget_cms_options_cache();
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('cms_settings')) {
            return;
        }

        foreach (self::RENAMES as $old => $new) {
            if (DB::table('cms_settings')->where('key', $old)->exists()) {
                continue;
            }
            DB::table('cms_settings')->where('key', $new)->update(['key' => $old]);
        }

        if (function_exists('forget_cms_options_cache')) {
            forget_cms_options_cache();
        }
    }
};
