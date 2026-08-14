<?php

use FalconCms\Core\Models\Menu;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Falcon Builder is now fully managed by MenuSeeder — no-op here to avoid
        // creating duplicate rows on fresh installs that run migrate then lazy:install.
    }

    public function down(): void
    {
        Menu::where('title', 'Falcon Builder')
            ->where('route', 'admin.falcon-builder.sections')
            ->delete();
    }
};
