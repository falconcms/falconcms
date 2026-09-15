<?php

use FalconCms\Core\Database\Seeders\MenuSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shop and Products get a sidebar section of their own, headed "eCommerce", the way ACPT heads
 * "Advanced".
 *
 * They were two more unlabelled entries in a long Main list, which is what made it possible to
 * lose track of whether something had crept between them. A section says what they are, and a
 * site that sells nothing can read the heading and skip both.
 *
 * Their orders are left alone: a group's place in the sidebar comes from where its first member
 * falls in the overall sort, so keeping Shop at 55 puts the new section directly after the Main
 * items and before Advanced.
 */
return new class extends Migration
{
    private const PAIR = ['Shop', 'Products'];

    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->whereNull('parent_id')
            ->whereIn('title', self::PAIR)
            ->update(['group' => MenuSeeder::ECOMMERCE_GROUP]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->whereNull('parent_id')
            ->whereIn('title', self::PAIR)
            ->update(['group' => 'Main']);
    }
};
