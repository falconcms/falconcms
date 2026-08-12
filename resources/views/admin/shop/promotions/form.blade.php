@php
    $isNew = !$promotion->exists;
    $val = fn (string $key, $default = null) => old($key, $promotion->{$key} ?? $default);
    $selectedTriggerIds = collect(old('trigger_ids', $promotion->trigger_ids ?? []))->map(fn ($v) => (int) $v)->all();
    $selectedRewardIds  = collect(old('reward_ids',  $promotion->reward_ids  ?? []))->map(fn ($v) => (int) $v)->all();
@endphp

<x-falcon-cms::layouts.admin pro-lock-feature="ecommerce" :title="$isNew ? 'Add Promotion' : 'Edit Promotion'">
    <div class="flex items-center mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327] mr-3">{{ $isNew ? 'Add Promotion' : 'Edit Promotion' }}</h1>
        <a href="{{ route('admin.shop.promotions.index') }}" class="wp-btn-secondary">Back to list</a>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] p-3 mb-4 rounded-sm text-[13px]">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-[#fff] border-l-4 border-[#d63638] p-3 mb-4 rounded-sm text-[13px]">
            <ul class="list-disc ml-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $isNew ? route('admin.shop.promotions.store') : route('admin.shop.promotions.update', $promotion->id) }}"
          x-data="{
              triggerType: '{{ $val('trigger_type', 'product') }}',
              rewardType:  '{{ $val('reward_type', 'free_item') }}',
              rewardScope: '{{ $val('reward_scope', 'same') }}'
          }">
        @csrf
        @unless($isNew) @method('PUT') @endunless

        <div class="wp-metabox">
            <h2 class="wp-metabox-header">Basics</h2>
            <div class="wp-metabox-content space-y-4">
                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Name <span class="text-[#d63638]">*</span></label>
                    <div class="col-span-2">
                        <input type="text" name="name" value="{{ $val('name') }}" required
                               placeholder="e.g. Eid offer — buy 2 get 1 free"
                               class="wp-input w-full max-w-[420px]">
                        <p class="text-[11px] text-[#646970] mt-1">Shown to customers on the cart and checkout.</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-start">
                    <label class="text-[13px] font-semibold text-[#1d2327] pt-2">Cart message</label>
                    <div class="col-span-2">
                        <input type="text" name="cart_message" value="{{ $val('cart_message') }}" maxlength="255"
                               placeholder="{{ $promotion->summary }}"
                               class="wp-input w-full max-w-[520px]">
                        <p class="text-[11px] text-[#646970] mt-1">
                            What customers read on the cart. Leave empty to use the generated description
                            (shown greyed out above) &mdash; useful if you sell in another language.
                            Write <code>{missing}</code> where the number of items still needed should appear,
                            e.g. <em>&ldquo;Add {missing} more and the case is on us!&rdquo;</em>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Active</label>
                    <div class="col-span-2">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ $val('is_active', true) ? 'checked' : '' }} class="w-4 h-4 mr-2">
                            <span class="text-[13px]">Run this promotion</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Runs between</label>
                    <div class="col-span-2 flex items-center gap-2">
                        <input type="date" name="starts_at" value="{{ $promotion->starts_at?->format('Y-m-d') }}" class="wp-input">
                        <span class="text-[#646970]">&rarr;</span>
                        <input type="date" name="ends_at" value="{{ $promotion->ends_at?->format('Y-m-d') }}" class="wp-input">
                        <span class="text-[11px] text-[#646970]">Leave empty for no limit</span>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Priority</label>
                    <div class="col-span-2">
                        <input type="number" name="priority" value="{{ $val('priority', 10) }}" min="0" class="wp-input w-[100px] text-center">
                        <p class="text-[11px] text-[#646970] mt-1">
                            Lower runs first. Each unit in the cart can only be rewarded once, so the
                            first matching promotion claims it.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="wp-metabox">
            <h2 class="wp-metabox-header">Condition &mdash; what the customer must buy</h2>
            <div class="wp-metabox-content space-y-4">
                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Based on</label>
                    <div class="col-span-2">
                        <select name="trigger_type" x-model="triggerType" class="wp-input w-full max-w-[300px]">
                            <option value="product">Specific products</option>
                            <option value="category">Product categories</option>
                            <option value="cart_total">Cart total (amount spent)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-start" x-show="triggerType === 'product'">
                    <label class="text-[13px] font-semibold text-[#1d2327] pt-2">Products</label>
                    <div class="col-span-2">
                        <select id="promo-trigger-products" name="trigger_ids[]" multiple class="wp-input w-full max-w-[520px]">
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ in_array((int) $p->id, $selectedTriggerIds, true) ? 'selected' : '' }}>{{ $p->title }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-[#646970] mt-1">Type to search. Select none to mean <strong>any product</strong>.</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-start" x-show="triggerType === 'category'">
                    <label class="text-[13px] font-semibold text-[#1d2327] pt-2">Categories</label>
                    <div class="col-span-2">
                        <select id="promo-trigger-categories" name="trigger_ids[]" multiple class="wp-input w-full max-w-[520px]">
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" {{ in_array((int) $c->id, $selectedTriggerIds, true) ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-[#646970] mt-1">Select none to mean <strong>any category</strong>.</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">
                        <span x-show="triggerType !== 'cart_total'">Buy quantity</span>
                        <span x-show="triggerType === 'cart_total'" x-cloak>Minimum spend</span>
                    </label>
                    <div class="col-span-2">
                        <input type="number" step="0.01" min="0.01" name="trigger_qty" value="{{ $val('trigger_qty', 1) }}" class="wp-input w-[140px] text-center">
                        <p class="text-[11px] text-[#646970] mt-1">
                            <span x-show="triggerType !== 'cart_total'">How many units must be in the cart to earn the reward once.</span>
                            <span x-show="triggerType === 'cart_total'" x-cloak>Cart subtotal needed before the reward applies.</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="wp-metabox">
            <h2 class="wp-metabox-header">Reward &mdash; what they get</h2>
            <div class="wp-metabox-content space-y-4">
                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Reward applies to</label>
                    <div class="col-span-2">
                        <select name="reward_scope" x-model="rewardScope" class="wp-input w-full max-w-[300px]">
                            <option value="same">The same products bought</option>
                            <option value="specific">Specific other products</option>
                            <option value="category">Products in a category</option>
                        </select>
                        <p class="text-[11px] text-[#646970] mt-1">
                            &ldquo;The same products&rdquo; gives classic buy-2-get-1. The other options reward a
                            different item &mdash; the customer must have it in the cart for the discount to apply.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-start" x-show="rewardScope === 'specific'" x-cloak>
                    <label class="text-[13px] font-semibold text-[#1d2327] pt-2">Reward products</label>
                    <div class="col-span-2">
                        <select id="promo-reward-products" name="reward_ids[]" multiple class="wp-input w-full max-w-[520px]">
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ in_array((int) $p->id, $selectedRewardIds, true) ? 'selected' : '' }}>{{ $p->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-start" x-show="rewardScope === 'category'" x-cloak>
                    <label class="text-[13px] font-semibold text-[#1d2327] pt-2">Reward categories</label>
                    <div class="col-span-2">
                        <select id="promo-reward-categories" name="reward_ids[]" multiple class="wp-input w-full max-w-[520px]">
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" {{ in_array((int) $c->id, $selectedRewardIds, true) ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Discount</label>
                    <div class="col-span-2 flex items-center gap-2">
                        <select name="reward_type" x-model="rewardType" class="wp-input w-[220px]">
                            <option value="free_item">Free</option>
                            <option value="percent_off">Percentage off</option>
                            <option value="fixed_off">Fixed amount off</option>
                        </select>
                        <template x-if="rewardType !== 'free_item'">
                            <input type="number" step="0.01" min="0" name="reward_value" value="{{ $val('reward_value', 0) }}" class="wp-input w-[120px] text-center" placeholder="0">
                        </template>
                        <span class="text-[12px] text-[#646970]" x-show="rewardType === 'percent_off'" x-cloak>%</span>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Reward quantity</label>
                    <div class="col-span-2">
                        <input type="number" min="1" name="reward_qty" value="{{ $val('reward_qty', 1) }}" class="wp-input w-[100px] text-center">
                        <p class="text-[11px] text-[#646970] mt-1">
                            Units rewarded each time the condition is met. When several qualify, the
                            <strong>cheapest</strong> are discounted.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="wp-metabox">
            <h2 class="wp-metabox-header">Limits</h2>
            <div class="wp-metabox-content space-y-4">
                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Max times per order</label>
                    <div class="col-span-2">
                        <input type="number" min="1" name="max_applications" value="{{ $val('max_applications') }}" class="wp-input w-[120px] text-center" placeholder="No limit">
                        <p class="text-[11px] text-[#646970] mt-1">Stops one large basket claiming the offer over and over.</p>
                    </div>
                </div>
                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Total uses allowed</label>
                    <div class="col-span-2">
                        <input type="number" min="1" name="usage_limit" value="{{ $val('usage_limit') }}" class="wp-input w-[120px] text-center" placeholder="No limit">
                        <p class="text-[11px] text-[#646970] mt-1">
                            Across all customers. Used so far: <strong>{{ $promotion->usage_count ?? 0 }}</strong>.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 mt-4">
            <button type="submit" class="wp-btn-primary">{{ $isNew ? 'Create promotion' : 'Save changes' }}</button>
            <a href="{{ route('admin.shop.promotions.index') }}" class="text-[13px] text-[#2271b1]">Cancel</a>
        </div>
    </form>

    {{-- Searchable multi-selects, same component and options the Shop settings screen uses. --}}
    <link href="{{ asset('vendor/falcon-cms/css/tom-select.default.min.css') }}" rel="stylesheet">
    <script src="{{ asset('vendor/falcon-cms/js/tom-select.complete.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof TomSelect === 'undefined') return;

            [
                ['#promo-trigger-products',   'Search and select products...'],
                ['#promo-trigger-categories', 'Search and select categories...'],
                ['#promo-reward-products',    'Search and select products...'],
                ['#promo-reward-categories',  'Search and select categories...'],
            ].forEach(function ([selector, placeholder]) {
                var el = document.querySelector(selector);
                if (!el) return;
                new TomSelect(el, {
                    plugins: ['remove_button', 'dropdown_input'],
                    placeholder: placeholder,
                    maxOptions: 1000,
                    // Rendered on <body> so the list is not clipped by the metabox.
                    dropdownParent: 'body',
                    onItemAdd: function () { this.setTextboxValue(''); }
                });
            });
        });
    </script>
</x-falcon-cms::layouts.admin>
