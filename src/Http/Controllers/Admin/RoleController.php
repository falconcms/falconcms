<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Models\Menu;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Support\AdminMenu;
use FalconCms\Core\Support\Editorial;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')
            ->orderByRaw("CASE WHEN slug = 'super-admin' THEN 0 WHEN slug = 'administrator' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        return view('falcon-cms::admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $data = $this->getDynamicPermissions();

        return view('falcon-cms::admin.roles.create', $data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:roles,slug',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['slug']);
        // The administrator slugs are reserved (Role::ADMIN_SLUGS): a role created with one
        // would make everyone who holds it an administrator. Checked after Str::slug, so
        // "Admin" or " ADMIN " cannot slip past, and uniqueness is checked on the final slug.
        if ($error = $this->slugError($validated['slug'], null)) {
            return redirect()->back()->withErrors(['slug' => $error])->withInput();
        }
        $requested = array_values(array_unique(array_map('strval', (array) $request->input('permissions', []))));
        if ($error = $this->permissionError(null, $requested)) {
            return redirect()->back()->withErrors(['permissions' => $error])->withInput();
        }

        $role = Role::create($validated);

        if ($request->has('permissions')) {
            $this->syncPermissions($role, $requested);
        }

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        if ($role->slug === 'super-admin' && !auth()->user()->hasRole('super-admin')) {
            return redirect()->route('admin.roles.index')->with('error', 'Only Super Admin can manage Super Admin role.');
        }

        $data = $this->getDynamicPermissions();
        $data['role'] = $role;
        $data['rolePermissions'] = $role->permissions->pluck('slug')->toArray();
        try {
            $data['rolePermissions'] = array_merge(
                $data['rolePermissions'],
                app(AdminMenu::class)->impliedPermissions($data['rolePermissions'])
            );
        } catch (\Throwable $e) {
            // A package throwing while registering must not take the Roles screen down with it.
        }

        return view('falcon-cms::admin.roles.edit', $data);
    }

    public function update(Request $request, Role $role)
    {
        // The same guard as edit(): without it a PUT straight to the route changed the Super
        // Admin role's permissions for anyone allowed on the Roles screen.
        if ($role->slug === 'super-admin' && !auth()->user()->hasRole('super-admin')) {
            abort(403, 'Only a Super Admin can change the Super Admin role.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:roles,slug,'.$role->id,
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['slug']);
        // Administrators are known by these slugs (never by a role's id), so a role that has one
        // keeps it: renaming one is fine, re-slugging it would take every admin's access. And no
        // other role may take one (Role::ADMIN_SLUGS), or editing a role would mint administrators.
        if (Role::isAdminSlug($role->slug)) {
            $validated['slug'] = $role->slug;
        } elseif ($error = $this->slugError($validated['slug'], $role->id)) {
            return redirect()->back()->withErrors(['slug' => $error])->withInput();
        }

        // Grants for a switched-off plugin's menus are not on the form, so they never come back
        // in the request; carry them over, or saving a role while the shop is off would take
        // its shop access away for good.
        $requested = array_values(array_unique(array_map('strval', array_merge(
            (array) $request->input('permissions', []),
            array_intersect($role->permissions->pluck('slug')->all(), $this->unavailableMenuPermissions())
        ))));
        if ($error = $this->permissionError($role, $requested)) {
            return redirect()->back()->withErrors(['permissions' => $error])->withInput();
        }

        $role->update($validated);
        $this->syncPermissions($role, $requested);

        return redirect()->route('admin.roles.edit', $role)->with('success', 'Role updated successfully.');
    }

    /**
     * The permissions of menus the Roles screen leaves out because their plugin is switched
     * off — the same menus the sidebar drops.
     *
     * @return array<int,string>
     */
    protected function unavailableMenuPermissions(): array
    {
        $sidebar = new Sidebar;
        $slugs = [];

        foreach (Menu::with('children')->whereNull('parent_id')->get() as $menu) {
            $children = $menu->children;
            $available = $children->filter(fn ($child) => $sidebar->routeAvailable($child));

            foreach ($children->diff($available) as $child) {
                $slugs[] = $child->permission ?: $this->generatePermissionSlug($child, $menu);
            }
            if (!$sidebar->routeAvailable($menu) && $available->isEmpty()) {
                $slugs[] = $menu->permission ?: $this->generatePermissionSlug($menu);
            }
        }

        return $slugs;
    }

    /** Why a slug may not be used for a role (reserved administrator slug, taken), or null. */
    protected function slugError(string $slug, ?int $exceptRoleId): ?string
    {
        if ($slug === '') {
            return 'The slug must contain letters or digits.';
        }
        if (Role::isAdminSlug($slug)) {
            return "\"{$slug}\" is reserved: roles with that slug are administrators.";
        }
        if (Role::where('slug', $slug)->when($exceptRoleId, fn ($q) => $q->whereKeyNot($exceptRoleId))->exists()) {
            return 'The slug has already been taken.';
        }

        return null;
    }

    /**
     * Every permission the Roles screen can grant: exactly what it offers (menus, the
     * Publishing permission, menus registered in code through falcon_add_menu_page, custom
     * options pages), plus the grants of menus whose plugin is switched off. Anything else in a
     * request is not a permission this screen knows, and is refused rather than created.
     *
     * @return array<string, true>
     */
    protected function registeredPermissions(): array
    {
        $slugs = [];
        $walk = function (array $items) use (&$walk, &$slugs) {
            foreach ($items as $item) {
                if (!empty($item['slug'])) {
                    $slugs[(string) $item['slug']] = true;
                }
                $walk((array) ($item['children'] ?? []));
            }
        };
        foreach ($this->getDynamicPermissions()['dynamicPermissions'] as $items) {
            $walk((array) $items);
        }
        foreach ($this->unavailableMenuPermissions() as $slug) {
            $slugs[(string) $slug] = true;
        }

        return $slugs;
    }

    /**
     * Why a permission set may not be saved, or null:
     *  - a slug the Roles screen does not offer (and the role does not already hold);
     *  - for anyone but an administrator: a permission being added that they do not hold
     *    themselves. A role manager could otherwise hand any role (their own included) the
     *    plugin installer, the role editor's own reach, or anything else, and so become an
     *    administrator in all but name.
     */
    protected function permissionError(?Role $role, array $requested): ?string
    {
        $current = $role ? $role->permissions->pluck('slug')->map(fn ($s) => (string) $s)->all() : [];
        $registered = $this->registeredPermissions();
        $unknown = array_values(array_filter($requested, fn ($slug) => !isset($registered[$slug]) && !in_array($slug, $current, true)));
        if ($unknown !== []) {
            return 'Unknown permission: '.implode(', ', array_slice($unknown, 0, 10)).'. Nothing was saved.';
        }

        $editor = auth()->user();
        $editorIsAdmin = $editor !== null && method_exists($editor, 'isAdmin') && $editor->isAdmin();
        if ($editor && !$editorIsAdmin) {
            $added = array_diff($requested, $current);
            $notHeld = array_values(array_filter($added, fn ($slug) => !$editor->hasPermission($slug)));
            if ($notHeld !== []) {
                return 'You can only grant permissions you hold yourself: '.implode(', ', array_slice($notHeld, 0, 10)).'. Nothing was saved.';
            }
        }

        return null;
    }

    /** Only ever called with slugs permissionError() accepted. */
    protected function syncPermissions(Role $role, array $permissionSlugs)
    {
        $ids = [];
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace(['_', '-'], ' ', $slug))]
            );
            $ids[] = $permission->id;
        }
        $role->permissions()->sync($ids);
    }

    protected function getDynamicPermissions()
    {
        // 1. Get all Menus grouped by their group
        $menuGroups = Menu::with('children')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get()
            ->groupBy('group');

        // 2. Format into a structured array for the view
        $dynamicPermissions = [];

        // The sidebar's own availability test, so the two screens list the same menus: one
        // whose plugin is switched off (Shop and Products without falcon-shop) is not offered
        // as a permission any more than it is shown in the sidebar.
        $sidebar = new Sidebar;

        foreach ($menuGroups as $groupName => $menus) {
            foreach ($menus as $menu) {
                $children = $menu->children->filter(fn ($child) => $sidebar->routeAvailable($child));
                if (!$sidebar->routeAvailable($menu) && $children->isEmpty()) {
                    continue;
                }

                $slug = $menu->permission ?: $this->generatePermissionSlug($menu);

                $item = [
                    'title' => $menu->title,
                    'slug' => $slug,
                    'children' => [],
                ];

                foreach ($children as $child) {
                    $childSlug = $child->permission ?: $this->generatePermissionSlug($child, $menu);
                    $item['children'][] = [
                        'title' => $child->title,
                        'slug' => $childSlug,
                    ];
                }

                $dynamicPermissions[$groupName][] = $item;
            }
        }

        // 2a. Publishing is not a menu: it is what separates writing a section from taking it live.
        $dynamicPermissions['Publishing'][] = [
            'title' => 'Publish without review',
            'description' => 'Ticked: this role publishes directly and approves the work of others. Not ticked: everything it writes or edits goes live only after someone approves it.',
            'slug' => Editorial::PERMISSION,
            'children' => [],
        ];

        // 2b. Menus a plugin or theme registered in code (falcon_add_menu_page).
        //
        // These were missing entirely, so a package could put an entry in the sidebar that no
        // administrator could grant to anybody: the capability existed only in the package's
        // own source and this screen never offered it. They merge into the same groups the
        // sidebar puts them in, so the two screens read alike. A menu registered as `public`,
        // or with `show_in_roles => false`, opts itself out.
        try {
            foreach (app(AdminMenu::class)->permissionTree() as $groupName => $items) {
                foreach ($items as $item) {
                    $dynamicPermissions[$groupName][] = $item;
                }
            }
        } catch (\Throwable $e) {
            // A package throwing while registering must not take the Roles screen down with it.
        }

        // 3. Add Custom Options Pages from config
        $customPages = config('falcon-options.pages') ?? [];
        if (!empty($customPages)) {
            $dynamicPermissions['Custom Options'] = [];
            foreach ($customPages as $slug => $page) {
                $dynamicPermissions['Custom Options'][] = [
                    'title' => $page['title'] ?? $slug,
                    'slug' => 'manage_options_'.$slug,
                    'children' => [],
                ];
            }
        }

        return [
            'dynamicPermissions' => $dynamicPermissions,
        ];
    }

    protected function generatePermissionSlug($menu, $parent = null)
    {
        // Single source of truth: the same resolver the access middleware uses
        // (Sidebar::getPermission), so a checked menu item grants exactly the
        // permission its page requires. Keeps assignment and enforcement in sync.
        return (new Sidebar)->getPermission($menu);
    }

    public function destroy(Role $role)
    {
        // The administrator slugs (Role::ADMIN_SLUGS, legacy "admin" included) and the
        // default role for new accounts are system roles.
        if (Role::isAdminSlug($role->slug) || $role->slug === 'subscriber') {
            return redirect()->route('admin.roles.index')->with('error', 'Cannot delete system roles.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
    }
}
