<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $table = 'shop_reviews';
    
    protected $fillable = [
        'post_id',
        'user_id',
        'name',
        'email',
        'rating',
        'comment',
        'is_approved',
        'parent_id'
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function replies()
    {
        return $this->hasMany(Review::class, 'parent_id')->where('is_approved', true);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'parent_id');
    }
}
