<?php

namespace FalconCms\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
