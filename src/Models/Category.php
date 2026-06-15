<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'lang_code', 'origin_id'];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($category) {
            if (empty($category->slug)) {
                $category->slug = static::generateUniqueSlug($category->name, $category->id ?? 0, $category->lang_code ?? 'en');
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

        $slug = $slug ?: 'category';
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
        return $this->hasMany(Category::class, 'origin_id', 'id')
               ->orWhere('id', $originId)
               ->orWhere('origin_id', $originId);
    }

    public function getTranslation($locale)
    {
        if ($this->lang_code === $locale) return $this;
        $originId = $this->origin_id ?: $this->id;
        return Category::where('lang_code', $locale)
                    ->where(function($q) use ($originId) {
                        $q->where('id', $originId)->orWhere('origin_id', $originId);
                    })->first();
    }

    public function parent(): BelongsTo { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Category::class, 'parent_id'); }
    public function posts(): BelongsToMany { return $this->belongsToMany(Post::class); }

    public function getFullSlugPath()
    {
        $path = $this->slug;
        $parent = $this->parent;
        while ($parent) {
            $path = $parent->slug . '/' . $path;
            $parent = $parent->parent;
        }
        return $path;
    }
}
