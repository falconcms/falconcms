<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class ProductData extends Model
{
    protected $table = 'shop_products';
    protected $fillable = [
        'post_id', 'type', 'tax_status', 'attributes_data', 'price', 'sale_price', 'sale_ends_at',
        'is_downloadable', 'download_expiry_days',
        'sku', 'stock_quantity', 'stock_status', 'manage_stock', 'product_type',
        'upsell_ids', 'cross_sell_ids',
        'short_description', 'attributes',
        'weight', 'length', 'width', 'height', 'backorders',
    ];

    /** The only values the tax engine understands; anything else is coerced to 'taxable'. */
    public const TAX_STATUSES = ['taxable', 'shipping', 'none'];

    /**
     * Backorder policy:
     *   'no'     — cannot be bought once stock runs out
     *   'notify' — can be bought, and the shopper is told it is on backorder
     *   'yes'    — can be bought with no notice
     */
    public const BACKORDER_MODES = ['no', 'notify', 'yes'];

    /**
     * "10 × 5 × 2" from the three stored measurements, skipping any that were left blank.
     * The product page has always asked for this; until now there was nothing behind it.
     */
    public function getDimensionsAttribute(): ?string
    {
        $trim  = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        $parts = array_map($trim, array_filter(
            [$this->length, $this->width, $this->height],
            static fn ($v) => $v !== null && (float) $v > 0
        ));

        return $parts ? implode(' × ', $parts) : null;
    }

    /** Is this product being sold ahead of stock right now? */
    public function isOnBackorder(): bool
    {
        if (!$this->manage_stock || !$this->allowsBackorders()) {
            return false;
        }

        $floor = function_exists('get_shop_option') ? (int) get_shop_option('shop_out_of_stock_threshold', '0') : 0;

        return (int) $this->stock_quantity <= $floor;
    }

    /** Should the shopper be told? The 'yes' mode backorders silently. */
    public function showsBackorderNotice(): bool
    {
        return $this->backorders === 'notify' && $this->isOnBackorder();
    }

    /** Can this product still be sold once stock reaches the out-of-stock floor? */
    public function allowsBackorders(): bool
    {
        return in_array($this->backorders, ['notify', 'yes'], true);
    }

    /**
     * Is this a variable product?
     *
     * The table carries two columns for this — `type` and `product_type`. The admin only ever
     * writes `type`, so `product_type` drifts on any row saved through the editor; rows created
     * by imports or by hand often set only the other one. Until they are reconciled, either
     * saying "variable" has to count, or the same product is variable on one screen and simple
     * on the next.
     */
    public function isVariable(): bool
    {
        return ($this->type ?? null) === 'variable'
            || ($this->product_type ?? null) === 'variable';
    }

    /** Products whose type says variable, whichever column it was written to. */
    public function scopeVariable($query)
    {
        return $query->where(fn ($w) => $w->where('type', 'variable')->orWhere('product_type', 'variable'));
    }

    /** Everything else — neither column may claim variable. */
    public function scopeNotVariable($query)
    {
        return $query
            ->where(fn ($w) => $w->where('type', '!=', 'variable')->orWhereNull('type'))
            ->where(fn ($w) => $w->where('product_type', '!=', 'variable')->orWhereNull('product_type'));
    }

    /**
     * What a variable product costs, as a formatted range.
     *
     * A variable product holds no price of its own — the variations do — so the parent row sits
     * at 0.00 and every card that printed it showed the product as free. The templates already
     * reached for `price_range` first; the accessor simply never existed, so the null coalesce
     * fell through to that zero every time.
     *
     * Returns null for simple products and for variable ones with no priced variations yet, which
     * leaves the existing single-price markup in charge.
     */
    public function getPriceRangeAttribute(): ?string
    {
        if (!$this->isVariable() || !function_exists('falcon_price_format')) {
            return null;
        }

        $variations = $this->relationLoaded('variations')
            ? $this->variations
            : $this->variations()->get(['id', 'price', 'sale_price']);

        $prices = [];
        foreach ($variations as $variation) {
            // What the shopper would actually pay for that variation.
            $sale = $variation->sale_price !== null ? (float) $variation->sale_price : 0.0;
            $effective = $sale > 0 ? $sale : (float) $variation->price;

            if ($effective > 0) {
                // Through the same tax conversion as every other price on the page.
                $prices[] = function_exists('falcon_display_price')
                    ? (float) falcon_display_price($effective, $this->post_id)
                    : $effective;
            }
        }

        if (empty($prices)) {
            return null;
        }

        $low = min($prices);
        $high = max($prices);

        // One price across every variation reads better as a single figure than as "X – X".
        return $low === $high
            ? falcon_price_format($low)
            : falcon_price_format($low) . ' – ' . falcon_price_format($high);
    }

    /**
     * Is the sale price in force at this moment?
     *
     * `sale_price` itself stays exactly as the shop owner typed it — the admin form reads it back
     * to fill the field, so nulling it there would wipe their sale on the next save. The expiry is
     * applied on the way out instead, through active_sale_price.
     *
     * The falcon:expire-sales command clears expired rows for good, but it runs daily; a sale that
     * ended at noon must stop applying at noon, not at the next run.
     */
    public function isOnSale(): bool
    {
        $sale = $this->attributes['sale_price'] ?? null;

        if ($sale === null || $sale === '' || (float) $sale <= 0) {
            return false;
        }

        return empty($this->sale_ends_at) || $this->sale_ends_at->isFuture();
    }

    /** The sale price while the sale is running, null once it is over. */
    public function getActiveSalePriceAttribute(): ?float
    {
        return $this->isOnSale() ? (float) $this->attributes['sale_price'] : null;
    }

    /**
     * Can anything under this product actually be bought right now?
     *
     * A variable product is only as available as its variations: sell the last one of every size
     * and the product is gone, even though the parent row still says "instock". The parent's
     * status is only re-synced when someone saves the product in the admin, and that sync looks
     * at each variation's status rather than its quantity — so a product sold down to zero
     * through the shop kept advertising itself until it was edited by hand.
     */
    public function isInStock(): bool
    {
        // An explicit "out of stock" on the parent wins over everything below it.
        if ($this->stock_status === 'outofstock') {
            return false;
        }

        if ($this->isVariable()) {
            $variations = $this->relationLoaded('variations')
                ? $this->variations
                : $this->variations()->get(['id', 'stock_status', 'manage_stock', 'stock_quantity']);

            // A variable product with no variations yet falls back to its own shelf, so a
            // half-built product behaves the same as it did before.
            if ($variations->isNotEmpty()) {
                foreach ($variations as $variation) {
                    if ($this->variationInStock($variation)) {
                        return true;
                    }
                }
                return false;
            }
        }

        return $this->quantityAllowsSale((int) $this->stock_quantity, (bool) $this->manage_stock);
    }

    /** Availability of one variation, judged with its parent's backorder policy. */
    protected function variationInStock($variation): bool
    {
        if (($variation->stock_status ?? 'instock') === 'outofstock') {
            return false;
        }

        // A variation that does not track its own stock is sold off the parent's shelf.
        if (empty($variation->manage_stock)) {
            return $this->quantityAllowsSale((int) $this->stock_quantity, (bool) $this->manage_stock);
        }

        return $this->quantityAllowsSale((int) $variation->stock_quantity, true);
    }

    /**
     * Does this quantity still allow a sale? Tracking has to be on both globally and for the
     * product before a number can block anything, and backorders override an empty shelf —
     * that is the entire point of the setting.
     */
    protected function quantityAllowsSale(int $quantity, bool $manageStock): bool
    {
        $globalManage = function_exists('get_shop_option')
            ? get_shop_option('shop_manage_stock', '1') === '1'
            : true;
        $threshold = function_exists('get_shop_option')
            ? (int) get_shop_option('shop_out_of_stock_threshold', '0')
            : 0;

        if ($globalManage && $manageStock && $quantity <= $threshold) {
            return $this->allowsBackorders();
        }

        return true;
    }

    public function downloads()
    {
        return $this->hasMany(ProductDownload::class, 'product_id');
    }

    protected $casts = [
        'attributes_data' => 'array',
        'upsell_ids'      => 'array',
        'cross_sell_ids'  => 'array',
        'sale_ends_at'    => 'datetime',
        'is_downloadable' => 'boolean',
    ];

    public function variations()
    {
        return $this->hasMany(ProductVariation::class, 'product_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
