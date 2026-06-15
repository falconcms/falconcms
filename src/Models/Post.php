<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'user_id', 'title', 'slug', 'content', 'excerpt', 'type', 'status',
        'featured_image', 'gallery', 'seo_meta', 'published_at', 'editor_type',
        'lang_code', 'origin_id', 'template', 'password', 'comment_status',
        'menu_order', 'post_parent', 'is_sticky',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'seo_meta' => 'array',
        'gallery' => 'array',
    ];

    /**
     * Calculate SEO score percentage (0-100)
     */
    public function getSeoScore(): int
    {
        $meta = is_array($this->seo_meta) ? $this->seo_meta : [];
        $score = 0;

        // Title: 30% (Fallback to post title if meta title is empty)
        $title = !empty($meta['title']) ? $meta['title'] : ($this->title ?? '');
        if (!empty($title)) {
            $len = strlen($title);
            if ($len >= 50 && $len <= 60) $score += 30;
            elseif ($len > 0) $score += 15;
        }

        // Description: 40%
        if (!empty($meta['description'])) {
            $len = strlen($meta['description']);
            if ($len >= 150 && $len <= 160) $score += 40;
            elseif ($len > 0) $score += 20;
        }

        // Keywords: 10%
        if (!empty($meta['keywords'])) $score += 10;

        // OG Image: 20%
        if (!empty($meta['og_image'])) $score += 20;

        return $score;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
                     ->whereHas('user', function($q) {
                         $q->where('is_blocked', false);
                     });
    }

    /**
     * Decide the effective publish status from a publish date, on the SERVER (timezone-safe).
     * A future publish date ⇒ "scheduled"; a past/now date ⇒ "published". Drafts stay drafts.
     * This avoids relying on the browser clock, which breaks when the app timezone (UTC) differs
     * from the editor's local timezone (e.g. a +2 min bump still looked "past" to the browser).
     */
    public static function resolveStatusForSchedule(?string $status, $publishedAt): ?string
    {
        if ($status === 'draft' || empty($publishedAt)) {
            return $status;
        }
        try {
            return \Illuminate\Support\Carbon::parse($publishedAt)->isFuture() ? 'scheduled' : 'published';
        } catch (\Throwable $e) {
            return $status;
        }
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function postTypeDefinition(): BelongsTo
    {
        return $this->belongsTo(PostType::class, 'type', 'slug');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function taxonomyTerms(): BelongsToMany
    {
        return $this->belongsToMany(TaxonomyTerm::class, 'post_taxonomy_term', 'post_id', 'taxonomy_term_id');
    }

    public function productCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_post', 'post_id', 'product_category_id');
    }

    public function productTags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_tag_post', 'post_id', 'product_tag_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->where('is_approved', true);
    }

    /**
     * Get all translations/clones for this post.
     */
    public function translations()
    {
        $originId = $this->origin_id ?: $this->id;
        return $this->hasMany(Post::class, 'origin_id', 'id') // Clones of this original
               ->orWhere('id', $originId) // The original itself
               ->orWhere('origin_id', $originId); // Other clones of same original
    }

    /**
     * Get specific translation for a locale.
     */
    public function getTranslation($locale)
    {
        if ($this->lang_code === $locale) return $this;
        
        $origin_id = $this->origin_id ?: $this->id;
        return Post::where('lang_code', $locale)
                    ->where(function($q) use ($origin_id) {
                        $q->where('id', $origin_id)->orWhere('origin_id', $origin_id);
                    })->first();
    }

    /**
     * eCommerce Relationship
     */
    public function shopData()
    {
        return $this->hasOne(ProductData::class, 'post_id');
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Review::class, 'post_id')->whereNull('parent_id')->where('is_approved', true);
    }

    public function getPriceAttribute()
    {
        return $this->shopData?->price ?? 0;
    }

    public function getSalePriceAttribute()
    {
        return $this->shopData?->sale_price;
    }

    public function getSkuAttribute()
    {
        return $this->shopData?->sku;
    }

    public function getStockStatusAttribute()
    {
        return $this->shopData?->stock_status ?? 'instock';
    }

    public function getIsInStockAttribute()
    {
        if (!$this->shopData) {
            return true;
        }
        if ($this->shopData->stock_status === 'outofstock') {
            return false;
        }
        // Stock-quantity gating only applies when stock management is on globally
        // (Shop settings → Products) and for this product; the out-of-stock floor
        // is the configurable threshold (defaults to 0 = previous behavior).
        $globalManageStock = function_exists('get_shop_option') ? get_shop_option('shop_manage_stock', '1') === '1' : true;
        $outThreshold = function_exists('get_shop_option') ? (int) get_shop_option('shop_out_of_stock_threshold', '0') : 0;
        if ($globalManageStock && $this->shopData->manage_stock && (int) $this->shopData->stock_quantity <= $outThreshold) {
            return false;
        }
        return true;
    }
}
