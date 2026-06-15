<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Revision extends Model
{
    protected $table = 'cms_revisions';

    protected $fillable = [
        'revisionable_type', 'revisionable_id', 'user_id', 'type', 'title', 'content', 'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /** How many manual revisions to keep per item (oldest pruned beyond this). */
    public const KEEP = 30;

    public function revisionable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'user_id');
    }

    /**
     * Capture a snapshot of a model's current content.
     *
     * @param  Model   $model  The Post/Page being saved.
     * @param  string  $type   'revision' (manual save) or 'autosave'.
     * @param  array   $extra  Optional extra fields to store in `data`.
     */
    public static function snapshot(Model $model, string $type = 'revision', array $extra = []): ?self
    {
        // Defensive: never let revision-taking break a save.
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('cms_revisions')) return null;

            $userId = function_exists('auth') && auth()->check() ? auth()->id() : null;

            if ($type === 'autosave') {
                // Keep only the latest autosave per item — update in place.
                $rev = static::where('revisionable_type', $model->getMorphClass())
                    ->where('revisionable_id', $model->getKey())
                    ->where('type', 'autosave')
                    ->first();
                $rev = $rev ?: new static([
                    'revisionable_type' => $model->getMorphClass(),
                    'revisionable_id'   => $model->getKey(),
                    'type'              => 'autosave',
                ]);
                $rev->user_id = $userId;
                $rev->title   = $model->title ?? null;
                $rev->content = $model->content ?? null;
                $rev->data    = $extra ?: null;
                $rev->save();
                return $rev;
            }

            // Skip a duplicate snapshot if nothing changed since the last revision.
            $last = static::where('revisionable_type', $model->getMorphClass())
                ->where('revisionable_id', $model->getKey())
                ->where('type', 'revision')
                ->orderByDesc('id')->first();
            if ($last && $last->content === ($model->content ?? null) && $last->title === ($model->title ?? null)) {
                return $last;
            }

            $rev = static::create([
                'revisionable_type' => $model->getMorphClass(),
                'revisionable_id'   => $model->getKey(),
                'user_id'           => $userId,
                'type'              => 'revision',
                'title'             => $model->title ?? null,
                'content'           => $model->content ?? null,
                'data'              => $extra ?: null,
            ]);

            // Prune older manual revisions beyond KEEP.
            $ids = static::where('revisionable_type', $model->getMorphClass())
                ->where('revisionable_id', $model->getKey())
                ->where('type', 'revision')
                ->orderByDesc('id')
                ->skip(static::KEEP)->take(100)
                ->pluck('id');
            if ($ids->isNotEmpty()) static::whereIn('id', $ids)->delete();

            return $rev;
        } catch (\Throwable $e) {
            // Swallow — revisions are non-critical; a failure must not block the actual save.
            return null;
        }
    }

    /** Discard the autosave row for a model (after a real save / restore). */
    public static function clearAutosave(Model $model): void
    {
        try {
            static::where('revisionable_type', $model->getMorphClass())
                ->where('revisionable_id', $model->getKey())
                ->where('type', 'autosave')
                ->delete();
        } catch (\Throwable $e) {}
    }
}
