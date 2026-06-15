<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'lang_code', 'origin_id'];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = static::generateUniqueSlug($tag->name, $tag->id ?? 0, $tag->lang_code ?? 'en');
            }
        });
    }

    public static function generateUniqueSlug($name, $id = 0, $langCode = 'en')
    {
        // If string contains non-ascii characters OR lang is not english, use native slug logic
        if ($langCode !== 'en' || preg_match('/[^\x00-\x7F]/', $name)) {
            $slug = mb_strtolower($name, 'UTF-8');
            $slug = str_replace(' ', '-', trim($slug));
            // Keep letters (\p{L}), marks/vowels (\p{M}), numbers (\p{N}), and dashes.
            $slug = preg_replace('/[^\p{L}\p{M}\p{N}\-]+/u', '', $slug);
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
        } else {
            $slug = Str::slug($name);
        }

        $slug = $slug ?: 'tag';
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)
            ->where('id', '!=', $id)
            ->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }

    public function translations()
    {
        $originId = $this->origin_id ?: $this->id;
        return $this->hasMany(Tag::class, 'origin_id', 'id')
               ->orWhere('id', $originId)
               ->orWhere('origin_id', $originId);
    }

    public function getTranslation($locale)
    {
        if ($this->lang_code === $locale) return $this;
        $originId = $this->origin_id ?: $this->id;
        return Tag::where('lang_code', $locale)
                    ->where(function($q) use ($originId) {
                        $q->where('id', $originId)->orWhere('origin_id', $originId);
                    })->first();
    }

    public function posts(): BelongsToMany { return $this->belongsToMany(Post::class); }
}
