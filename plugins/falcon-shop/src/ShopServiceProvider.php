<?php

namespace FalconShop;

use Illuminate\Support\ServiceProvider;

/**
 * The shop plugin's entry point, registered only while the plugin is active.
 *
 * Hooks, view composers and bindings belong in boot() here rather than in plugin.php: a
 * provider runs on every application boot, while plugin.php is required once per process,
 * so anything registered there would be lost to a long-running worker or a test that boots
 * the application again.
 */
class ShopServiceProvider extends ServiceProvider
{
    /** Classes that lived in the CMS core before they moved here, by their old name. */
    private const MOVED_CLASSES = [
        'FalconCms\\Core\\Http\\Controllers\\ShopFrontendController' => Http\Controllers\ShopFrontendController::class,
        'FalconCms\\Core\\Http\\Controllers\\WishlistController' => Http\Controllers\WishlistController::class,
        'FalconCms\\Core\\Http\\Middleware\\PersistCart' => Http\Middleware\PersistCart::class,
        'FalconCms\\Core\\Http\\Controllers\\Admin\\ShopController' => Http\Controllers\Admin\ShopController::class,
        'FalconCms\\Core\\Http\\Controllers\\Admin\\ProductCategoryController' => Http\Controllers\Admin\ProductCategoryController::class,
        'FalconCms\\Core\\Http\\Controllers\\Admin\\ProductTagController' => Http\Controllers\Admin\ProductTagController::class,
        'FalconCms\\Core\\Http\\Controllers\\Admin\\PromotionController' => Http\Controllers\Admin\PromotionController::class,
        'FalconCms\\Core\\Http\\Controllers\\Admin\\ShopReportController' => Http\Controllers\Admin\ShopReportController::class,
        'FalconCms\\Core\\Http\\Controllers\\Admin\\ProductDownloadController' => Http\Controllers\Admin\ProductDownloadController::class,
        'FalconCms\\Core\\Http\\Controllers\\Admin\\ReviewController' => Http\Controllers\Admin\ReviewController::class,
        'FalconCms\\Core\\Http\\Controllers\\Concerns\\SyncsOrderInventory' => Http\Controllers\Concerns\SyncsOrderInventory::class,
        // also: an order email queued before the move is stored under its old name
        'FalconCms\\Core\\Mail\\OrderNotificationMail' => Mail\OrderNotificationMail::class,
    ];

    public function register(): void
    {
        // Code written against the old names (a site's own routes, say) keeps working.
        foreach (self::MOVED_CLASSES as $old => $new) {
            // trait_exists too: a trait is not a class, and aliasing one twice is fatal on a
            // second boot (a worker or a test run boots the application more than once).
            if (!class_exists($old, false) && !trait_exists($old, false) && !interface_exists($old, false)) {
                class_alias($new, $old);
            }
        }
    }

