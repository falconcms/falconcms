<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

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
 * @property int|null $duration_seconds
 * @property bool $human
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
        'browser', 'os', 'device_type', 'country', 'country_code', 'city', 'human',
    ];

    /**
     * Every figure the dashboard shows counts real visitors only: rows a browser confirmed
     * (see HumanVisits). Code that has to see every row — recording, confirming, pruning —
     * asks for Analytics::withoutGlobalScope('human').
     */
    protected static function booted()
    {
        static::addGlobalScope('human', function ($query) {
            if (self::hasHumanColumn()) {
                $query->where($query->getModel()->getTable().'.human', true);
            }
        });
    }

    /** Is the column there? (Code can be updated a moment before its migration runs.) */
    public static function hasHumanColumn(): bool
    {
        // Only a yes is remembered: a process that started before the migration ran (a queue
        // worker, the test app) has to notice the column the moment it appears.
        $memo = falcon_request_memo('analytics_human_column');
        if (!empty($memo['v'])) {
            return true;
        }
        try {
            $memo['v'] = Schema::hasColumn('cms_analytics', 'human');
        } catch (\Throwable $e) {
            return false;
        }

        return (bool) $memo['v'];
    }
}
