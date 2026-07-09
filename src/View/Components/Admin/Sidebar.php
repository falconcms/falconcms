<?php

namespace FalconCms\Core\View\Components\Admin;

use FalconCms\Core\Models\Menu;
use Illuminate\View\Component;
use Illuminate\Support\Facades\Route;

class Sidebar extends Component
{
    public $menuGroups;
    public $activeMenu;

    public function __construct(?string $activeMenu = null)
    {
        $this->activeMenu = $activeMenu;

        $this->menuGroups = Menu::with('children')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get()
            ->groupBy('group');

        // Drop menu items that point at a route which isn't registered — e.g. Pro
        // features (the Shop back-office) gated off on an unlicensed site. Generic and
        // self-maintaining: any item targeting an unregistered named route disappears,
        // and a parent left with no visible children goes with it.
        $this->menuGroups = $this->menuGroups
            ->map(function ($menus) {
                return $menus
                    ->map(function ($menu) {
                        if ($menu->relationLoaded('children') && $menu->children) {
                            $menu->setRelation('children', $menu->children
                                ->filter(fn ($child) => $this->routeAvailable($child))
                                ->values());
                        }
                        return $menu;
                    })
                    ->filter(function ($menu) {
                        return $this->routeAvailable($menu)
                            || ($menu->children && $menu->children->isNotEmpty());
                    })
                    ->values();
            })
            ->filter(fn ($menus) => $menus->isNotEmpty());
    }

    /**
     * Whether a menu item's target route is available. Items without a named route
     * (blank, '#', or a raw URL/path) are always shown; an item pointing at a named
     * route is shown only when that route is actually registered.
     */
    protected function routeAvailable($menu): bool
    {
        $route = $menu->route ?? null;
        if (! $route || $route === '#' || str_starts_with($route, '/') || str_starts_with($route, 'http')) {
            return true;
        }
        // Every Pro feature is now VISIBLE in the menu: each uses either the "browse but locked"
        // model (view the page freely; write actions are gated by EnsureProEditable + a toast) or
        // the analytics "locked preview". So no Pro menu item is hidden here anymore.
        if (str_contains($route, '.')) {
            return Route::has($route);
        }
        return true;
    }

    public static function isUrlActive($url, $strict = false)
    {
        if (!$url || $url === '#') return false;

        $targetUrl = parse_url($url);
        $targetPath = trim($targetUrl['path'] ?? '', '/');
        $currentPath = trim(request()->getPathInfo(), '/');

        // 1. Dashboard: Must be exact match 'admin'
        if ($targetPath === 'admin') {
            return $currentPath === 'admin';
        }

        // 2. Base path check
        $indexPaths = ['admin/posts', 'admin/pages', 'admin/users', 'admin/settings', 'admin/roles', 'admin/categories', 'admin/tags', 'admin/product-categories', 'admin/product-tags', 'admin/comments', 'admin/profile'];
        
        // Special case: Your Profile belongs to Users group
        if ($targetPath === 'admin/users' && ($currentPath === 'admin/profile' || str_starts_with($currentPath, 'admin/users/') && str_ends_with($currentPath, '/edit'))) {
            // If we are editing the CURRENT user, then Your Profile should be active
            $route = request()->route();
            if ($route && $route->getName() === 'admin.users.edit') {
                $userParam = $route->parameter('user');
                $userId = ($userParam instanceof \App\Models\User) ? $userParam->id : $userParam;
                if ((int)$userId === (int)auth()->id()) {
                    return false; // Parent itself not active, but children loop will find it
                }
            }
        }

        // Special case for Your Profile child item
        if ($targetPath === 'admin/profile') {
            if ($currentPath === 'admin/profile') return true;
            
            $route = request()->route();
            if ($route && $route->getName() === 'admin.users.edit') {
                $userParam = $route->parameter('user');
                $userId = ($userParam instanceof \App\Models\User) ? $userParam->id : $userParam;
                return (int)$userId === (int)auth()->id();
            }
        }
        
        if (in_array($targetPath, $indexPaths)) {
            // Index routes MUST be an exact match (ignoring query strings for now) 
            // unless it's a specific type (handled in step 3) or an edit/create page
            if ($currentPath !== $targetPath) {
                // If it's something like admin/posts/create, the index 'admin/posts' should be active 
                // ONLY if the types match (handled later) or if it's a generic index.
                // For now, let's allow it if it starts with the target path followed by /
                if ($strict || !str_starts_with($currentPath, $targetPath . '/')) {
                    return false;
                }
            }
        } elseif (!str_starts_with($currentPath, $targetPath)) {
            return false;
        }

        // 3. Query Parameter & Type Strict Check
        parse_str($targetUrl['query'] ?? '', $targetQuery);
        
        $currentType = request()->query('type');
        $currentCpt = request()->query('cpt_slug') ?? request()->query('cpt');

        // Fallback: Detect type from route if on edit/create page
        if (!$currentType) {
            $route = request()->route();
            if ($route) {
                $post = $route->parameter('post');
                if ($post instanceof \FalconCms\Core\Models\Post) {
                    $currentType = $post->type;
                } elseif (is_numeric($post)) {
                    try {
                        $currentType = \FalconCms\Core\Models\Post::where('id', $post)->value('type');
                    } catch (\Exception $e) {}
                } else {
                    $currentType = $route->parameter('type');
                }
            }
        }

        $targetType = $targetQuery['type'] ?? $targetQuery['cpt_slug'] ?? $targetQuery['cpt'] ?? null;

        // If target has a type/cpt_slug, current request MUST match it
        if ($targetType) {
            if ($currentType !== $targetType && $currentCpt !== $targetType) {
                return false;
            }
            
            // Even if types match, if target is an index path, current path MUST match exactly 
            // OR be a child of it (like /create or /1/edit)
            if (in_array($targetPath, $indexPaths) && $currentPath !== $targetPath) {
                if ($strict || !str_starts_with($currentPath, $targetPath . '/')) {
                    return false;
                }
            }

            return true;
        }

        // IMPORTANT: If target belongs to standard Posts/Pages but HAS NO TYPE (it's a root or general menu),
        // it should NOT be active if the CURRENT request has a custom type (CPT).
        if (!$targetType && !empty($currentType) && !in_array($currentType, ['post', 'page'])) {
            $isTargetPosts = str_starts_with($targetPath, 'admin/posts');
            $isTargetPages = str_starts_with($targetPath, 'admin/pages');

            if ($isTargetPosts || $isTargetPages) {
                return false;
            }
        }

        return true;
    }

