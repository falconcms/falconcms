<?php

namespace FalconCms\Core\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request)
    {
        $currentLocale = app()->getLocale();
        $translation = $this->getTranslation($currentLocale);

        $data = [
            'id' => $this->id,
            'title' => $translation ? $translation->title : $this->title,
            'slug' => $translation && $translation->slug ? $translation->slug : $this->slug,
            'content' => $translation ? $translation->content : ($this->editor_type === 'builder' ? get_lazy_content($this->content) : $this->content),
            'excerpt' => $translation && $translation->excerpt ? $translation->excerpt : get_falcon_excerpt($this, 160),
            'featured_image' => $this->featured_image ? url('storage/' . $this->featured_image) : null,
            'status' => $this->status,
            'type' => $this->type,
            'author' => [
                'name' => $this->user->name ?? 'Admin',
                'id' => $this->user_id
            ],
            'categories' => $this->categories->map(function($cat) {
                return [
                    'name' => $cat->name,
                    'slug' => $cat->slug
                ];
            }),
            'tags' => $this->tags->map(function($tag) {
                return [
                    'name' => $tag->name,
                    'slug' => $tag->slug
                ];
            }),
            'published_at' => $this->created_at->format('Y-m-d H:i:s'),
            'seo' => $this->seo_meta,
            // Dynamic: all custom fields assigned to this post's type (auto-detected from
            // the field groups, so adding/removing a field updates the API automatically).
            'custom_fields' => get_post_custom_fields($this->resource),
        ];

        return apply_falcon_filters('lazy_api_post_data', $data, $this->resource);
    }
}
