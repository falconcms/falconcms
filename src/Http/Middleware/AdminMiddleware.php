<?php

namespace Acme\CmsDashboard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Acme\CmsDashboard\Models\BlockedIp;
use Acme\CmsDashboard\Models\Menu;
use Acme\CmsDashboard\View\Components\Admin\Sidebar;
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

        // Try to match current URL with Menu items
        // We look for a menu item whose route/URL matches or is a parent of the current path
        // We sort by descending length to match more specific routes first (e.g. admin/pages/create before admin/pages)
        $sidebar = new Sidebar(); 
        $menus = Menu::all()->sort(function($a, $b) use ($sidebar) {
            $urlA = $sidebar->resolveRoute($a->route, $a->title);
            $urlB = $sidebar->resolveRoute($b->route, $b->title);
            $pathA = trim(parse_url($urlA, PHP_URL_PATH), '/');
            $pathB = trim(parse_url($urlB, PHP_URL_PATH), '/');
            
            $lenA = strlen($pathA);
            $lenB = strlen($pathB);
            
            if ($lenA !== $lenB) {
                return $lenB <=> $lenA; // Longest path first
            }

            // If lengths equal, prioritize those with query strings (more specific)
            $hasQA = str_contains($urlA, '?');
            $hasQB = str_contains($urlB, '?');
            if ($hasQA && !$hasQB) return -1;
            if (!$hasQA && $hasQB) return 1;
            
            // If still equal, prioritize children (those with parent_id)
            if ($a->parent_id && !$b->parent_id) return -1;
            if (!$a->parent_id && $b->parent_id) return 1;
            
            return 0;
        });

        $bestMatch = null;
        $bestMatchLen = -1;

        foreach ($menus as $menu) {
            // Skip parents that have children, because their URLs are handled by the children
            if ($menu->children()->count() > 0) continue;

            $menuUrl = $sidebar->resolveRoute($menu->route, $menu->title);
            $menuPath = trim(parse_url($menuUrl, PHP_URL_PATH), '/');

            if (!$menuPath || $menuPath === 'admin') continue;

            // If current path starts with this menu's path
            if (Str::startsWith($path, $menuPath)) {
                parse_str(parse_url($menuUrl, PHP_URL_QUERY), $menuQuery);
                $menuType = $menuQuery['type'] ?? $menuQuery['cpt_slug'] ?? null;
                $currentType = $request->query('type') ?? $request->query('cpt_slug');

                // If menu has a type, it MUST match exactly
                if ($menuType && $currentType !== $menuType) {
                    continue;
                }

                $matchLen = strlen($menuPath);
                
                // If it's a type-specific match, give it a weight boost to prioritize it over general ones of same length
                if ($menuType) $matchLen += 1000; 
                
                // If this is an exact path match, give it a boost
                if ($path === $menuPath) $matchLen += 500;

                if ($matchLen > $bestMatchLen) {
                    $bestMatchLen = $matchLen;
                    $bestMatch = $menu;
                }
            }
        }

        if ($bestMatch) {
            $perm = $sidebar->getPermission($bestMatch);
            $hasPerm = $user->hasPermission($perm);
            // \Illuminate\Support\Facades\Log::info("AdminMiddleware Match", [
            //     'path' => $path,
            //     'best_match' => $bestMatch->title,
            //     'required_perm' => $perm,
            //     'has_perm' => $hasPerm
            // ]);
            return $hasPerm;
        }




        // Fallback to the Sidebar's canAccess static logic for routes not in Menu table
        return Sidebar::canAccess($request->fullUrl());
    }
}

