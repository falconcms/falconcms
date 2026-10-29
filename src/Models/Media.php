<?php

namespace FalconCms\Core\Models;

use FalconCms\Core\Support\CustomFonts\FontFile;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int|null $folder_id
 * @property-read string $url
 */
class Media extends Model
{
    protected $fillable = [
        'filename', 'path', 'mime_type', 'width', 'height',
        'original_size', 'compressed_size', 'alt_text',
        'title', 'caption', 'description', 'user_id', 'folder_id',
    ];

    protected $casts = ['folder_id' => 'integer'];

    public function getUrlAttribute()
    {
        return asset('storage/'.$this->path);
    }

    /**
     * Fonts in the library: of one format ('woff2', 'woff', 'ttf', 'otf'), or of any of them.
     * Used by the Media Library's font mode (Customizer → Typography → Custom Font).
     */
    public function scopeFonts(Builder $query, string $format = ''): Builder
    {
        $exts = in_array($format, FontFile::FORMATS, true) ? [$format] : FontFile::FORMATS;

        return $query->where(function ($q) use ($exts) {
            foreach ($exts as $ext) {
                $q->orWhere('path', 'like', '%.'.$ext);
            }
        });
    }
}
