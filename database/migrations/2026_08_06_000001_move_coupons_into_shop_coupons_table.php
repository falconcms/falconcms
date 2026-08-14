<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coupons used to live as a JSON blob in cms_settings('shop_coupons') even though a
 * shop_coupons table already existed (unused, zero rows). The blob had no unique index on the
 * code, and its usage counter was read-modify-written on every order — so two checkouts landing
 * together could both redeem the last use of a limited coupon, and the whole coupon set was
 * rewritten on every settings save.
 *
 * This moves the data into the table. The blob is deliberately LEFT IN PLACE: it costs nothing
 * to keep and it is the rollback path if a store needs to go back.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shop_coupons')) {
            Schema::create('shop_coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('type')->default('fixed_cart');
                $table->decimal('amount', 15, 2);
                $table->decimal('min_spend', 15, 2)->nullable();
                $table->decimal('max_spend', 15, 2)->nullable();
                $table->integer('usage_limit')->nullable();
                $table->integer('usage_count')->default(0);
                $table->timestamp('expiry_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('shop_coupons', function (Blueprint $table) {
            // Product / category restrictions and the global cap had no home in the original
            // table — they only ever existed in the JSON shape.
            if (!Schema::hasColumn('shop_coupons', 'products')) {
                $table->json('products')->nullable()->after('max_spend');
            }
            if (!Schema::hasColumn('shop_coupons', 'categories')) {
                $table->json('categories')->nullable()->after('products');
            }
            if (!Schema::hasColumn('shop_coupons', 'total_usage_limit')) {
                $table->integer('total_usage_limit')->nullable()->after('usage_limit');
            }
        });

        $this->importFromSettings();
    }

    /**
     * Copy any coupons still living in the settings blob into the table.
     *
     * Keyed on the (unique) code and skips codes already present, so it is safe to re-run and
     * will never clobber edits made after the first import.
     */
    protected function importFromSettings(): void
    {
        $raw = DB::table('cms_settings')->where('key', 'shop_coupons')->value('value');
        if (!$raw) {
            return;
        }

        $coupons = json_decode((string) $raw, true);
        if (!is_array($coupons)) {
            return;
        }

        $existing = DB::table('shop_coupons')->pluck('code')->map(
            static fn ($c) => strtoupper((string) $c)
        )->all();

        foreach ($coupons as $coupon) {
            if (!is_array($coupon)) {
                continue;
            }

            $code = trim((string) ($coupon['code'] ?? ''));
            if ($code === '' || in_array(strtoupper($code), $existing, true)) {
                continue;
            }

            // Blank date strings must become NULL, not '0000-00-00'.
            $expiry = trim((string) ($coupon['expiry'] ?? ''));
            $expiry = $expiry !== '' ? date('Y-m-d H:i:s', strtotime($expiry)) : null;

            $numeric = static function ($value): ?string {
                $value = is_scalar($value) ? trim((string) $value) : '';

                return $value === '' ? null : (string) (float) $value;
            };
            $intOrNull = static function ($value): ?int {
                $value = is_scalar($value) ? trim((string) $value) : '';

                return $value === '' ? null : (int) $value;
            };

            DB::table('shop_coupons')->insert([
                'code' => $code,
                'type' => in_array($coupon['type'] ?? '', ['percent', 'fixed_cart', 'fixed_product', 'free_shipping'], true) ? $coupon['type'] : 'fixed_cart',
                'amount' => $numeric($coupon['amount'] ?? ($coupon['discount'] ?? 0)) ?? 0,
                'min_spend' => $numeric($coupon['min_spend'] ?? null),
                'max_spend' => $numeric($coupon['max_spend'] ?? null),
                'products' => json_encode(array_values((array) ($coupon['products'] ?? []))),
                'categories' => json_encode(array_values((array) ($coupon['categories'] ?? []))),
                'usage_limit' => $intOrNull($coupon['usage_limit'] ?? null),
                'total_usage_limit' => $intOrNull($coupon['total_usage_limit'] ?? null),
                'usage_count' => (int) ($coupon['used_count'] ?? 0),
                'expiry_date' => $expiry,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $existing[] = strtoupper($code);
        }
    }

    public function down(): void
    {
        Schema::table('shop_coupons', function (Blueprint $table) {
            foreach (['products', 'categories', 'total_usage_limit'] as $column) {
                if (Schema::hasColumn('shop_coupons', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
