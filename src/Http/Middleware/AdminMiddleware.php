<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use FalconCms\Core\Models\BlockedIp;
use FalconCms\Core\Models\Menu;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\AdminMenu;
use FalconCms\Core\Support\MenuAccess;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminMiddleware
{
    /**
     * Kept as a no-op so an older call site cannot fatal during an update.
     *
     * It used to drop a year-long cookie marking a browser that had signed in, which
     * {@see self::handle()} then let past the /admin 404 and on to the login page. That
     * exception is gone — see the note there — so there is nothing left to remember.
     *
     * @deprecated Since the /admin 404 applies to every browser again. Stop calling it.
     */
    public static function rememberBrowser(): void
    {
        // Intentionally empty.
    }

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
        $login_slug = get_cms_option('login_url', 'falcon-admin');
        $register_slug = get_cms_option('register_url', 'falcon-registration');

        if ($request->is('admin/login*') || $request->is('admin/register*') ||
            $request->is('admin/login/check') || $request->is('admin/email/check') ||
            $request->is($login_slug.'*') || $request->is($register_slug.'*')) {
            return $next($request);
        }

        // 3. Ensure Authenticated
        //
        // 404, never a redirect to the login page. The login URL is deliberately moved off
        // a guessable path (Settings → Login URL); bouncing an anonymous hit on /admin
        // straight to it hands that address to anyone who types the obvious guess, which
        // defeats the whole point of moving it. Without a session the admin simply does
        // not exist, whoever is asking.
        //
        // v2.6.13 carved out an exception for a browser that had signed in here before,
        // so an admin whose session had lapsed met the login page rather than a bare 404.
        // The exception is gone: it made the address reachable from /admin again for the
        // one browser most likely to be used to check whether moving it had worked, and
        // it left the setting looking like it did nothing. An admin who cannot reach the
        // panel goes to the address in Settings → Login URL, which is the address they
        // chose.
        if (!auth()->check()) {
            abort(404);
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
            $accountPage = $accountPageId ? Post::find($accountPageId) : null;
            $accountUrl = $accountPage ? url('/'.$accountPage->slug) : url('/');

            return redirect($accountUrl);
        }

        // 4. Strict Permission Check
        $userRoleSlug = $user->role ? $user->role->slug : null;
        if (!$userRoleSlug && $user->role_id) {
            $userRoleSlug = DB::table('roles')->where('id', $user->role_id)->value('slug');
        }
        // By slug, not by id: a role that merely has id 1 or 6 is not an administrator.
        $isAdmin = in_array($userRoleSlug, ['super-admin', 'administrator', 'admin'], true)
                || (method_exists($user, 'isAdmin') && $user->isAdmin());

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

            // A page registered with its own capability is judged by that one, so the menu a
            // package asked for and the page behind it cannot disagree. Everything else keeps
            // the manage_options_<slug> this has always demanded.
            $required = 'manage_options_'.$slug;
            try {
                $page = app(AdminMenu::class)->optionsPage($slug);
                if (!empty($page['capability'])) {
                    $required = $page['capability'];
                }
            } catch (\Throwable $e) {
                // Fall back to the conventional slug rather than letting a broken package
                // registration decide who gets in.
            }

            return $user->hasPermission($required);
        }

        // A plugin may decide a path no menu owns (Falcon SEO: the editor's SEO helpers, for
        // whoever can write posts or pages). true or false decides; null leaves it to the menus.
        $decided = apply_falcon_filters('falcon_admin_path_access', null, $path, $user, $request);
        if (is_bool($decided)) {
            return $decided;
        }

        // ── Dynamic, menu-driven access control ──────────────────────────────────
        // The required permission for any admin page is derived from the menu item that
        // "owns" the path (its getPermission slug — the SAME slug the Roles editor assigns).
        // So whatever a role has checked is exactly what it can reach; nothing else.
        $sidebar = new Sidebar;
        $access = new MenuAccess($sidebar);

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

        // Everything under the shared posts/pages paths except the list and the create
        // screen — a row's edit, update, delete, revisions, and a store whose type is in the
        // body — carries no ?type=, so the permission it needs depends on the row's own type
        // and only the controller knows it. PostController and PageController enforce read
        // and write for each of those; a menu matched here by path alone would be
        // the wrong one (All Posts standing in for a product).
        if (preg_match('#^admin/(posts|pages)(/(.+))?$#', $path, $m)) {
            $sub = $m[3] ?? '';
            if (!($request->isMethodSafe() && ($sub === '' || $sub === 'create'))) {
                return true;
            }
        }

        $bestMatch = $access->ownerOf($path, $request->query('type') ?? $request->query('cpt_slug'));

        if (!$bestMatch) {
            // Strict default: any page not owned by a permitted menu is denied.
            return false;
        }

        // A package can declare a menu public, meaning every signed-in user reaches it and
        // there is no permission to hold.
        if (!$bestMatch instanceof Menu && !empty($bestMatch->public)) {
            return true;
        }

        if (!$sidebar->allows($user, $bestMatch)) {
            return false;
        }

        // A list whose section has an Add New is read-only without it: viewing the list
        // needs the list's permission, changing anything in it needs Add New as well.
        if ($access->isWrite($request)) {
            $addNew = $access->addNewFor($bestMatch);
            if ($addNew && !$sidebar->allows($user, $addNew)) {
                return false;
            }
        }

        return true;
    }
}
