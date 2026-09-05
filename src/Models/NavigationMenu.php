<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $location
 * @property string|null $lang_code
 * @property bool|null $is_header
 * @property bool|null $is_footer
 * @property-read Collection<int, NavigationMenuItem> $items
 * @property-read Collection<int, NavigationMenuItem> $allItems
 */
class NavigationMenu extends Model
{
    protected $fillable = [
        'name', 'slug', 'location', 'lang_code', 'is_header', 'is_footer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(NavigationMenuItem::class)->whereNull('parent_id')->orderBy('order');
    }

    public function allItems(): HasMany
    {
        return $this->hasMany(NavigationMenuItem::class)->orderBy('order');
    }
}
