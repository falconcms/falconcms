<?php

namespace FalconCms\Core\Database\Seeders;

use App\Models\User;
use FalconCms\Core\Models\Post;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seeds a sample blog post and a Blog page, and asks the shop plugin (if on) for its
 * storefront pages. Idempotent (firstOrCreate) so it is
 * safe to run on every seed/update. Runs via `falcon:seed` and `falcon:install`.
 */
class DefaultContentSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = optional(User::first())->id ?? 1;

        // ── Auth theme defaults (always modern) ──────────────────────────────
        foreach (['login_theme' => 'modern', 'registration_theme' => 'modern'] as $key => $value) {
            DB::table('cms_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        }

        // ── Default timezone — only set if not already configured ────────────
        if (!DB::table('cms_settings')->where('key', 'timezone')->exists()) {
            DB::table('cms_settings')->insert(['key' => 'timezone', 'value' => 'Asia/Dhaka', 'created_at' => now(), 'updated_at' => now()]);
        }

        // ── Storefront pages: made by the shop plugin, only while it is on ─────
        // (Shop, Cart, Checkout, Account, Wishlist — see FalconShop\StorefrontPages.) Once only:
        // a page the owner deletes is not brought back on the next seed or update.
        try {
            do_falcon_action('falcon_install_defaults');
        } catch (\Throwable $e) {
            Log::warning('DefaultContentSeeder [storefront]: '.$e->getMessage());
        }

        // ── Sample blog post so the blog listing has content right after install ──
        try {
            Post::withoutGlobalScopes()->firstOrCreate(
                ['slug' => 'hello-world', 'type' => 'post', 'lang_code' => 'en'],
                [
                    'title' => 'Hello World — Welcome to Falcon CMS',
                    'status' => 'published',
                    'lang_code' => 'en',
                    'user_id' => $adminId,
                    'editor_type' => 'rich',
                    'excerpt' => 'Welcome to Falcon CMS! This is your first sample blog post — edit or delete it and start publishing your own stories.',
                    'content' => '<p>Welcome to <strong>Falcon CMS</strong> 🎉</p>'
                        .'<p>This is a sample blog post that was created automatically when you installed the CMS. '
                        .'You can edit it, delete it, or use it as a reference for how your posts will look on the front-end.</p>'
                        .'<h2>Getting started</h2>'
                        .'<ul><li>Create new posts from <em>Dashboard → Posts</em>.</li>'
                        .'<li>Customize colours, typography and the blog layout from <em>Appearance → Customize</em>.</li>'
                        .'<li>Assign a page as your Blog page from <em>Settings → General</em>.</li></ul>'
                        .'<p>Happy publishing!</p>',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('DefaultContentSeeder [hello-world]: '.$e->getMessage());
        }

        // ── A ready-to-use "Blog" page ───────────────────────────────────────
        try {
            Post::withoutGlobalScopes()->firstOrCreate(
                ['slug' => 'blog', 'type' => 'page', 'lang_code' => 'en'],
                [
                    'title' => 'Blog',
                    'status' => 'published',
                    'lang_code' => 'en',
                    'user_id' => $adminId,
                    'editor_type' => 'rich',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('DefaultContentSeeder [blog]: '.$e->getMessage());
        }
    }
}
