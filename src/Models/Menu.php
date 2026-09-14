<?php

namespace FalconCms\Core\Models;

use FalconCms\Core\Support\MenuPlacement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row of the admin sidebar. Top-level items have a null `parent_id`; their children are
 * the submenu. Position is `order` within a `group`, and {@see MenuPlacement} is what
 * decides it.
 *
 * The columns are spelled out for the same reason as on {@see PostType}: static analysis
 * cannot see through Eloquent's `__get`.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $route a named route, or a raw path/URL
 * @property string|null $params JSON query parameters for a named route
 * @property string|null $icon a Material Symbols name, or a raw <svg> blob
 * @property string|null $group sidebar section; 'Main' draws without a heading
 * @property int $order
 * @property string|null $permission
 * @property Collection<int, Menu> $children
 */
class Menu extends Model
{
    protected $fillable = ['parent_id', 'title', 'route', 'icon', 'group', 'order', 'permission'];

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }
}
