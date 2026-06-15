<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomTaxonomy extends Model
{
    use SoftDeletes;

    protected $table = 'custom_taxonomies';

    protected $fillable = [
        'name', 'singular_name', 'slug', 'description',
        'post_types', 'hierarchical', 'is_active',
    ];

    protected $casts = [
        'post_types' => 'array',
        'is_active' => 'boolean',
        'hierarchical' => 'boolean',
    ];

    public function terms()
    {
        return $this->hasMany(TaxonomyTerm::class, 'taxonomy_slug', 'slug');
    }
}
