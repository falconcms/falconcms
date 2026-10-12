<?php

namespace FalconShop;

use App\Models\User;
use FalconCms\Core\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * The shop's own pages (Shop, Cart, Checkout, Account, Wishlist), each assigned in Shop →
 * Settings → Products → Shop pages.
 *
 * ensure() makes every one of them exist, smartly: the page already assigned is kept (and
 * restored from the trash if it was deleted); with none assigned, a page with the usual
 * address is found and assigned; only then is a new page created. It never creates a
 * second page for a slot that has one, and never touches a page's content.
 *
 * It runs when the plugin is activated, and once on its own (ensureOnce) for a site that had
 * the plugin on from install. After that a page the owner deletes stays deleted until they
 * press "Create missing pages" in the shop settings.
 */
final class StorefrontPages
{
    /** Set once the pages were made the first time, so a page deleted later is not brought back unasked. */
    public const DONE = 'shop_pages_initialized';

    public const PAGES = [
        'shop' => ['option' => 'shop_shop_page_id', 'title' => 'Shop', 'slug' => 'product'],
        'cart' => ['option' => 'shop_cart_page_id', 'title' => 'Cart', 'slug' => 'cart'],
        'checkout' => ['option' => 'shop_checkout_page_id', 'title' => 'Checkout', 'slug' => 'checkout'],
        'account' => ['option' => 'shop_account_page_id', 'title' => 'Account', 'slug' => 'account'],
        'wishlist' => ['option' => 'shop_wishlist_page_id', 'title' => 'Wishlist', 'slug' => 'wishlist'],
    ];

    /** First time only: make the pages unless that was already done. */
    public static function ensureOnce(): void
    {
        try {
            if ((string) get_cms_option(self::DONE, '') === '1') {
                return;
            }
            self::ensure();
            update_cms_option(self::DONE, '1');
        } catch (Throwable $e) {
            Log::warning('Falcon Shop: could not create the shop pages: '.$e->getMessage());
        }
    }

    /**
     * Make every shop page exist and be assigned.
     *
     * @return array<string, string> page key => what was done: ok, published, restored, linked or created
     */
    public static function ensure(): array
    {
        $done = [];
        foreach (self::PAGES as $key => $def) {
            try {
                $done[$key] = self::ensureOne($def);
            } catch (Throwable $e) {
                Log::warning("Falcon Shop: shop page '{$key}': ".$e->getMessage());
                $done[$key] = 'failed';
            }
        }
        forget_cms_options_cache();

        return $done;
    }

    /**
     * Each page's state, for the settings screen.
     *
     * @return array<string, array{title: string, state: string, id: ?int, page_title: ?string, url: ?string, edit: ?string}>
     *                                                                                                                        state: ok, draft, trashed or missing
     */
    public static function status(): array
    {
        $out = [];
        foreach (self::PAGES as $key => $def) {
            $id = (int) get_cms_option($def['option'], 0);
            $page = $id ? Post::withoutGlobalScopes()->where('type', 'page')->find($id) : null;
            $state = !$page ? 'missing' : ($page->trashed() ? 'trashed' : ($page->status === 'published' ? 'ok' : 'draft'));
            $out[$key] = [
                'title' => $def['title'],
                'state' => $state,
                'id' => $page?->id,
                'page_title' => $page?->title,
                'url' => $state === 'ok' ? get_falcon_permalink($page) : null,
                'edit' => $page && !$page->trashed() && Route::has('admin.pages.edit') ? route('admin.pages.edit', $page->id) : null,
            ];
        }

        return $out;
    }

    /** True when any page needs attention (deleted, unpublished or never assigned). */
    public static function needsAttention(): bool
    {
        return collect(self::status())->contains(fn ($p) => $p['state'] !== 'ok');
    }

    private static function ensureOne(array $def): string
    {
        $id = (int) get_cms_option($def['option'], 0);
        $page = $id ? Post::withoutGlobalScopes()->where('type', 'page')->find($id) : null;

        // Nothing assigned (or the assigned page is gone for good): look for one at the usual address.
        $how = 'ok';
        if (!$page) {
            $page = Post::withoutGlobalScopes()->where('type', 'page')
                ->where('slug', $def['slug'])->where('lang_code', self::language())->first();
            $how = 'linked';
        }

        if (!$page) {
            $page = Post::withoutGlobalScopes()->forceCreate([
                'title' => $def['title'],
                'slug' => $def['slug'],
                'type' => 'page',
                'status' => 'published',
                'lang_code' => self::language(),
                'user_id' => self::authorId(),
                'editor_type' => 'rich',
                'content' => '',
            ]);
            $how = 'created';
        } elseif ($page->trashed()) {
            $page->restore();
            $page->status = 'published';
            $page->save();
            $how = 'restored';
        } elseif ($page->status !== 'published' && $how === 'linked') {
            // A draft found by address only becomes the shop's page once it is live.
            $page->status = 'published';
            $page->save();
        }

        if ((int) get_cms_option($def['option'], 0) !== (int) $page->id) {
            DB::table('cms_settings')->updateOrInsert(['key' => $def['option']], ['value' => (string) $page->id, 'updated_at' => now()]);
        }

        return $how;
    }

    private static function language(): string
    {
        return function_exists('falcon_default_language') ? (string) falcon_default_language() : 'en';
    }

    private static function authorId(): int
    {
        try {
            return (int) (auth()->id() ?: (User::query()->orderBy('id')->value('id') ?? 1));
        } catch (Throwable $e) {
            return 1;
        }
    }
}
