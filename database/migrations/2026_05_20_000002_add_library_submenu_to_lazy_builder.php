<?php

use FalconCms\Core\Models\Menu;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Falcon Builder submenus (Sections, Header Builder, Footer Builder, Library)
        // are now fully managed by MenuSeeder — no-op here.
    }

    public function down(): void
    {
        Menu::where('title', 'Library')->where('route', 'admin.falcon-builder.library')->delete();
        Menu::where('title', 'Sections')->where('route', 'admin.falcon-builder.sections')
            ->whereNotNull('parent_id')->delete();
    }
};
