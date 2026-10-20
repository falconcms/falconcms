<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Runtime admin sidebar menu registry — the WordPress `add_menu_page()` analogue.
 *
 * A package or a site's own service provider registers menus in code (never in the
 * database) by hooking the `falcon_admin_menu` action and calling
 * `falcon_add_menu_page()` / `falcon_add_submenu_page()`. The {@see Sidebar}
 * component asks this registry for those menus and merges them alongside the
 * database-driven core menus at render time.
 *
 * Registered menus are **not** persisted: they live only for the request, so a
 * package that is removed simply stops registering — nothing to clean up.
 *
 * Item shape mirrors the core `Menu` model closely enough for the shared sidebar
 * Blade to render both without special-casing (title, route, icon, permission,
 * params, children, order, group).
 */
class AdminMenu
{
    /** @var array<string,array> top-level pages keyed by slug (last write wins, so re-registration is idempotent) */
    protected array $pages = [];

    /** @var array<string,array<int,array>> submenu items keyed by parent slug */
    protected array $submenus = [];

    /** @var array<string,array> options-page configs (with fields) keyed by slug */
    protected array $optionsPages = [];

    /** The `falcon_admin_menu` action is fired exactly once, on first read. */
    protected bool $collected = false;

    /**
     * Register a top-level sidebar menu. Accepts (WordPress-style keys in brackets):
     *   'slug'        required, unique id                    [menu_slug]
     *   'menu_title'  label shown in the sidebar             [menu_title]  (falls back to 'title')
     *   'route'       named route, URL, or '#'               [ callback ]
     *   'capability'  RBAC permission required to see it     [capability]  (default: access_<slug>)
     *   'icon'        material-symbol name or raw <svg>      [icon_url]
     *   'position'    sort order within its group            [position]    (default 100)
     *   'group'       sidebar section label                                (default 'Extend'; 'Main' = no label)
     *   'params'      route params                            (default [])
     *   'public'      true = every signed-in user sees it     (default false)
     *   'show_in_roles' false = keep the capability, but leave it out of Users → Roles
     *
     * Unless it says otherwise a menu appears in Users → Roles as a checkbox under its group,
     * so an administrator can grant it per role without the package doing anything further.
     * Two ways out: `'public' => true` for something everyone should reach, which is not listed
     * because there is nothing to grant; and `'show_in_roles' => false` for a menu whose
     * capability is granted somewhere else and would only be confusing twice over.
     */
    public function addMenuPage(array $args): void
    {
        if (empty($args['slug'])) {
            return;
        }
        $this->pages[$args['slug']] = $args;
    }

    /** Register a submenu item under a parent menu's slug. Same keys as {@see addMenuPage()} (slug optional). */
    public function addSubmenuPage(string $parentSlug, array $args): void
    {
        $this->submenus[$parentSlug][] = $args;
    }

    /**
     * Register a self-rendering settings page — the WordPress Settings API analogue.
     * FalconCMS renders the form from your `fields`, pre-fills them from CMS options
     * and saves them back on submit; you only declare the fields. Also adds the
     * sidebar menu automatically. In addition to the {@see addMenuPage()} keys:
     *   'title'   heading shown on the page
     *   'fields'  array of field definitions, each:
     *               'name'    (required) the cms_option key it reads/writes
     *               'label'   field label
     *               'type'    text|textarea|number|email|password|checkbox|select|color (default text)
     *               'options' value=>label map (for select)
     *               'default' value when the option is unset
     *               'help'    small helper text
     *               'placeholder'
     */
    public function addOptionsPage(array $args): void
    {
        if (empty($args['slug'])) {
            return;
        }
        $this->optionsPages[$args['slug']] = $args;

        // Wire the sidebar menu to the generic options route for this page.
        $this->addMenuPage([
            'slug' => $args['slug'],
            'menu_title' => $args['menu_title'] ?? $args['title'] ?? 'Settings',
            'route' => 'admin.options.show',
            'params' => ['slug' => $args['slug']],
            // The permission AdminMiddleware already demands for this page, rather than
            // manage_settings. Three rules used to disagree about one screen: the sidebar
            // showed it to anyone who could reach Settings, the middleware asked for
            // manage_options_<slug>, and the Roles screen offered neither — so a user with
            // Settings saw the menu and got a 403 from it, and nobody could be granted the
            // permission that would have worked. One spelling now, in all three places.
            'capability' => $args['capability'] ?? 'manage_options_'.$args['slug'],
            'icon' => $args['icon'] ?? 'tune',
            'group' => $args['group'] ?? 'Extend',
            'position' => $args['position'] ?? 100,
        ]);
    }

    /** The registered options-page config for a slug (fires the hook first), or null. */
    public function optionsPage(string $slug): ?array
    {
        $this->collect();

        return $this->optionsPages[$slug] ?? null;
    }

    /**
     * All registered menus as render-ready objects, grouped by their sidebar group.
     * Fires the `falcon_admin_menu` action once so registrations happen lazily, right
     * before the sidebar needs them.
     *
     * @return Collection<string,Collection<int,object>>
     */
    public function grouped(): Collection
    {
        $this->collect();

        return collect($this->pages)
            ->map(function (array $page) {
                $children = collect($this->submenus[$page['slug']] ?? [])
                    ->map(fn (array $child) => $this->toItem($child, null, $page['slug']))
                    ->sortBy('order')
                    ->values();

                $item = $this->toItem($page, $children);
                $this->separateSharedCapabilities($item);

                return $item;
            })
            ->sortBy('order')
            ->groupBy(fn (object $item) => $item->group);
    }

