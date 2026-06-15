<?php

use Illuminate\Database\Migrations\Migration;
use FalconCms\Core\Models\Menu;

return new class extends Migration
{
    public function up(): void
    {
        // Lazy Builder submenus (Sections, Header Builder, Footer Builder, Library)
        // are now fully managed by MenuSeeder — no-op here.
    }

    public function down(): void
    {
        Menu::where('title', 'Library')->where('route', 'admin.lazy-builder.library')->delete();
        Menu::where('title', 'Sections')->where('route', 'admin.lazy-builder.sections')
            ->whereNotNull('parent_id')->delete();
    }
};
