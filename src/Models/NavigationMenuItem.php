<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $navigation_menu_id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $url
 * @property string|null $type
 * @property int|null $object_id
 * @property string|null $target
 * @property string|null $classes
 * @property string|null $icon
 * @property bool|null $show_only_icon
 * @property int|null $order
 * @property int|null $mega_menu_id
 * @property-read Collection<int, NavigationMenuItem> $children
 * @property-read NavigationMenuItem|null $parent
 * @property-read NavigationMenu|null $menu
 */
class NavigationMenuItem extends Model
{
    protected $fillable = [
        'navigation_menu_id', 'parent_id', 'title', 'url', 'type',
        'object_id', 'target', 'classes', 'icon', 'show_only_icon',
        'order', 'mega_menu_id',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(NavigationMenu::class, 'navigation_menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(NavigationMenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(NavigationMenuItem::class, 'parent_id')->orderBy('order');
    }
}
