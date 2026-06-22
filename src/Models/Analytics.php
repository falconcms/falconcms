<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Analytics extends Model
{
    protected $table = 'cms_analytics';
    
    public $timestamps = false;

    protected $fillable = [
        'ip_address', 'url', 'referrer', 'user_agent',
        'browser', 'os', 'device_type', 'country', 'country_code', 'city'
    ];
}