    public static function canAccess($url)
    {
        if (!$url || $url === '#') return true;
        if (!auth()->check()) return false;
        $user = auth()->user();

        $targetUrl = parse_url($url);
        $targetPath = trim($targetUrl['path'] ?? '', '/');
        parse_str($targetUrl['query'] ?? '', $targetQuery);

        $type = $targetQuery['type'] ?? $targetQuery['cpt_slug'] ?? null;

        // Dashboard
        if ($targetPath === 'admin') return true;

        // Content / Posts / Pages
        if (str_contains($targetPath, 'admin/posts') || str_contains($targetPath, 'admin/pages')) {
            $pType = $type ?: (str_contains($targetPath, 'admin/pages') ? 'page' : 'post');
            if ($pType === 'page') return $user->hasPermission('manage_pages');
            if ($pType === 'post') return $user->hasPermission('manage_posts');
            
            // For CPTs, check multiple potential permission prefixes. Permission slugs are
            // derived from the (often pluralised) menu title — e.g. the "Products" CPT yields
            // access_products / access_all_products — so accept both the type slug and its
            // plural form to avoid singular/plural mismatches.
            foreach (array_unique([$pType, $pType . 's']) as $v) {
                if ($user->hasPermission('manage_' . $v)
                    || $user->hasPermission('access_' . $v)
                    || $user->hasPermission('access_all_' . $v)) {
                    return true;
                }
            }
            return false;
        }

        // Users
        if (str_contains($targetPath, 'admin/users') || str_contains($targetPath, 'admin/blacklist')) return $user->hasPermission('manage_users');
        
        // Roles
        if (str_contains($targetPath, 'admin/roles')) return $user->hasPermission('manage_roles');

        // Settings
        if (str_contains($targetPath, 'admin/settings') || $targetPath === 'admin/dashboard/settings') return $user->hasPermission('manage_settings');

        // Media
        if (str_contains($targetPath, 'admin/media')) return $user->hasPermission('manage_media');
        
        // Each section below requires its OWN permission. Broad permissions
        // (manage_posts / manage_settings) are intentionally NOT accepted as a
        // cross-module master key, so granting one section does not unlock unrelated
        // pages by typing the URL.

        // Comments
        if (str_contains($targetPath, 'admin/comments')) return $user->hasPermission('access_comments');

        // Appearance (Themes, Menus, Widgets)
        if (str_contains($targetPath, 'admin/themes')) return $user->hasPermission('access_themes');
        if (str_contains($targetPath, 'admin/menus')) return $user->hasPermission('access_menus');
        if (str_contains($targetPath, 'admin/widgets')) return $user->hasPermission('access_widgets');

        // Product taxonomy (dedicated Product Categories / Tags pages).
        if (str_contains($targetPath, 'admin/product-categories')) {
            return $user->hasPermission('access_categories_products')
                || $user->hasPermission('access_product_category');
        }
        if (str_contains($targetPath, 'admin/product-tags')) {
            return $user->hasPermission('access_tags_products')
                || $user->hasPermission('access_product_tags');
        }

        // Post taxonomy (Categories / Tags pages).
        if (str_contains($targetPath, 'admin/categories')) {
            return $user->hasPermission('access_categories_posts')
                || $user->hasPermission('access_categories');
        }
        if (str_contains($targetPath, 'admin/tags')) {
            return $user->hasPermission('access_tags_posts')
                || $user->hasPermission('access_tags');
        }

        // Shop / eCommerce — requires a Shop permission (or one of its sub-permissions).
        if (str_contains($targetPath, 'admin/shop')) {
            return $user->hasPermission('access_shop')
                || $user->hasPermission('access_orders_shop')
                || $user->hasPermission('access_customers_shop')
                || $user->hasPermission('access_settings_shop')
                || $user->hasPermission('access_product_reviews');
        }

        // ACPT
        if (str_contains($targetPath, 'admin/acpt')) return $user->hasPermission('manage_settings');

        return false;
    }

