<?php

namespace Acme\CmsDashboard\Traits;

trait HasCmsPermissions
{
    public function role()
    {
        return $this->belongsTo(\Acme\CmsDashboard\Models\Role::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->role && $this->role->slug === $role;
    }

    public function isAdmin(): bool
    {
        // Fast path: the seeded admin roles (1 = administrator, 6 = super-admin).
        if (in_array($this->role_id, [1, 6])) return true;

        if (!$this->role_id) return false;

        static $adminCheck = [];
        if (isset($adminCheck[$this->role_id])) return $adminCheck[$this->role_id];
        
        $slug = \Illuminate\Support\Facades\DB::table('roles')->where('id', $this->role_id)->value('slug');
        $res = in_array($slug, ['super-admin', 'administrator', 'admin']);
        
        return $adminCheck[$this->role_id] = $res;
    }

    public function hasPermission(string $permission): bool
    {
        if (!$this->role_id) return false;

        if ($this->isAdmin()) {
            return true;
        }

        // 2. Check role's permissions from DB
        return \Illuminate\Support\Facades\DB::table('role_permission')
            ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
            ->where('role_permission.role_id', $this->role_id)
            ->where('permissions.slug', $permission)
            ->exists();
    }

    public function hasCmsPermission(string $permission): bool
    {
        return $this->hasPermission($permission);
    }
}
