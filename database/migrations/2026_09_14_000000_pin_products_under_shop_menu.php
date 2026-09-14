<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shop, then Products directly beneath it, with nothing able to come between them.
 *
 * The previous attempt (2026_08_10_000002) pinned the pair to 55/56 and moved custom post types
 * to `60 + id`, but three other code paths — updating, duplicating and re-activating a post type —
 * still numbered them `40 + id`. A post type with id 16 therefore came back as order 56, tying
 * with Shop, and the sidebar split the pair again the next time anyone edited that type.
 *
 * The bands are gone now (see MenuPlacement), so this only has to repair the rows they left:
 * put Shop above Products, then renumber everything below the pair so no two menus in the Main
 * group share an order. Relative order among the custom post types is preserved.
 */
return new class extends Migration
{
    /** Where the locked pair sits. Everything else in Main is pushed below it. */
    private const SHOP = 55;

    private const PRODUCTS = 56;

    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $pair = ['Shop' => self::SHOP, 'Products' => self::PRODUCTS];
        foreach ($pair as $title => $order) {
            DB::table('menus')
                ->whereNull('parent_id')
                ->where('group', 'Main')
                ->where('title', $title)
                ->update(['order' => $order]);
        }

        // Anything that was sitting at or below the pair gets a fresh, unique order under it,
        // keeping the order it is in today (ties broken by id, the same way the sidebar does).
        $below = DB::table('menus')
            ->whereNull('parent_id')
            ->where('group', 'Main')
            ->where('order', '>=', self::SHOP)
            ->whereNotIn('title', array_keys($pair))
            ->orderBy('order')->orderBy('id')
            ->get(['id']);

        $next = self::PRODUCTS + 1;
        foreach ($below as $menu) {
            DB::table('menus')->where('id', $menu->id)->update(['order' => $next++]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        // Back to the 2026_08_10_000002 arrangement: Products above Shop.
        DB::table('menus')->whereNull('parent_id')->where('group', 'Main')
            ->where('title', 'Products')->update(['order' => 55]);
        DB::table('menus')->whereNull('parent_id')->where('group', 'Main')
            ->where('title', 'Shop')->update(['order' => 56]);
    }
};