    /** Fire the registration hook exactly once. */
    protected function collect(): void
    {
        if ($this->collected) {
            return;
        }
        $this->collected = true;

        if (function_exists('do_falcon_action')) {
            do_falcon_action('falcon_admin_menu');
        }
    }

    /**
     * Every registered menu that belongs in Users → Roles, shaped the way that screen wants
     * it: `group => [['title', 'slug', 'children' => [['title', 'slug'], …]], …]`.
     *
     * Registered menus used to be invisible there, so a plugin could add a sidebar entry that
     * no administrator could grant to anyone — the capability existed only in the plugin's own
     * source. Now the Roles screen asks for them the same way it asks the menus table.
     *
     * @return array<string,array<int,array{title:string,slug:string,children:array}>>
     */
    public function permissionTree(): array
    {
        $tree = [];

        foreach ($this->grouped() as $group => $items) {
            foreach ($items as $item) {
                if ($item->public || !$item->show_in_roles) {
                    continue;
                }

                $children = [];
                foreach ($item->children as $child) {
                    if ($child->public || !$child->show_in_roles) {
                        continue;
                    }
                    $children[] = ['title' => $child->title, 'slug' => $child->permission];
                }

                $tree[$group][] = [
                    'title' => $item->title,
                    'slug' => $item->permission,
                    'children' => $children,
                ];
            }
        }

        return $tree;
    }

    /**
     * The submenu permissions a role saved before {@see separateSharedCapabilities()} holds
     * in effect: every sub-option of a section whose shared capability it has, when it has
     * none of that section's own. The Roles screen ticks them, so saving such a role keeps
     * what it could reach instead of silently dropping the section.
     *
     * @param  array<int,string>  $held
     * @return array<int,string>
     */
    public function impliedPermissions(array $held): array
    {
        $implied = [];

        foreach ($this->grouped()->flatten(1) as $item) {
            foreach ($item->children as $child) {
                if (empty($child->inherits) || !in_array($child->inherits, $held, true)
                    || array_intersect($child->siblings ?? [], $held)) {
                    continue;
                }
                $implied[] = $child->permission;
            }
        }

        return $implied;
    }

    /** Normalise a registration array into a sidebar item object (children as a Collection). */
    protected function toItem(array $a, ?Collection $children = null, ?string $parentSlug = null): object
    {
        $title = $a['menu_title'] ?? $a['title'] ?? 'Menu';

        return (object) [
            'slug' => $a['slug'] ?? Str::slug($title),
            'title' => $title,
            'route' => $a['route'] ?? '#',
            'icon' => $a['icon'] ?? '',
            // The sidebar's getPermission() returns this verbatim when set, so an explicit
            // capability short-circuits any title-based derivation.
            //
            // Without one the fallback is a permission of this menu's own rather than
            // access_dashboard, which is what it used to be: that meant every package menu was
            // held by whoever could see the dashboard, could not be granted or withheld
            // separately, and would have been meaningless as a checkbox in Roles.
            'permission' => $a['capability'] ?? $a['permission'] ?? $this->derivedPermission($a, $title, $parentSlug),
            'params' => $a['params'] ?? [],
            'parent_id' => null,
            'order' => $a['position'] ?? $a['order'] ?? 100,
            'group' => $a['group'] ?? 'Extend',
            'public' => (bool) ($a['public'] ?? false),
            'show_in_roles' => (bool) ($a['show_in_roles'] ?? true),
            'children' => $children ?? collect(),
        ];
    }

    /**
     * Give every submenu item a permission of its own.
     *
     * A package that passes one capability to its page and to every submenu item (Wholesale
     * did: manage_wholesale throughout) put the same checkbox value in Users → Roles six times
     * over, so ticking one sub-option ticked them all and no section could be granted alone.
     * A child that repeats its parent's or a sibling's capability is given the derived
     * `access_<parent>_<title>` instead. The shared capability stays on the parent, which the
     * Roles screen keeps checked whenever any child is, so a package still testing it in its
     * own controllers keeps working for every role that holds any of its sections.
     *
     * A role saved before this held only the shared capability. `inherits` and `siblings`
     * record that, so {@see Sidebar::allows()} can read such a role as the whole section it
     * used to mean until an administrator saves it again.
     */
    protected function separateSharedCapabilities(object $item): void
    {
        $counts = [];
        foreach ($item->children as $child) {
            $counts[$child->permission] = ($counts[$child->permission] ?? 0) + 1;
        }

        $separated = false;
        foreach ($item->children as $child) {
            $shared = $child->permission;
            if ($shared === $item->permission || $counts[$shared] > 1) {
                $child->permission = $this->derivedPermission([], $child->title, $item->slug);
                $child->inherits = $shared;
                $separated = true;
            }
        }

        if ($separated) {
            $siblings = $item->children->pluck('permission')->all();
            foreach ($item->children as $child) {
                if (isset($child->inherits)) {
                    $child->siblings = $siblings;
                }
            }
        }
    }

    /** `access_<slug>` for a page, `access_<parent>_<title>` for a submenu item. */
    protected function derivedPermission(array $a, string $title, ?string $parentSlug): string
    {
        $own = $a['slug'] ?? $title;

        return 'access_'.Str::slug($parentSlug ? $parentSlug.' '.$title : $own, '_');
    }
}
