<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Models\Menu;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Read and write access, worked out from the admin menus.
 *
 * A section that lists something and offers an "Add New" beside it — Posts, Pages, every post
 * type, Products, Media, Users, Forms, and any package menu built the same way — is read with
 * the list's permission and changed with the Add New one. A role given the list alone browses
 * it; creating, editing and deleting need Add New as well.
 *
 * Nothing here names a section. The pairing is found by shape (a sibling whose address is the
 * list's own plus /create, or whose route is the list's `.index` route as `.create`), so a post
 * type, a role or a plugin menu added later follows the same rule without anyone wiring it up.
 */
class MenuAccess
{
    protected Sidebar $sidebar;

    /** @var array<int,mixed>|null */
    protected ?array $candidates = null;

    /** Registered submenu items' siblings, keyed by spl_object_id() of the item. */
    protected array $registeredSiblings = [];

    public function __construct(?Sidebar $sidebar = null)
    {
        $this->sidebar = $sidebar ?? new Sidebar;
    }

    /**
     * Every menu that can own an admin path: the ones in the menus table, plus the ones a
     * plugin or theme registered in code.
     *
     * @return array<int,mixed>
     */
    public function candidates(): array
    {
        if ($this->candidates !== null) {
            return $this->candidates;
        }

        // With the number of children, so hasChildren() asks nothing more of the database:
        // every admin request walks this list, and a COUNT per menu was ~80 queries a page.
        $candidates = Menu::withCount('children')->get()->all();

        try {
            foreach (app(AdminMenu::class)->grouped() as $items) {
                foreach ($items as $item) {
                    $candidates[] = $item;
                    foreach ($item->children as $child) {
                        $candidates[] = $child;
                        $this->registeredSiblings[spl_object_id($child)] = $item->children;
                    }
                }
            }
        } catch (\Throwable $e) {
            // A package throwing while registering must not decide who gets in; the database
            // menus still answer for every core page.
        }

        return $this->candidates = $candidates;
    }

    /** Works for a Menu row (a relation) and for a registered item (a plain Collection). */
    public function hasChildren($menu): bool
    {
        if ($menu instanceof Menu) {
            return ($menu->children_count ?? $menu->children()->count()) > 0;
        }

        return !empty($menu->children) && count($menu->children) > 0;
    }

    /** The admin path a menu points at, without the host or the query string. */
    public function pathOf($menu): string
    {
        return trim(parse_url($this->sidebar->resolveRoute($menu), PHP_URL_PATH) ?? '', '/');
    }

    /** The post type a menu's address is about (`?type=` / `?cpt_slug=`), if any. */
    public function typeOf($menu): ?string
    {
        parse_str(parse_url($this->sidebar->resolveRoute($menu), PHP_URL_QUERY) ?? '', $query);

        return $query['type'] ?? $query['cpt_slug'] ?? null;
    }

    /**
     * Whether a request changes something rather than only reading it: any unsafe method,
     * and the create and edit screens, which exist only to change something.
     */
    public function isWrite(Request $request): bool
    {
        if (!$request->isMethodSafe()) {
            return true;
        }

        $path = trim($request->getPathInfo(), '/');

        return Str::endsWith($path, ['/create', '/edit']);
    }

    /**
     * The "Add New" item that sits beside a list menu, or null when the section has none
     * (Settings, Analytics, a single-page plugin menu) — those have no read-only mode, their
     * own permission decides everything.
     */
    public function addNewFor($menu)
    {
        $path = $this->pathOf($menu);
        if ($path === '') {
            return null;
        }

        $type = $this->typeOf($menu);
        $route = (string) ($menu->route ?? '');
        $createRoute = Str::endsWith($route, '.index') ? Str::replaceLast('.index', '.create', $route) : null;

        foreach ($this->siblingsOf($menu) as $sibling) {
            if ($sibling === $menu || ($menu instanceof Menu && $sibling instanceof Menu && $sibling->is($menu))) {
                continue;
            }
            if ($this->typeOf($sibling) !== $type) {
                continue;
            }
            if ($this->pathOf($sibling) === $path.'/create'
                || ($createRoute && ($sibling->route ?? null) === $createRoute)) {
                return $sibling;
            }
        }

        return null;
    }

    /**
     * The leaf menu that owns an admin path: the longest menu address the path starts with,
     * preferring a menu for the request's post type, then an exact match. Null when no menu
     * owns it.
     */
    public function ownerOf(string $path, ?string $type = null)
    {
        $path = trim($path, '/');

        // The shared posts/pages list paths are normally linked with ?type=…; default it
        // so the bare paths still map to their menus.
        if (!$type) {
            if (Str::startsWith($path, 'admin/posts')) {
                $type = 'post';
            } elseif (Str::startsWith($path, 'admin/pages')) {
                $type = 'page';
            }
        }

        $best = null;
        $bestLen = -1;

        foreach ($this->candidates() as $menu) {
            // Parents with children are reached through their children's URLs.
            if ($this->hasChildren($menu)) {
                continue;
            }

            $menuPath = $this->pathOf($menu);
            if (!$menuPath || $menuPath === 'admin' || !Str::startsWith($path, $menuPath)) {
                continue;
            }

            // A type-specific menu must match the request's type.
            $menuType = $this->typeOf($menu);
            if ($menuType && $type !== $menuType) {
                continue;
            }

            $len = strlen($menuPath) + ($menuType ? 1000 : 0) + ($path === $menuPath ? 500 : 0);
            if ($len > $bestLen) {
                $bestLen = $len;
                $best = $menu;
            }
        }

        return $best;
    }

    /**
     * Whether the user may change things on an admin screen: true unless the menu owning it
     * has an Add New beside it that the user does not hold. A screen no menu owns, or one
     * whose section has no Add New, has no read-only mode.
     */
    public function canWriteAt($user, string $path, ?string $type = null): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        $owner = $this->ownerOf($path, $type);
        $addNew = $owner ? $this->addNewFor($owner) : null;

        return !$addNew || $this->sidebar->allows($user, $addNew);
    }

    /**
     * Whether the user may create, edit and delete content of a post type: the permission of
     * that type's Add New item. Null when the type has no such item, for the caller to fall
     * back on whatever it checked before.
     */
    public function canWriteType($user, string $type): ?bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        $list = $this->listMenuFor($type);
        $addNew = $list ? $this->addNewFor($list) : null;

        return $addNew ? $this->sidebar->allows($user, $addNew) : null;
    }

    /** The leaf menu that lists a post type (All Posts, All Pages, All Products, All Books…). */
    public function listMenuFor(string $type)
    {
        $listPath = $type === 'page' ? 'admin/pages' : 'admin/posts';

        foreach ($this->candidates() as $menu) {
            if ($this->hasChildren($menu) || $this->pathOf($menu) !== $listPath) {
                continue;
            }

            // The bare list addresses mean their own type.
            $menuType = $this->typeOf($menu) ?? ($listPath === 'admin/pages' ? 'page' : 'post');
            if ($menuType === $type) {
                return $menu;
            }
        }

        return null;
    }

    /**
     * The other items under the same parent menu. A registered item is looked up among the
     * objects {@see candidates()} handed out, since the registry builds fresh ones per call.
     */
    protected function siblingsOf($menu): iterable
    {
        if ($menu instanceof Menu) {
            return $menu->parent_id ? Menu::where('parent_id', $menu->parent_id)->get() : [];
        }

        $this->candidates();

        return $this->registeredSiblings[spl_object_id($menu)] ?? [];
    }
}
