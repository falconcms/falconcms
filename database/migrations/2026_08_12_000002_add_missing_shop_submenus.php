<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Put Reports and Promotions into the Shop submenu on sites that already exist.
 *
 * MenuSeeder only runs at install time, so anything added to the sidebar afterwards never
 * reaches a site that is already running. Both screens have been reachable by URL but absent
 * from the menu: Reports since it was built, Promotions since it shipped.
 *
 * Matched on route rather than title so a shop owner who renamed the item keeps their wording,
 * and re-running this cannot produce a duplicate.
 */
return new class extends Migration
{
    /** @var array<int, array{title: string, route: string, order: int}> */
    private array $items = [
        ['title' => 'Reports',    'route' => 'admin.shop.reports.index',    'order' => 3],
        ['title' => 'Promotions', 'route' => 'admin.shop.promotions.index', 'order' => 5],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $shopId = DB::table('menus')
            ->whereNull('parent_id')
            ->where('route', 'admin.shop.orders.index')
            ->value('id');

        // Fall back to the title for a site whose Shop item points somewhere else.
        $shopId = $shopId ?: DB::table('menus')
            ->whereNull('parent_id')
            ->where('title', 'Shop')
            ->value('id');

        if (! $shopId) {
            return;   // no Shop menu on this site; nothing to hang these off
        }

        foreach ($this->items as $item) {
            $exists = DB::table('menus')
                ->where('parent_id', $shopId)
                ->where('route', $item['route'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('menus')->insert([
                'parent_id'  => $shopId,
                'title'      => $item['title'],
                'route'      => $item['route'],
                'params'     => null,
                'order'      => $item['order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')
            ->whereNotNull('parent_id')
            ->whereIn('route', array_column($this->items, 'route'))
            ->delete();
    }
};
