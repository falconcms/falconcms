<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use FalconCms\Core\Models\Post;

/**
 * Creates the storefront "Account" page and stores its id in shop settings.
 * Idempotent (firstOrCreate + existence check) and safe on a brand-new install
 * where no user exists yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        $adminId = optional(\App\Models\User::first())->id ?? 1;

        $page = Post::firstOrCreate(
            ['slug' => 'account', 'type' => 'page'],
            [
                'title'       => 'Account',
                'status'      => 'published',
                'lang_code'   => app()->getLocale() ?? 'en',
                'user_id'     => $adminId,
                'editor_type' => 'rich',
            ]
        );

        if ($page && !DB::table('cms_settings')->where('key', 'shop_account_page_id')->exists()) {
            DB::table('cms_settings')->insert([
                'key'        => 'shop_account_page_id',
                'value'      => $page->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No down needed.
    }
};
