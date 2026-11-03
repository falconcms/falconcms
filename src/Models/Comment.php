<?php

namespace FalconCms\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $user_id
 * @property string|null $name
 * @property string|null $email
 * @property string $comment
 * @property bool $is_approved
 * @property bool $is_read
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Comment extends Model
{
    protected $fillable = [
        'post_id',
        'user_id',
        'name',
        'email',
        'comment',
        'is_approved',
        'is_read',
        'parent_id',
    ];

    protected static function booted(): void
    {
        // The post's cached page shows this comment list; it is stale the moment it changes.
        $forget = static function (Comment $comment): void {
            if ($comment->post_id && function_exists('falcon_forget_post_page_cache')) {
                falcon_forget_post_page_cache($comment->post_id);
            }
        };
        static::saved($forget);
        static::deleted($forget);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->where('is_approved', true);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }
}
