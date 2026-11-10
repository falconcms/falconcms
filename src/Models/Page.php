<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Builder;

class Page extends Post
{
    protected $table = 'posts';

    protected static function booted()
    {
        parent::booted(); // Post's hooks (forgetting the remembered page parents on save)

        static::addGlobalScope('page', function (Builder $builder) {
            $builder->where('type', 'page');
        });

        static::creating(function ($page) {
            $page->type = 'page';
        });
    }

    public function parent()
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('menu_order');
    }
}
