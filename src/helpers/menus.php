<?php

/**
 * Navigation menus: loading, active state, anchors and special items.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\CustomTaxonomy;
use FalconCms\Core\Models\NavigationMenu;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Models\TaxonomyTerm;
use FalconCms\Core\Models\Wishlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

if (!function_exists('falcon_same_page')) {
    /**
     * Do two URLs point at the same page?
     *
     * Comparing them as whole strings almost never says yes. What an editor pastes into
     * a menu is whatever they had on the clipboard — absolute one day, relative the
     * next, with or without a trailing slash or a fragment — while the page being
     * rendered always knows its own, fully qualified. "/docs" is not
     * "https://example.com/docs/", so the Home item was never marked active on the home
     * page and a Previous/Next list could never find where it was.
     *
     * Paths are what was meant. A link to another host is a link off the site, so it is
     * never the page you are on however its path reads.
     */
    function falcon_same_page(?string $a, ?string $b): bool
    {
        $a = trim((string) $a);
        $b = trim((string) $b);

        if ($a === '' || $b === '' || $a === '#') {
            return false;
        }

        $hostA = parse_url($a, PHP_URL_HOST);
        $hostB = parse_url($b, PHP_URL_HOST);

        // Only compared when both sides name a host. One of them being relative means it
        // is on this site, which is the same thing the other one is saying.
        if ($hostA && $hostB && strcasecmp((string) $hostA, (string) $hostB) !== 0) {
            return false;
        }

        // A URL with no path at all means one of two opposite things, and reading both as
        // "/" is what marked every custom menu item active at once: an item saved as
        // "#pricing" or "?tab=2" collapsed to the site root, so on the home page it matched.
        // Those are a fragment or a query on the page you are already on, not a page of
        // their own. A bare host — "https://example.test" — really is the site root.
        // '' is returned for the first case and never matches anything, including itself.
        $path = static function (string $url): string {
            $p = (string) parse_url($url, PHP_URL_PATH);

            if ($p === '') {
                return parse_url($url, PHP_URL_HOST) || parse_url($url, PHP_URL_SCHEME) ? '/' : '';
            }

            // "about" and "/about" name the same page; only one of them is what the editor
            // happened to type.
            $p = '/'.ltrim($p, '/');

            return rtrim($p, '/') === '' ? '/' : rtrim($p, '/');
        };

        $pathA = $path($a);
        $pathB = $path($b);

        if ($pathA === '' || $pathB === '') {
            return false;
        }

        return $pathA === $pathB;
    }
}

if (!function_exists('falcon_anchor_url')) {
    /**
     * Put a slash before the fragment of a link that has one.
     *
     * `/pricing#plans` and `/pricing/#plans` reach exactly the same place, so this is
     * about the address bar rather than the navigation: a reader who clicks a section
     * link and then copies what is in the bar should get back a URL shaped like the
     * rest of the site's, and a site that serves its pages with a trailing slash looks
     * inconsistent the moment an anchor is involved. Search engines and analytics treat
     * the two spellings as different pages, which is the part that actually costs
     * something.
     *
     * A fragment with nothing before it — the `#plans` a menu item is usually saved as —
     * is resolved against the page it is being rendered on, which is where it was
     * already going. So the link behaves identically and only reads better.
     *
     * Left alone: anything with no fragment at all, and a bare `#`, which is not a link
     * to anywhere but the placeholder for an item that has no link.
     */
    function falcon_anchor_url(?string $url, ?string $currentPath = null): string
    {
        $url = trim((string) $url);

        if ($url === '' || $url === '#' || !str_contains($url, '#')) {
            return $url;
        }

        [$before, $fragment] = explode('#', $url, 2);

        // The query belongs after the path, so the slash goes before it, not at the end.
        $query = '';
        if (($q = strpos($before, '?')) !== false) {
            $query = substr($before, $q);
            $before = substr($before, 0, $q);
        }

        if ($before === '') {
            $before = $currentPath ?? (function () {
                try {
                    return request()->getPathInfo();
                } catch (Throwable $e) {
                    return '/';
                }
            })();
            $before = '/'.ltrim((string) $before, '/');
        }

        if (!str_ends_with($before, '/')) {
            $before .= '/';
        }

        return $before.$query.'#'.$fragment;
    }
}

