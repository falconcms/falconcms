<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class PostTranslation extends Model
{
    protected $fillable = [
        'post_id', 'locale', 'slug', 'title', 'content', 
        'excerpt', 'meta_title', 'meta_description'
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
