{{--
    "You qualify" prompts: promotions the cart has already earned whose reward item is not in the
    basket yet. A cross-product rule ("buy 3 phones, get a case free") can only discount a case the
    customer actually has, and nothing else on the page would tell them that.

    Kept as its own partial because the cart renders it on page load *and* the AJAX totals payload
    re-renders it after every quantity change — one template so the two can never drift apart.

    Expects: $offers (from falcon_pending_promotion_offers()).
--}}
@if(!empty($offers))
    <div class="mb-8 space-y-3">
        @foreach($offers as $offer)
            <div class="border border-amber-200 bg-amber-50/60 rounded p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-grow">
                    <p class="font-bold text-amber-800 text-[15px] flex items-center gap-2">
                        <span>&#127873;</span> You qualify: {{ $offer['name'] }}
                    </p>
                    <p class="text-[13px] text-amber-700/80 mt-0.5">
                        {{-- The shop's own wording stands on its own; the generated summary gets the
                             "add N more" tail appended, since on its own it does not tell the
                             customer what to do next. --}}
                        {{ $offer['summary'] }}@unless($offer['custom'] ?? false) &mdash;
                            add {{ $offer['missing'] }} more item{{ $offer['missing'] === 1 ? '' : 's' }} below to claim it.@endunless
                    </p>
                </div>
                @if(!empty($offer['products']))
                    <div class="flex flex-wrap gap-2 shrink-0">
                        @foreach($offer['products'] as $rewardProduct)
                            <button type="button"
                                    onclick="addPromoReward({{ (int) $rewardProduct['id'] }}, this)"
                                    class="bg-amber-500 text-white text-[12px] font-bold px-3 py-2 rounded hover:opacity-90 disabled:opacity-60 transition-all">
                                + {{ \Illuminate\Support\Str::limit($rewardProduct['title'], 28) }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