if (!function_exists('falcon_menu_is_active')) {
    /**
     * Is this menu item's URL the page currently being viewed?
     *
     * Menu URLs are saved in every shape a person might type: "/about", "/about/",
     * a full "https://site.test/about", a bare "/" for Home, an anchor-only
     * "#plans", or a link off to another site. This normalises them all down to a
     * path and compares that against the request.
     *
     *  - Trailing slashes do not matter: "/about/" matches /about.
     *  - "/" matches the home page (which the request reports as "/", not "").
     *  - An anchor- or query-only item — "#plans", "?tab=2" — never matches: it is a
     *    place on the page you are already reading, not a page.
     *  - A bare host — "https://site.test" — is the home page, and matches there.
     *  - A link to another host never matches, so an external "https://x.test/"
     *    cannot light up on our own home page.
     *
     * The comparison itself is {@see falcon_same_page()}, which the Layout builder's Menu
     * element also uses. They answered this question separately once and disagreed about
     * anchor-only items, so the same menu highlighted differently depending on whether the
     * header came from the theme or from the builder.
     */
    function falcon_menu_is_active(?string $url): bool
    {
        try {
            return falcon_same_page($url, url()->current());
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_menu_branch_is_active')) {
    /**
     * Is this menu item, or anything nested under it, the page being viewed?
     *
     * {@see falcon_menu_is_active()} answers only for the item's own URL, so a parent
     * whose child is the current page stayed unhighlighted and the reader lost track of
     * which section they were in — the submenu entry lit up, the top-level entry above it
     * did not. Whole branches are marked instead: the item on the current page and every
     * ancestor of it.
     *
     * Two callers with two shapes. The theme header holds Eloquent rows that expose their
     * own `children`, which the default reader below walks on its own. The builder's Menu
     * element holds one flat list plus a collection grouped by parent id, so it passes
     * $childrenOf to look the next level up. Arrays work too, via a `children` key.
     *
     * Depth is capped rather than trusted: menu rows carry a parent_id an editor can point
     * anywhere, and a row that ends up its own ancestor would otherwise recurse until the
     * request died.
     */
    function falcon_menu_branch_is_active(?string $url, $children = [], ?callable $childrenOf = null, int $depth = 0): bool
    {
        if (falcon_menu_is_active($url)) {
            return true;
        }
        if ($depth >= 10 || empty($children)) {
            return false;
        }

        foreach ($children as $child) {
            $childUrl = is_array($child) ? ($child['url'] ?? null) : ($child->url ?? null);
            $grandChildren = $childrenOf
                ? $childrenOf($child)
                : (is_array($child) ? ($child['children'] ?? []) : ($child->children ?? []));

            if (falcon_menu_branch_is_active($childUrl, $grandChildren ?: [], $childrenOf, $depth + 1)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('falcon_nav_menu_version')) {
    /**
     * Version token embedded in every cached nav-menu key. Bumping it (via
     * forget_nav_menu_cache) instantly invalidates all cached menus across every
     * location and locale, without needing wildcard cache deletes.
     */
    function falcon_nav_menu_version(): string
    {
        try {
            return (string) Cache::rememberForever(
                'falcon:nav_menu_ver',
                fn () => uniqid('', true)
            );
        } catch (Throwable $e) {
            return '0';
        }
    }
}

if (!function_exists('forget_nav_menu_cache')) {
    /** Invalidate every cached nav menu. Call after any menu / CPT / taxonomy edit. */
    function forget_nav_menu_cache(): void
    {
        try {
            Cache::forever('falcon:nav_menu_ver', uniqid('', true));
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('get_falcon_menu')) {
    function get_falcon_menu($slugOrLocation)
    {
        // Nav menus resolve on every frontend page (header + footer) and each fans
        // out into many queries (per-item post/term lookups + permalinks). Cache the
        // resolved tree per location+locale; the version token lets any menu/CPT/
        // taxonomy edit invalidate all of them at once, with a 10-min TTL backstop.
        // Cache a PURE ARRAY tree (no Eloquent/objects — those don't round-trip
        // through every cache store reliably), then hydrate to stdClass on the way
        // out so the theme keeps its object property access unchanged.
        try {
            // The bases are in the key because term URLs are derived from them now, and a
            // settings change is not a menu edit — nothing would have bumped the version.
            $key = 'falcon:nav_menu:'.falcon_nav_menu_version().':'.$slugOrLocation.':'.app()->getLocale()
                .':'.falcon_taxonomy_base('category').':'.falcon_taxonomy_base('tag');
            $tree = Cache::remember(
                $key,
                now()->addMinutes(10),
                fn () => _falcon_menu_items_to_array(_falcon_resolve_menu($slugOrLocation))
            );
        } catch (Throwable $e) {
            $tree = _falcon_menu_items_to_array(_falcon_resolve_menu($slugOrLocation));
        }

        return _falcon_menu_array_to_objects($tree);
    }
}

if (!function_exists('_falcon_menu_items_to_array')) {
    /** Resolved menu items -> plain nested arrays (cache-safe). */
    function _falcon_menu_items_to_array($items): array
    {
        return collect($items)->map(function ($item) {
            $data = method_exists($item, 'getAttributes') ? $item->getAttributes() : (array) $item;
            $url = $item->url ?? ($data['url'] ?? '#');

            // Internal links (page/post/cpt/category) are cached as ROOT-RELATIVE paths
            // so the browser resolves them against whatever domain it's on — the cached
            // value must never freeze the host that happened to populate it. Only
            // user-entered 'custom' items keep their URL verbatim (may be external).
            if (($data['type'] ?? '') !== 'custom' && preg_match('#^https?://#i', $url)) {
                $p = parse_url($url);
                $url = ($p['path'] ?? '/')
                    .(isset($p['query']) ? '?'.$p['query'] : '')
                    .(isset($p['fragment']) ? '#'.$p['fragment'] : '');
            }

            $data['url'] = $url;
            $children = $item->children ?? null;
            $data['children'] = ($children && count($children))
                ? _falcon_menu_items_to_array($children)
                : [];

            return $data;
        })->values()->all();
    }
}

if (!function_exists('_falcon_menu_array_to_objects')) {
    /** Cached array tree -> Collection of stdClass (children as nested Collections). */
    function _falcon_menu_array_to_objects($tree)
    {
        return collect($tree)->map(function ($data) {
            $data = (array) $data;
            $children = $data['children'] ?? [];
            $obj = (object) $data;
            $obj->children = _falcon_menu_array_to_objects($children);

            return $obj;
        })->values();
    }
}

if (!function_exists('_falcon_resolve_menu')) {
    function _falcon_resolve_menu($slugOrLocation)
    {
        $query = NavigationMenu::query();

        if ($slugOrLocation === 'header') {
            $query->where('is_header', true);
        } elseif ($slugOrLocation === 'footer') {
            $query->where('is_footer', true);
        } else {
            $query->where('slug', $slugOrLocation);
        }

        $currentLocale = app()->getLocale();

        // Try to find menu with exact slug-locale if it's a slug
        if (!in_array($slugOrLocation, ['header', 'footer'])) {
            $langSlug = $slugOrLocation.'-'.$currentLocale;
            $menu = (clone $query)->where('slug', $langSlug)->first();
            if ($menu) {
                return this_process_items($menu);
            }
        }

        // Try to find by location AND lang_code
        $menu = (clone $query)->where('lang_code', $currentLocale)->first();

        if (!$menu) {
            // Fallback to location only without lang_code
            $menu = (clone $query)->whereNull('lang_code')->first();
        }

        if (!$menu) {
            return collect();
        }

        return this_process_items($menu);
    }
}

// Internal helper for menu processing (moved logic out of the main function for reuse)
if (!function_exists('this_process_items')) {
    function this_process_items($menu)
    {
        // Fetch active CPTs and Taxonomies to filter items
        $activePostTypes = PostType::where('is_active', true)->pluck('slug')->toArray();
        $activeTaxonomies = CustomTaxonomy::where('is_active', true)->pluck('slug')->toArray();

        // Built-in types are always active
        $activePostTypes[] = 'post';
        $activePostTypes[] = 'page';
        $activePostTypes[] = 'category'; // Default category
        $activePostTypes[] = 'custom';   // Custom links

        $items = $menu->items->filter(function ($item) use ($activePostTypes, $activeTaxonomies) {
            // If it's a post/page/cpt item
            if (!in_array($item->type, ['category', 'custom'])) {
                return in_array($item->type, $activePostTypes);
            }
            // If it's a category/taxonomy item
            if ($item->type === 'category' && $item->object_id) {
                $term = TaxonomyTerm::find($item->object_id);
                if ($term) {
                    return in_array($term->taxonomy_slug, $activeTaxonomies);
                }
                $standardCat = Category::find($item->object_id);

                return (bool) $standardCat;
            }

            return true;
        });

        $cleanItems = function ($items) use (&$cleanItems) {
            return $items->map(function ($item) use ($cleanItems) {
                $currentLocale = app()->getLocale();

                // If it's a post/page/cpt item, find translation
                if (!in_array($item->type, ['category', 'custom']) && $item->object_id) {
                    $post = Post::find($item->object_id);
                    if ($post) {
                        // Find translation in current locale
                        if ($post->lang_code !== $currentLocale) {
                            $translation = $post->getTranslation($currentLocale);
                            if ($translation) {
                                $post = $translation;
                            }
                        }
                        $item->url = get_falcon_permalink($post);
                    }
                }

                // A term item is worked out the same way, and used not to be: the address
                // saved with the item was served forever, so renaming the term's slug — or
                // changing the archive base — left the menu pointing at the old one. The
                // link still arrived, because a rename leaves a redirect, but it arrived the
                // long way round and never matched the page it was on, so the item never
                // showed as current. Only rewritten when the term is actually found; a stored
                // URL is better than a guess.
                if ($item->type === 'category' && $item->object_id) {
                    $term = TaxonomyTerm::find($item->object_id);
                    if ($term) {
                        $item->url = get_falcon_term_link($term->slug, $term->taxonomy_slug);
                    } elseif ($category = Category::find($item->object_id)) {
                        $item->url = get_falcon_term_link($category);
                    }
                }

                // Recursively clean children
                if ($item->children && $item->children->count() > 0) {
                    $item->setRelation('children', $cleanItems($item->children));
                }

                return $item;
            });
        };

        return $cleanItems($items);
    }
}

if (!function_exists('falcon_is_special_menu_item')) {
    /** True when a menu item is one of the Lazy Special Menu widgets. */
    function falcon_is_special_menu_item($type): bool
    {
        return in_array($type, ['special_cart', 'special_search', 'special_wishlist'], true);
    }
}

if (!function_exists('falcon_render_special_menu_item')) {
    /**
     * Render a Lazy Special Menu widget (Cart / Search / Wishlist) inside a navigation menu.
     * Returns a full <li>…</li> string. Reuses existing helpers + the global mini-cart drawer.
     *
     * @param  object  $item  navigation_menu_items row (stdClass)
     * @param  string  $style  inline link style inherited from the menu element
     */
    function falcon_render_special_menu_item($item, string $style = '', bool $isMobile = false, $elId = ''): string
    {
        $type = $item->type ?? '';
        $label = $item->title ?? '';

        $icons = [
            'special_cart' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
            'special_search' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
            'special_wishlist' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>',
        ];
        // The Menu Item Options modal lets an editor pick a FontAwesome icon for every item,
        // special ones included — but this always drew its own fixed SVG regardless, so a
        // chosen icon was saved (and shown correctly on the item's row in that modal) and then
        // silently ignored the moment the menu actually rendered. The fallback SVG is only for
        // an item nobody has picked an icon for, matching how it looked before this existed.
        $icon = !empty($item->icon) ? '<i class="'.e($item->icon).'"></i>' : ($icons[$type] ?? '');

        // Count-badge appearance (NO display property here — Tailwind classes control show/hide,
        // matching the header badge so LazyCart's `hidden`-class toggle keeps working).
        //
        // Background follows the Customizer's Primary Color — the same setting the theme
        // header's own cart badge uses (there via the `bg-primary` Tailwind class, which reads
        // the identical option through the theme's runtime Tailwind config). Read directly
        // rather than assuming a `--primary` CSS variable or Tailwind config is in scope: this
        // HTML can end up wherever the menu element is placed, not only inside the theme's own
        // header markup.
        $badgeColor = get_cms_option('theme_primary_color', '#0091ea');
        $badgeStyle = 'min-width:18px;height:18px;padding:0 5px;margin-left:6px;font-size:11px;font-weight:700;line-height:1;color:#fff;background:'.e($badgeColor).';border-radius:9999px;';
        $badgeCls = 'inline-flex items-center justify-center';
        $iconWrap = 'display:inline-flex;align-items:center;gap:2px;';

        if ($type === 'special_cart') {
            $count = function_exists('get_falcon_cart_count') ? (int) get_falcon_cart_count() : 0;
            $cartUrl = Route::has('shop.cart') ? route('shop.cart') : url('/cart');
            $badge = '<span class="cart-count-badge '.$badgeCls.($count > 0 ? '' : ' hidden').'" style="'.$badgeStyle.'">'.$count.'</span>';

            return '<li class="falcon-menu-item lazy-special-item lazy-special-cart">'
                 .'<a href="'.e($cartUrl).'" class="falcon-menu-link lazy-special-link" style="'.$style.$iconWrap.'" '
                 .'onclick="if(window.LazyCart){LazyCart.open();return false;}" aria-label="'.e($label ?: 'Cart').'">'
                 .$icon.$badge
                 .'</a></li>';
        }

        if ($type === 'special_wishlist') {
            $count = function_exists('falcon_wishlist_count') ? (int) falcon_wishlist_count() : 0;
            $wishUrl = Route::has('shop.wishlist') ? route('shop.wishlist') : url('/wishlist');
            $badge = '<span class="wishlist-count-badge '.$badgeCls.($count > 0 ? '' : ' hidden').'" style="'.$badgeStyle.'">'.$count.'</span>';

            return '<li class="falcon-menu-item lazy-special-item lazy-special-wishlist">'
                 .'<a href="'.e($wishUrl).'" class="falcon-menu-link lazy-special-link" style="'.$style.$iconWrap.'" aria-label="'.e($label ?: 'Wishlist').'">'
                 .$icon.$badge
                 .'</a></li>';
        }

        // special_search — icon toggles a simple search box that drops below the menu.
        $searchUrl = Route::has('frontend.search') ? route('frontend.search') : url('/search');
        $toggle = "var p=this.parentNode.querySelector('.lazy-search-panel');"
                ."var open=p.style.display!=='block';p.style.display=open?'block':'none';"
                ."if(open){var i=p.querySelector('input');if(i){i.focus();}}return false;";
        $panelStyle = 'display:none;position:absolute;top:100%;right:0;margin-top:8px;z-index:9999;'
                    .'background:#fff;border:1px solid #e5e7eb;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:10px;min-width:240px;';

        return '<li class="falcon-menu-item lazy-special-item lazy-special-search" style="position:relative;">'
             .'<a href="#" class="falcon-menu-link lazy-special-link" style="'.$style.$iconWrap.'" '
             .'onclick="'.e($toggle).'" aria-label="'.e($label ?: 'Search').'">'
             .$icon
             .'</a>'
             .'<div class="lazy-search-panel" style="'.$panelStyle.'">'
             .'<form action="'.e($searchUrl).'" method="GET" style="display:flex;align-items:center;gap:6px;">'
             .'<input type="text" name="s" placeholder="Search…" autocomplete="off" '
             .'style="flex:1;height:36px;padding:0 10px;border:1px solid #e5e7eb;border-radius:4px;font-size:14px;outline:none;color:#111;">'
             .'<button type="submit" style="height:36px;padding:0 12px;background:#0091ea;color:#fff;border:none;border-radius:4px;font-size:13px;font-weight:600;cursor:pointer;">Go</button>'
             .'</form>'
             .'</div></li>';
    }
}
