<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $table = 'cms_languages';
    protected $fillable = ['name', 'code', 'flag', 'is_default', 'status'];
}