    public function resolveRoute($menu)
    {
        if (is_string($menu)) return '#';
        if (is_array($menu)) $routeStr = $menu['route'] ?? '#';
        else $routeStr = $menu->route ?? '#';

        $title = is_array($menu) ? ($menu['title'] ?? '') : ($menu->title ?? '');
        $params = is_array($menu) ? ($menu['params'] ?? []) : ($menu->params ?? []);
        if (is_string($params)) $params = json_decode($params, true);
        if (!is_array($params)) $params = [];

        if (!$routeStr || $routeStr === '#') {
            if ($title === 'Categories') return route('admin.categories.index', $params);
            if ($title === 'Tags') return route('admin.tags.index', $params);
            if ($title === 'All Posts') return route('admin.posts.index', $params);
            if ($title === 'Add Post') return route('admin.posts.create', $params);
            if ($title === 'All Pages') return route('admin.pages.index', $params);
            if ($title === 'Add New' || $title === 'Add Page') return route('admin.pages.create', $params);
            if ($title === 'Comments') return route('admin.comments.index', $params);

            try {
                $postType = \FalconCms\Core\Models\PostType::where('name', $title)->first();
                if ($postType) {
                    return route('admin.posts.index', ['type' => $postType->slug]);
                }
            } catch (\Exception $e) {}

            if ($title === 'Tools') {
                if (auth()->user()->hasPermission('access_backup_restore') || auth()->user()->hasPermission('manage_settings')) {
                    if (Route::has('admin.backup.index')) return route('admin.backup.index');
                }
                if (auth()->user()->hasPermission('access_languages')) {
                    if (Route::has('admin.languages.index')) return route('admin.languages.index');
                }
            }

            return '#';
        }

        if (str_starts_with($routeStr, '/') || str_starts_with($routeStr, 'http')) return url($routeStr);
        return Route::has($routeStr) ? route($routeStr, $params) : $routeStr;
    }

    public function getPermission($menu)
    {
        if (is_string($menu)) return 'access_dashboard';
        if (is_array($menu)) {
            if (!empty($menu['permission'])) return $menu['permission'];
            $title = strtolower($menu['title'] ?? '');
            $parentId = $menu['parent_id'] ?? null;
        } else {
            if (!empty($menu->permission)) return $menu->permission;
            $title = strtolower($menu->title ?? '');
            $parentId = $menu->parent_id ?? null;
        }
        
        // Canonical (legacy) slugs apply only to TOP-LEVEL core menus. A child with the
        // same title (e.g. Shop → Settings) must NOT collapse onto manage_settings.
        if (!$parentId) {
            if ($title === 'dashboard') return 'access_dashboard';
            if ($title === 'posts') return 'manage_posts';
            if ($title === 'pages') return 'manage_pages';
            if ($title === 'media') return 'manage_media';
            if ($title === 'users') return 'manage_users';
            if ($title === 'settings') return 'manage_settings';
            if ($title === 'roles') return 'manage_roles';
            if ($title === 'analytics') return 'manage_analytics';
        }

        $titleStr = is_array($menu) ? ($menu['title'] ?? '') : ($menu->title ?? '');
        $slug = \Illuminate\Support\Str::slug($titleStr, '_');

        // Children are ALWAYS namespaced by their parent, so two items sharing a title
        // under different parents never collide (Shop → Settings vs top-level Settings,
        // Shop → Overview vs Dashboard → Overview, Shop → Orders, etc.).
        if ($parentId) {
            $parent = \FalconCms\Core\Models\Menu::find($parentId);
            if ($parent) {
                $slug .= '_' . \Illuminate\Support\Str::slug($parent->title, '_');
            }
        }

        return 'access_' . $slug;
    }

    public function render()
    {
        return view('falcon-cms::components.admin.sidebar', [
            'getPermission' => [$this, 'getPermission'],
            'resolveRoute' => [$this, 'resolveRoute'],
        ]);
    }
}
