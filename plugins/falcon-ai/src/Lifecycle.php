<?php

namespace FalconAi;

use FalconAi\Support\Settings;
use FalconCms\Core\Models\Permission;
use Illuminate\Support\Facades\Schema;

class Lifecycle
{
    /** Also done by the plugin's migration, for a site where the plugin is on by default. */
    public function activate(): void
    {
        self::ensurePermission();
    }

    public function uninstall(): void
    {
        Permission::where('slug', Settings::PERMISSION)->delete();
        Settings::forget();
    }

    public static function ensurePermission(): void
    {
        if (Schema::hasTable('permissions')) {
            Permission::updateOrCreate(
                ['slug' => Settings::PERMISSION],
                ['name' => 'Manage AI', 'description' => 'Choose the AI provider, save its API keys and see what AI writing has used']
            );
        }
    }
}
