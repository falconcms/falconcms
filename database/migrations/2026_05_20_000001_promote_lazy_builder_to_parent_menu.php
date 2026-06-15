<?php

use Illuminate\Database\Migrations\Migration;
use FalconCms\Core\Models\Menu;

return new class extends Migration
{
    public function up(): void
    {
        // Falcon Builder promotion is now handled by MenuSeeder — no-op here.
    }

    public function down(): void
    {
        $appearanceMenu = Menu::where('title', 'Appearance')->first();
        $falconBuilder = Menu::where('title', 'Falcon Builder')
            ->where('route', 'admin.falcon-builder.sections')
            ->first();

        if ($falconBuilder && $appearanceMenu) {
            $falconBuilder->update([
                'parent_id' => $appearanceMenu->id,
                'group'     => null,
                'icon'      => null,
                'order'     => 5,
            ]);
        }
    }
};
