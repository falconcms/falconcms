<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $filename
 * @property string $path
 * @property string|null $mime_type
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt_text
 * @property string|null $title
 * @property-read string $url
 */
class Media extends Model
{
    protected $fillable = [
        'filename', 'path', 'mime_type', 'width', 'height',
        'original_size', 'compressed_size', 'alt_text',
        'title', 'caption', 'description', 'user_id',
    ];

    public function getUrlAttribute()
    {
        return asset('storage/'.$this->path);
    }
}
