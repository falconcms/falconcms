<?php

namespace FalconCms\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Scaffold a new drop-in plugin: manifest, bootstrap, and a PSR-4 lifecycle
 * class — a working skeleton to build on.
 */
class MakePlugin extends Command
{
    protected $signature = 'make:plugin {name : Plugin display name}';

    protected $description = 'Scaffold a new plugin in the plugins/ directory';

    public function handle(): int
    {
        $name = trim($this->argument('name'));
        $slug = Str::slug($name);
        if ($slug === '') {
            $this->error('Invalid plugin name.');
            return self::FAILURE;
        }

        $dir = base_path('plugins/' . $slug);
        if (File::isDirectory($dir)) {
            $this->error("Plugin '{$slug}' already exists at {$dir}.");
            return self::FAILURE;
        }

        // StudlyCase namespace from the slug (e.g. "seo-booster" → "SeoBooster").
        $namespace = Str::studly($slug);

        File::ensureDirectoryExists($dir . '/src');

        File::put($dir . '/plugin.json', json_encode([
            'name'         => $name,
            'slug'         => $slug,
            'version'      => '1.0.0',
            'description'  => '',
            'author'       => '',
            'requires_php' => '>=8.0',
            'requires_cms' => '>=1.0',
            'namespace'    => $namespace . '\\',
            'lifecycle'    => $namespace . '\\Lifecycle',
            'dependencies' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        File::put($dir . '/plugin.php', <<<PHP
        <?php

        /**
         * {$name} — bootstrap file (loaded while the plugin is active).
         * Use the CMS hook / menu / settings APIs here.
         */

        add_falcon_action('falcon_register_settings', function () {
            // falcon_add_settings_field([
            //     'id'    => '{$slug}_example',
            //     'label' => 'Example',
            //     'type'  => 'text',
            // ]);
        });

        PHP);

        File::put($dir . '/src/Lifecycle.php', <<<PHP
        <?php

        namespace {$namespace};

        /**
         * Lifecycle hooks — called on activate / deactivate / uninstall / upgrade.
         * All methods are optional; delete the ones you don't need.
         */
        class Lifecycle
        {
            public function activate(): void
            {
                // Runs once when the plugin is activated.
            }

            public function deactivate(): void
            {
                // Runs when the plugin is deactivated.
            }

            public function uninstall(): void
            {
                // Clean up (drop tables, delete options) when the plugin is removed.
            }

            public function upgrade(?string \$previousVersion): void
            {
                // Migrate data when a newer version is installed over an older one.
            }
        }

        PHP);

        $this->info("Plugin scaffolded at: {$dir}");
        $this->line("Activate it with:  php artisan plugin:activate {$slug}");

        return self::SUCCESS;
    }
}