    public function boot(): void
    {
        // Keeps the basket in a long-lived cookie across browser restarts. Pushed after the
        // CMS's own web middleware, exactly where the CMS used to push it.
        $this->app['router']->pushMiddlewareToGroup('web', Http\Middleware\PersistCart::class);

        $this->registerStorefrontChrome();

        // The pages assigned as Shop, Cart, Checkout and Account render the shop; with the
        // plugin off they are ordinary pages.
        add_falcon_filter('falcon_frontend_page_response', [Http\AssignedShopPages::class, 'respond']);
        // The shop's pages (Shop, Cart, Checkout, Account, Wishlist): made the first time the CMS
        // installs or updates with the shop on. Activating the plugin makes them too (Lifecycle).
        add_falcon_action('falcon_install_defaults', [StorefrontPages::class, 'ensureOnce']);
        // A fresh install may run before the plugin is loaded: the first admin screen catches up.
        add_falcon_action('falcon_admin_menu', [StorefrontPages::class, 'ensureOnce']);
        // Cart, checkout and account are for one visitor, never a search result: noindex, and kept
        // out of the sitemap and the SEO Audit. (The shop listing itself stays indexable.)
        add_falcon_filter('falcon_seo_noindex', function ($noindex, $post = null) {
            $id = (int) (is_object($post) ? ($post->id ?? 0) : 0);

            // The storefront's own screens (/cart, /checkout, order confirmation, wishlist).
            if (!$id && request()->route() && request()->routeIs('shop.*')) {
                return true;
            }

            return $noindex || ($id && in_array($id, array_map('intval', array_filter([
                get_shop_option('shop_cart_page_id'), get_shop_option('shop_checkout_page_id'), get_shop_option('shop_account_page_id'),
                get_shop_option('shop_wishlist_page_id'),
            ])), true));
        });

        // Shop, cart, checkout and account pages are drawn by templates: no content is not "empty".
        add_falcon_filter('falcon_template_page_ids', fn ($ids) => array_merge((array) $ids, array_map('intval', array_filter([
            get_shop_option('shop_shop_page_id'), get_shop_option('shop_cart_page_id'),
            get_shop_option('shop_checkout_page_id'), get_shop_option('shop_account_page_id'),
            get_shop_option('shop_wishlist_page_id'),
        ]))));

        $this->registerProductTemplates();

        // Settings → Site Health: a theme's copy of a shop template that has fallen behind.
        add_falcon_filter('falcon_site_health_checks', [self::class, 'templateHealthCheck']);

        if ($this->app->runningInConsole()) {
            $this->commands([Console\ShopTemplateCommand::class]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     * @return array<int, array<string, mixed>>
     */
    public static function templateHealthCheck($checks): array
    {
        $rows = array_filter(Templates::status(), fn ($row) => $row['override'] !== null);
        $outdated = array_filter($rows, fn ($row) => $row['outdated']);
        $base = str_replace('\\', '/', base_path()).'/';
        $describe = fn ($row) => str_replace($base, '', str_replace('\\', '/', $row['override']['file']))
            .' — '.($row['override_version'] ? 'version '.$row['override_version'] : 'no version').', shop has '.$row['version'];

        $checks = is_array($checks) ? $checks : [];
        $checks[] = $outdated === []
            ? [
                'id' => 'shop_templates', 'label' => 'Shop templates are up to date', 'status' => 'good', 'category' => 'Plugins',
                'description' => $rows === []
                    ? 'The theme uses the shop\'s own templates.'
                    : 'The theme overrides '.count($rows).' shop template(s), all current with the shop.',
            ]
            : [
                'id' => 'shop_templates', 'label' => 'The theme has outdated copies of shop templates', 'status' => 'recommended', 'category' => 'Plugins',
                'description' => 'These copies were made from an older version of the shop template and may miss fields or fixes it has now. Compare each with the shop\'s current one and bring your changes across.',
                'action' => 'Run php artisan shop:template to see them; php artisan shop:template <name> --force replaces a copy with the current one.',
                'details' => ['files' => array_values(array_map($describe, $outdated))],
            ];

        return $checks;
    }

    /** Products render the shop's templates — the theme's override, else the plugin's default. */
    private function registerProductTemplates(): void
    {
        add_falcon_filter('falcon_single_view', function ($view, $post) {
            if ($view !== null || $post->type !== 'product') {
                return $view;
            }
            // The "variable" flag lives in either column, depending on how the product was saved.
            $sd = $post->shopData;
            $variable = $sd && (($sd->type ?? null) === 'variable' || ($sd->product_type ?? null) === 'variable');

            return Templates::view($variable ? 'single-product-variable' : 'single-product');
        });

        add_falcon_filter('falcon_archive_view', function ($view, $type) {
            return $view ?? ($type === 'product' ? Templates::view('archive-product') : null);
        });
    }

    /**
     * The bits of the shop every storefront page carries: the header cart icon and the
     * off-canvas mini-cart. A theme offers the places (falcon_header_actions,
     * falcon_after_footer); with the plugin off, nobody fills them and none of it — markup,
     * script or request — reaches the page.
     */
    private function registerStorefrontChrome(): void
    {
        add_falcon_action('falcon_header_actions', function () {
            echo view('falcon-shop::frontend.partials.header-cart')->render();
        });

        $miniCart = function () {
            // Once per page: falcon-theme fires falcon_after_footer (before its icon script),
            // a theme without that hook gets it at falcon_footer instead.
            $request = request();
            if ($request->attributes->get('falcon_shop.mini_cart')) {
                return;
            }
            $request->attributes->set('falcon_shop.mini_cart', true);
            echo view('falcon-shop::frontend.partials.mini-cart')->render();
        };
        add_falcon_action('falcon_after_footer', $miniCart);
        add_falcon_action('falcon_footer', $miniCart);
    }
}
