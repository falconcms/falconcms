<?php

namespace FalconCms\Core\Database\Seeders;

use App\Models\User;
use FalconCms\Core\Models\Post;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seeds the default storefront pages (Shop, Cart, Checkout, Account), a sample blog post
 * and a Blog page, and links the shop page settings. Idempotent (firstOrCreate) so it is
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

        // ── Storefront pages (+ link them in shop settings) ──────────────────
        $pages = [
            ['title' => 'Shop',     'slug' => 'product',  'setting' => 'shop_shop_page_id'],
            ['title' => 'Cart',     'slug' => 'cart',     'setting' => 'shop_cart_page_id'],
            ['title' => 'Checkout', 'slug' => 'checkout', 'setting' => 'shop_checkout_page_id'],
            ['title' => 'Account',  'slug' => 'account',  'setting' => 'shop_account_page_id'],
        ];

        foreach ($pages as $p) {
            // Match on the FULL unique key (slug + type + lang_code) and drop global
            // scopes — SoftDeletes hides a trashed page from the lookup while the unique
            // index still counts it, so a plain firstOrCreate would hit the constraint and
            // fatal the boot-seeder (container crash-loop). Restore a trashed storefront
            // page so the shop always has a live page. try/catch never fails the seed.
            try {
                $page = Post::withoutGlobalScopes()->firstOrCreate(
                    ['slug' => $p['slug'], 'type' => 'page', 'lang_code' => 'en'],
                    [
                        'title' => $p['title'],
                        'status' => 'published',
                        'user_id' => $adminId,
                        'editor_type' => 'rich',
                    ]
                );
                if ($page && method_exists($page, 'trashed') && $page->trashed()) {
                    $page->restore();
                }
            } catch (\Throwable $e) {
                Log::warning('DefaultContentSeeder ['.$p['slug'].']: '.$e->getMessage());
                $page = Post::withoutGlobalScopes()
                    ->where(['slug' => $p['slug'], 'type' => 'page', 'lang_code' => 'en'])
                    ->first();
            }

            if ($page && !DB::table('cms_settings')->where('key', $p['setting'])->exists()) {
                DB::table('cms_settings')->insert([
                    'key' => $p['setting'],
                    'value' => $page->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
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
