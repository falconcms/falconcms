<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $table = 'cms_redirects';
    
    protected $fillable = [
        'old_url',
        'new_url',
        'status_code',
        'hits',
        'last_hit_at'
    ];

    protected $casts = [
        'last_hit_at' => 'datetime',
    ];
}
