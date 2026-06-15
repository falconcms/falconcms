<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use FalconCms\Core\Models\Menu;

return new class extends Migration
{
    public function up(): void
    {
        // Lazy Builder is now fully managed by MenuSeeder — no-op here to avoid
        // creating duplicate rows on fresh installs that run migrate then lazy:install.
    }

    public function down(): void
    {
        Menu::where('title', 'Lazy Builder')
            ->where('route', 'admin.lazy-builder.sections')
            ->delete();
    }
};
