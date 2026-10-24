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

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
