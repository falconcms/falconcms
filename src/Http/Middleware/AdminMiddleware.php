<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use FalconCms\Core\Models\BlockedIp;
use FalconCms\Core\Models\Menu;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Support\Str;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Check IP Block
        $isIpBlocked = BlockedIp::where('ip_address', $request->ip())
            ->where('attempts', '>=', 5)
            ->exists();

        if ($isIpBlocked) {
            abort(403, 'You do not have permission to access this page. Your IP has been blocked.');
        }

        // 2. Exclude Public Auth Routes
        $login_slug = get_cms_option('login_url', 'super-lazy-admin');
        $register_slug = get_cms_option('register_url', 'super-lazy-register');

        if ($request->is('admin/login*') || $request->is('admin/register*') || 
            $request->is('admin/login/check') || $request->is('admin/email/check') ||
            $request->is($login_slug . '*') || $request->is($register_slug . '*')) {
            return $next($request);
        }

        // 3. Ensure Authenticated
        if (!auth()->check()) {
            return redirect()->route('admin.login')->with('error', 'Please login to access the admin panel.');
        }

        $user = auth()->user()->fresh();
        if ($user && ($user->is_blocked || ($user->blocked_until && $user->blocked_until->isFuture()))) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('admin.login')->withErrors(['email' => 'Your account has been blocked. Please contact the administrator.']);
        }

        // Redirect customer-role users to the shop account page instead of admin
        if (optional($user->role)->slug === 'customer') {
            $accountPageId = get_shop_option('shop_account_page_id');
            $accountPage   = $accountPageId ? \FalconCms\Core\Models\Post::find($accountPageId) : null;
            $accountUrl    = $accountPage ? url('/' . $accountPage->slug) : url('/');
            return redirect($accountUrl);
        }

        // 4. Strict Permission Check
        $userRoleSlug = $user->role ? $user->role->slug : null;
        if (!$userRoleSlug && $user->role_id) {
            $userRoleSlug = \Illuminate\Support\Facades\DB::table('roles')->where('id', $user->role_id)->value('slug');
        }
        $isAdmin = in_array($userRoleSlug, ['super-admin', 'administrator', 'admin'])
                || in_array($user->role_id, [1, 6]);

        if (!$isAdmin) {
            if (!$this->canUserAccessUrl($request, $user)) {
                abort(403, 'Access Denied. You do not have the required permissions to view this page.');
            }
        }

        return $next($request);
    }

    protected function canUserAccessUrl(Request $request, $user)
    {
        $path = trim($request->getPathInfo(), '/');
        
        // Always allow Dashboard root and Profile
        if ($path === 'admin' || $path === 'admin/profile' || $path === 'admin/logout') {
            return true;
        }

        // Check for Custom Options pages
        if (Str::startsWith($path, 'admin/options/')) {
            $slug = Str::after($path, 'admin/options/');
            return $user->hasPermission('manage_options_' . $slug);
        }

        // ── Dynamic, menu-driven access control ──────────────────────────────────
        // The required permission for any admin page is derived from the menu item that
        // "owns" the path (its getPermission slug — the SAME slug the Roles editor assigns).
        // So whatever a role has checked is exactly what it can reach; nothing else.
        $sidebar = new Sidebar();

        // "Your Profile" redirects to the current user's OWN account edit page
        // (/admin/users/{id}/edit). Editing one's own account is governed by the
        // Your Profile permission, NOT user-management — so it works without "All Users".
        if (preg_match('#^admin/users/(\d+)(/edit)?$#', $path, $m) && (int) $m[1] === (int) $user->id) {
            if ($user->hasPermission('manage_users') || $user->hasPermission('access_all_users_users')) {
                return true;
            }
            $profileMenu = Menu::where('route', 'admin.profile')->first();
            $profilePerm = $profileMenu ? $sidebar->getPermission($profileMenu) : 'access_your_profile_users';
            return $user->hasPermission($profilePerm);
        }

        // The shared posts/pages list paths are normally linked with ?type=…; default it
        // so the bare index path still maps to its menu.
        $currentType = $request->query('type') ?? $request->query('cpt_slug');
        if (!$currentType) {
            if ($path === 'admin/posts') $currentType = 'post';
            elseif ($path === 'admin/pages') $currentType = 'page';
        }

        $bestMatch = null;
        $bestMatchLen = -1;

        foreach (Menu::all() as $menu) {
            // Parents with children are reached through their children's URLs.
            if ($menu->children()->count() > 0) continue;

            $menuUrl  = $sidebar->resolveRoute($menu);           // pass the menu object (not strings)
            $menuPath = trim(parse_url($menuUrl, PHP_URL_PATH) ?? '', '/');
            if (!$menuPath || $menuPath === 'admin') continue;

            if (Str::startsWith($path, $menuPath)) {
                parse_str(parse_url($menuUrl, PHP_URL_QUERY) ?? '', $menuQuery);
                $menuType = $menuQuery['type'] ?? $menuQuery['cpt_slug'] ?? null;

                // A type-specific menu must match the request's type.
                if ($menuType && $currentType !== $menuType) continue;

                $matchLen = strlen($menuPath);
                if ($menuType) $matchLen += 1000;          // prefer type-specific
                if ($path === $menuPath) $matchLen += 500; // prefer exact path

                if ($matchLen > $bestMatchLen) {
                    $bestMatchLen = $matchLen;
                    $bestMatch = $menu;
                }
            }
        }

        if ($bestMatch) {
            return $user->hasPermission($sidebar->getPermission($bestMatch));
        }

        // Row-level actions under the shared posts/pages paths (e.g. /admin/posts/5/edit)
        // carry no ?type=, so their required permission depends on the row's own type —
        // PostController enforces those per-type. Allow them through to the controller.
        if (preg_match('#^admin/(posts|pages)/.+#', $path)) {
            return true;
        }

        // Strict default: any page not owned by a permitted menu is denied.
        return false;
    }
}

