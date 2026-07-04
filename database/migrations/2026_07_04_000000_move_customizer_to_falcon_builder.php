<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the admin sidebar "Customizer" item out of Appearance and under Falcon Builder for
 * EXISTING installs (fresh installs get it via MenuSeeder). Also normalises a legacy top-level
 * "Lazy Builder" menu to "Falcon Builder". Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus')) return;

        // 1) Legacy "Lazy Builder" → "Falcon Builder".
        DB::table('menus')->whereNull('parent_id')->where('title', 'Lazy Builder')->update([
            'title'      => 'Falcon Builder',
            'route'      => 'admin.falcon-builder.sections',
            'updated_at' => now(),
        ]);

        // 2) Find (or create) the top-level Falcon Builder menu.
        $fb = DB::table('menus')->whereNull('parent_id')
            ->where(function ($q) {
                $q->where('title', 'Falcon Builder')->orWhere('route', 'admin.falcon-builder.sections');
            })->first();

        if ($fb) {
            $fbId = $fb->id;
        } else {
            $fbId = DB::table('menus')->insertGetId([
                'parent_id'  => null,
                'title'      => 'Falcon Builder',
                'route'      => 'admin.falcon-builder.sections',
                'icon'       => 'view_quilt',
                'group'      => 'Main',
                'order'      => 42,
                'permission' => null,
                'params'     => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Give the freshly-created menu its Layout/Library children too.
            foreach ([
                ['title' => 'Layout',  'route' => 'admin.falcon-builder.sections', 'order' => 1],
                ['title' => 'Library', 'route' => 'admin.falcon-builder.library',  'order' => 2],
            ] as $child) {
                if (!DB::table('menus')->where('parent_id', $fbId)->where('route', $child['route'])->exists()) {
                    DB::table('menus')->insert($child + [
                        'parent_id' => $fbId, 'icon' => null, 'group' => null,
                        'permission' => null, 'params' => null, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        // 3) Move the Customizer item under Falcon Builder (it lived under Appearance before).
        $moved = DB::table('menus')->where('route', 'admin.customizer.index')->update([
            'parent_id'  => $fbId,
            'order'      => 5,
            'updated_at' => now(),
        ]);

        // If no Customizer row existed at all, create one under Falcon Builder.
        if ($moved === 0 && !DB::table('menus')->where('route', 'admin.customizer.index')->exists()) {
            DB::table('menus')->insert([
                'parent_id'  => $fbId,
                'title'      => 'Customizer',
                'route'      => 'admin.customizer.index',
                'icon'       => null,
                'group'      => null,
                'order'      => 5,
                'permission' => null,
                'params'     => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('menus')) return;

        // Best-effort: put Customizer back under Appearance.
        $appearance = DB::table('menus')->whereNull('parent_id')->where('title', 'Appearance')->first();
        if ($appearance) {
            DB::table('menus')->where('route', 'admin.customizer.index')->update([
                'parent_id'  => $appearance->id,
                'order'      => 2,
                'updated_at' => now(),
            ]);
        }
    }
};
