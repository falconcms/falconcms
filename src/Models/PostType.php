<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostType extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'singular_name', 'slug', 'description', 'icon', 'is_builtin', 'is_active', 'show_in_menu', 'is_public', 'supports'];

    protected $casts = [
        'supports' => 'array',
        'is_builtin' => 'boolean',
        'is_active' => 'boolean',
        'show_in_menu' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'type', 'slug');
    }
}
