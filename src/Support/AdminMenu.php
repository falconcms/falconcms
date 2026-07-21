<?php

namespace FalconCms\Core\Support;

use Illuminate\Support\Collection;

/**
 * Runtime admin sidebar menu registry — the WordPress `add_menu_page()` analogue.
 *
 * A package or a site's own service provider registers menus in code (never in the
 * database) by hooking the `falcon_admin_menu` action and calling
 * `falcon_add_menu_page()` / `falcon_add_submenu_page()`. The {@see \FalconCms\Core\View\Components\Admin\Sidebar}
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
     *   'capability'  RBAC permission required to see it     [capability]  (default: access_dashboard)
     *   'icon'        material-symbol name or raw <svg>      [icon_url]
     *   'position'    sort order within its group            [position]    (default 100)
     *   'group'       sidebar section label                                (default 'Extend'; 'Main' = no label)
     *   'params'      route params                            (default [])
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
            'slug'       => $args['slug'],
            'menu_title' => $args['menu_title'] ?? $args['title'] ?? 'Settings',
            'route'      => 'admin.options.show',
            'params'     => ['slug' => $args['slug']],
            'capability' => $args['capability'] ?? 'manage_settings',
            'icon'       => $args['icon'] ?? 'tune',
            'group'      => $args['group'] ?? 'Extend',
            'position'   => $args['position'] ?? 100,
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
                    ->map(fn (array $child) => $this->toItem($child))
                    ->sortBy('order')
                    ->values();

                return $this->toItem($page, $children);
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

    /** Normalise a registration array into a sidebar item object (children as a Collection). */
    protected function toItem(array $a, ?Collection $children = null): object
    {
        return (object) [
            'title'      => $a['menu_title'] ?? $a['title'] ?? 'Menu',
            'route'      => $a['route'] ?? '#',
            'icon'       => $a['icon'] ?? '',
            // The sidebar's getPermission() returns this verbatim when set, so an
            // explicit permission short-circuits any title-based derivation.
            'permission' => $a['capability'] ?? $a['permission'] ?? 'access_dashboard',
            'params'     => $a['params'] ?? [],
            'parent_id'  => null,
            'order'      => $a['position'] ?? $a['order'] ?? 100,
            'group'      => $a['group'] ?? 'Extend',
            'children'   => $children ?? collect(),
        ];
    }
}
