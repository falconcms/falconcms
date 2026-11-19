<?php

namespace FalconCms\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A set of permissions a user can hold. Spelled out because static analysis cannot see
 * through Eloquent's `__get`.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property-read Collection<int, Permission> $permissions
 */
class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    /**
     * Role slugs that make their holders administrators (HasCmsPermissions::isAdmin,
     * AdminMiddleware). Reserved: no role may be created with one or re-slugged to one from
     * the Roles screen, and a role that has one keeps it. "admin" is a legacy alias that no
     * FalconCMS version seeds; it stays recognised so an installation that created such a
     * role by hand keeps working, but it can no longer be minted to gain administrator access.
     */
    public const ADMIN_SLUGS = ['super-admin', 'administrator', 'admin'];

    public static function isAdminSlug(?string $slug): bool
    {
        return in_array((string) $slug, self::ADMIN_SLUGS, true);
    }

    /**
     * Every permission slug the given roles grant together (their union), sorted. That union
     * is what an account holding all of them can do (HasCmsPermissions::hasPermission).
     *
     * @param  array<int|string>  $roleIds
     * @return list<string>
     */
    public static function permissionSlugsFor(array $roleIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $roleIds))));
        if ($ids === []) {
            return [];
        }

        return DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->whereIn('role_permission.role_id', $ids)
            ->distinct()
            ->orderBy('permissions.slug')
            ->pluck('permissions.slug')
            ->map(fn ($slug) => (string) $slug)
            ->all();
    }

    /**
     * Why $actor may not give an account the roles $roleIds (primary and extra roles alike),
     * or null. An administrator may give any role; the Super Admin rule is the caller's.
     * Anyone else may give no administrator role, and no set of roles that together grants a
     * permission the actor does not hold: a role is only as safe to hand out as everything
     * it grants, so the account it lands on can never do more than the one that created it.
     * An unknown role id, or an actor whose permissions cannot be read, is refused.
     *
     * @param  array<int|string>  $roleIds
     */
    public static function assignmentError(?object $actor, array $roleIds): ?string
    {
        if ($actor === null || !method_exists($actor, 'hasPermission')) {
            return 'You are not allowed to assign roles.';
        }
        if (method_exists($actor, 'isAdmin') && $actor->isAdmin()) {
            return null;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $roleIds))));
        if (static::whereIn('id', $ids)->count() !== count($ids)) {
            return 'Unknown role.';
        }
        if (static::whereIn('id', $ids)->whereIn('slug', self::ADMIN_SLUGS)->exists()) {
            return 'Only an administrator can assign an administrator role.';
        }

        $notHeld = array_values(array_filter(static::permissionSlugsFor($ids), fn ($slug) => !$actor->hasPermission($slug)));
        if ($notHeld !== []) {
            return 'You can only assign roles whose permissions you hold yourself. Not held: '
                .implode(', ', array_slice($notHeld, 0, 10)).(count($notHeld) > 10 ? ', …' : '').'.';
        }

        return null;
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
