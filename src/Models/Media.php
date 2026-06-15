<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'filename', 'path', 'mime_type', 'width', 'height',
        'original_size', 'compressed_size', 'alt_text', 
        'title', 'caption', 'description', 'user_id'
    ];

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->path);
    }
}
