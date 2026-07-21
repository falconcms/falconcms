<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Activation record for a drop-in plugin. The plugin's files live in the app's
 * plugins/<slug>/ directory; this row just tracks install/active state and the
 * version that was active (so updates can be detected). Metadata (name, author,
 * description…) comes from the plugin's plugin.json at runtime, not this table.
 */
class Plugin extends Model
{
    protected $fillable = [
        'slug',
        'version',
        'is_active',
        'activated_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'activated_at' => 'datetime',
    ];
}
