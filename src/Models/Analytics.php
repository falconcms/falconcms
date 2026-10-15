<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $ip_address
 * @property string|null $url
 * @property string|null $referrer
 * @property string|null $user_agent
 * @property string|null $browser
 * @property string|null $os
 * @property string|null $device_type
 * @property string|null $country
 * @property string|null $country_code
 * @property string|null $city
 * @property Carbon|null $created_at
 */
class Analytics extends Model
{
    protected $table = 'cms_analytics';

    public $timestamps = false;

    /**
     * created_at is the only timestamp here and Eloquent does not manage it, so without
     * this it comes back as a raw string — which compares as text, not as a moment.
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected $fillable = [
        'ip_address', 'url', 'referrer', 'user_agent',
        'browser', 'os', 'device_type', 'country', 'country_code', 'city',
    ];
}
