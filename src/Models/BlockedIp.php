<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    protected $fillable = ['ip_address', 'country', 'country_code', 'city', 'region', 'isp', 'attempts', 'reason'];
}
