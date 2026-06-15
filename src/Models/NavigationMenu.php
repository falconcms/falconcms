<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationMenu extends Model
{
    protected $fillable = [
        'name', 'slug', 'location', 'lang_code', 'is_header', 'is_footer',
    ];

    public function items()
    {
        return $this->hasMany(NavigationMenuItem::class)->whereNull('parent_id')->orderBy('order');
    }

    public function allItems()
    {
        return $this->hasMany(NavigationMenuItem::class)->orderBy('order');
    }
}
