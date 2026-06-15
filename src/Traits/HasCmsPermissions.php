<?php

namespace FalconCms\Core\Traits;

use Illuminate\Support\Facades\DB;

trait HasCmsPermissions
{
    /** Per-request cache of the user's effective permission slugs (slug => true). */
    protected $cmsPermissionSet = null;

    /** Per-request cache of the user's effective role ids. */
    protected $cmsRoleIds = null;

    /** Primary role (backward compatible — $user->role keeps working). */
    public function role()
    {
        return $this->belongsTo(\FalconCms\Core\Models\Role::class);
    }

    /** All roles assigned to the user (primary role_id + the role_user pivot). */
    public function roles()
    {
        return $this->belongsToMany(\FalconCms\Core\Models\Role::class, 'role_user');
    }

    /**
     * Effective role ids: the primary role_id plus every role in the pivot, de-duplicated.
     * Falls back to the primary role alone if the pivot table doesn't exist yet.
     */
    public function cmsRoleIds(): array
    {
        if ($this->cmsRoleIds !== null) return $this->cmsRoleIds;

        $ids = [];
        if ($this->role_id) $ids[] = (int) $this->role_id;

        try {
            foreach (DB::table('role_user')->where('user_id', $this->id)->pluck('role_id') as $rid) {
                $ids[] = (int) $rid;
            }
        } catch (\Throwable $e) {
            // role_user not migrated yet — primary role only.
        }

        return $this->cmsRoleIds = array_values(array_unique(array_filter($ids)));
    }

    /** True if ANY of the user's roles matches the given slug. */
    public function hasRole(string $role): bool
    {
        $ids = $this->cmsRoleIds();
        if (empty($ids)) return false;

        return DB::table('roles')->whereIn('id', $ids)->where('slug', $role)->exists();
    }

    /** True if ANY of the user's roles is an administrator / super-admin. */
    public function isAdmin(): bool
    {
        $ids = $this->cmsRoleIds();
        if (empty($ids)) return false;

        // Fast path: seeded admin roles (1 = administrator, 6 = super-admin).
        if (array_intersect([1, 6], $ids)) return true;

        static $adminCheck = [];
        $key = implode(',', $ids);
        if (isset($adminCheck[$key])) return $adminCheck[$key];

        $isAdmin = DB::table('roles')
            ->whereIn('id', $ids)
            ->whereIn('slug', ['super-admin', 'administrator', 'admin'])
            ->exists();

        return $adminCheck[$key] = $isAdmin;
    }

    /**
     * True if the user has the permission through ANY of their roles. The full effective
     * permission set is loaded once per request and cached (Spatie-style), so repeated
     * checks are O(1) lookups rather than a query each.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) return true;

        if ($this->cmsPermissionSet === null) {
            $this->cmsPermissionSet = [];
            $ids = $this->cmsRoleIds();
            if (!empty($ids)) {
                $slugs = DB::table('role_permission')
                    ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
                    ->whereIn('role_permission.role_id', $ids)
                    ->pluck('permissions.slug');
                foreach ($slugs as $slug) {
                    $this->cmsPermissionSet[$slug] = true;
                }
            }
        }

        return isset($this->cmsPermissionSet[$permission]);
    }

    public function hasCmsPermission(string $permission): bool
    {
        return $this->hasPermission($permission);
    }

    /** Personal API tokens belonging to this user. */
    public function apiTokens()
    {
        return $this->hasMany(\FalconCms\Core\Models\ApiToken::class);
    }

    /**
     * Create a new API token and return the PLAINTEXT value (shown to the user once).
     * Only the SHA-256 hash is stored.
     */
    public function createApiToken(string $name): string
    {
        $plain = \Illuminate\Support\Str::random(48);
        $this->apiTokens()->create([
            'name'  => $name ?: 'API Token',
            'token' => hash('sha256', $plain),
        ]);
        return $plain;
    }

    /**
     * Assign a set of role ids: the first becomes the primary role_id (backward compat)
     * and the whole set is synced into the role_user pivot. Clears the per-request caches.
     */
    public function assignRoles(array $roleIds): void
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));

        $this->role_id = $roleIds[0] ?? $this->role_id;
        $this->save();
        $this->roles()->sync($roleIds);

        $this->cmsRoleIds = null;
        $this->cmsPermissionSet = null;
    }
}
