<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Widget extends Model
{
    protected $fillable = [
        'area', 'type', 'title', 'lang_code', 'settings', 'order', 'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Scope a query to only include active widgets in a specific area.
     */
    public function scopeForArea($query, $area)
    {
        return $query->where('area', $area)
                     ->where('is_active', true)
                     ->orderBy('order');
    }
}
