<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TaxonomyTerm extends Model
{
    protected $table = 'taxonomy_terms';
    protected $fillable = [
        'taxonomy_slug', 'cpt_slug', 'parent_id', 'name', 'slug',
        'lang_code', 'origin_id', 'description', 'order',
    ];

    public function parent()
    {
        return $this->belongsTo(TaxonomyTerm::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(TaxonomyTerm::class, 'parent_id');
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_taxonomy_term', 'taxonomy_term_id', 'post_id');
    }

    public function getFullSlugPath()
    {
        $path = [$this->slug];
        $parent = $this->parent;
        while ($parent) {
            array_unshift($path, $parent->slug);
            $parent = $parent->parent;
        }
        return implode('/', $path);
    }

    public function translations()
    {
        $originId = $this->origin_id ?: $this->id;
        return $this->hasMany(TaxonomyTerm::class, 'origin_id', 'id')
               ->orWhere('id', $originId)
               ->orWhere('origin_id', $originId);
    }

    public function getTranslation($locale)
    {
        if ($this->lang_code === $locale) return $this;
        $originId = $this->origin_id ?: $this->id;
        return TaxonomyTerm::where('lang_code', $locale)
                    ->where(function($q) use ($originId) {
                        $q->where('id', $originId)->orWhere('origin_id', $originId);
                    })->first();
    }

    public static function generateUniqueSlug($name, $id = 0, $cptSlug = null, $langCode = 'en')
    {
        // If string contains non-ascii characters OR lang is not english, use native slug logic
        if (($langCode && $langCode !== 'en') || preg_match('/[^\x00-\x7F]/', $name)) {
            $slug = mb_strtolower($name, 'UTF-8');
            $slug = str_replace(' ', '-', trim($slug));
            // Keep letters (\p{L}), marks/vowels (\p{M}), numbers (\p{N}), and dashes.
            $slug = preg_replace('/[^\p{L}\p{M}\p{N}\-]+/u', '', $slug);
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
        } else {
            $slug = Str::slug($name);
        }

        $slug = $slug ?: 'term';
        $originalSlug = $slug;
        $count = 1;
        while (static::where('slug', $slug)
            ->where('id', '!=', $id)
            ->when($cptSlug, function($q) use ($cptSlug) {
                return $q->where('cpt_slug', $cptSlug);
            })->exists()) {
            $slug = "{$originalSlug}-" . ($count++);
        }
        return $slug;
    }
}
