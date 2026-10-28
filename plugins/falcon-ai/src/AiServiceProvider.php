<?php

namespace FalconAi;

use FalconAi\Support\Access;
use FalconAi\Support\AiManager;
use FalconAi\Support\SeoWriter;
use FalconAi\Support\Settings;
use FalconAi\Support\Usage;
use Illuminate\Support\ServiceProvider;

/**
 * Registered only while the plugin is active. Hooks live here rather than in plugin.php,
 * because a provider runs on every application boot and plugin.php only once per process.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One per request, so the provider list (and its filter) is built once.
        $this->app->scoped(AiManager::class);
    }

    public function boot(): void
    {
        // routes/admin.php is loaded by the CMS, by convention.
        add_falcon_action('falcon_admin_menu', [self::class, 'menu']);

        // The writing endpoints belong to no menu: decided here (and again in each request).
        add_falcon_filter('falcon_admin_path_access', static function ($decided, $path = '', $user = null) {
            if ($decided !== null || !preg_match('#^admin/ai/(write/[a-z]+|seo/suggest)$#', (string) $path)) {
                return $decided;
            }

            return Access::canWrite($user);
        });

        // The editor: Write with AI beside the editor tabs, and suggestions in Falcon SEO's box.
        add_falcon_action('falcon_admin_post_editor_tabs', [self::class, 'editor']);

        // Falcon SEO asks for its AI writing through these filters.
        add_falcon_filter('falcon_seo_ai_available', [SeoWriter::class, 'available']);
        add_falcon_filter('falcon_seo_ai_suggestions', [SeoWriter::class, 'suggestionsFilter']);
        add_falcon_filter('falcon_seo_ai_alt_texts', [SeoWriter::class, 'altTextsFilter']);
    }

    public static function menu(): void
    {
        falcon_add_menu_page([
            'slug' => 'ai',
            'menu_title' => 'AI',
            'route' => 'admin.ai.settings',
            'capability' => Settings::PERMISSION,
            'icon' => 'auto_awesome',
            'position' => 75,
            'group' => 'Advanced',
        ]);
        falcon_add_submenu_page('ai', [
            'menu_title' => 'Settings & Usage',
            'route' => 'admin.ai.settings',
            'capability' => Settings::PERMISSION,
            'order' => 1,
        ]);
    }

    /** `falcon_admin_post_editor_tabs` ($post, $type): only for someone allowed to write with AI. */
    public static function editor($post = null, $type = 'post'): void
    {
        $user = auth()->user();
        if (!Access::canWrite($user)) {
            return;
        }

        echo view('falcon-ai::admin.editor', [
            'ready' => app(AiManager::class)->available(),
            'canManage' => Access::canManage($user),
            'usage' => Usage::thisMonth(),
        ])->render();
    }
}
