<?php

namespace FalconCms\Core\Models;

use App\Models\User;
use FalconCms\Core\Traits\HasEditorialWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $type
 * @property string $status
 * @property string|null $lang_code
 * @property int|null $user_id
 * @property int|null $menu_order
 * @property Carbon|null $published_at
 * @property string|null $content
 * @property string|null $excerpt
 * @property string|null $featured_image
 * @property array<string, mixed>|null $seo_meta
 * @property string|null $editor_type
 * @property int|null $origin_id
 * @property int|null $parent_id
 * @property int|null $menu_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property array<int, mixed>|string|null $gallery
 * @property-read ProductData|null $shopData
 */
class Post extends Model
{
    use HasEditorialWorkflow, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'title', 'slug', 'content', 'excerpt', 'type', 'status',
        'featured_image', 'gallery', 'seo_meta', 'published_at', 'editor_type',
        'lang_code', 'origin_id', 'template', 'password', 'comment_status',
        // parent_id is the real column (a page's parent page). 'post_parent' was listed here
        // instead, a name no column has, so a parent chosen in the editor was dropped on save.
        'menu_order', 'parent_id', 'post_parent', 'is_sticky',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'seo_meta' => 'array',
        'gallery' => 'array',
    ];

    protected static function booted()
    {
        // Page addresses are built from the parents remembered for the request
        // (falcon_page_ancestors); a page saved or removed mid-request makes them stale.
        $forget = static fn () => app()->forgetInstance('falcon.memo.page_parents');
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Calculate SEO score percentage (0-100)
     */
    public function getSeoScore(): int
    {
        $meta = is_array($this->seo_meta) ? $this->seo_meta : [];
        $score = 0;

        // Lengths are counted in characters, as the editor's counter and Google count them.
        // strlen() counted bytes, so one en dash or curly quote pushed a 60-character title
        // over the limit and halved its points.

        // Title: 30% (Fallback to post title if meta title is empty)
        $title = !empty($meta['title']) ? $meta['title'] : ($this->title ?? '');
        if (!empty($title)) {
            $len = mb_strlen($title);
            if ($len >= 50 && $len <= 60) {
                $score += 30;
            } elseif ($len > 0) {
                $score += 15;
            }
        }

        // Description: 40%
        if (!empty($meta['description'])) {
            $len = mb_strlen($meta['description']);
            if ($len >= 150 && $len <= 160) {
                $score += 40;
            } elseif ($len > 0) {
                $score += 20;
            }
        }

        // Focus keyword: 10%. The editor's Focus Keyword field is what the analysis works
        // from; the old meta keywords field is still honoured for content that has one.
        if (!empty($meta['focus_keyword']) || !empty($meta['keywords'])) {
            $score += 10;
        }

        // Share image: 20%. The page shares the same image seo-meta prints: its own social
        // image, else its featured image, else the site's default — a page with any of them
        // is not missing one.
        if (!empty($meta['og_image']) || !empty($this->featured_image) || !empty(get_cms_option('seo_default_image'))) {
            $score += 20;
        }

        return $score;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereHas('user', function ($q) {
                $q->where('is_blocked', false);
            });
    }

    /**
     * Decide the effective publish status from a publish date, on the SERVER (timezone-safe).
     * A future publish date ⇒ "scheduled"; a past/now date ⇒ "published". Drafts stay drafts, and
     * work waiting for review stays there: the date a writer picked is a suggestion until approved.
     * This avoids relying on the browser clock, which breaks when the app timezone (UTC) differs
     * from the editor's local timezone (e.g. a +2 min bump still looked "past" to the browser).
     */
    public static function resolveStatusForSchedule(?string $status, $publishedAt): ?string
    {
        if (in_array($status, ['draft', 'pending'], true) || empty($publishedAt)) {
            return $status;
        }
        try {
            return Carbon::parse($publishedAt)->isFuture() ? 'scheduled' : 'published';
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

    /**
     * The flattened attribute index this product is filterable by. Derived data — see
     * falcon_sync_product_attribute_index().
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class, 'post_id');
    }

    public function productTags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_tag_post', 'post_id', 'product_tag_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
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
        if ($this->lang_code === $locale) {
            return $this;
        }

        $origin_id = $this->origin_id ?: $this->id;

        return Post::where('lang_code', $locale)
            ->where(function ($q) use ($origin_id) {
                $q->where('id', $origin_id)->orWhere('origin_id', $origin_id);
            })->first();
    }

    /**
     * eCommerce Relationship
     */
    /** @return HasOne<ProductData, $this> */
    public function shopData(): HasOne
    {
        return $this->hasOne(ProductData::class, 'post_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'post_id')->whereNull('parent_id')->where('is_approved', true);
    }

    public function getPriceAttribute()
    {
        return $this->shopData?->price ?? 0;
    }

    public function getSalePriceAttribute()
    {
        // The live one: templates reading $post->sale_price must never show a sale that has
        // already ended. The admin reads $post->shopData->sale_price, which stays untouched.
        return $this->shopData?->active_sale_price;
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
        // No shop row at all: nothing is being tracked, so nothing can be out of stock.
        if (!$this->shopData) {
            return true;
        }

        // The rule lives on ProductData so the badge, the archive filter and the add-to-cart
        // check cannot drift apart.
        return $this->shopData->isInStock();
    }
}
