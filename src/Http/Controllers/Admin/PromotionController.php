<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Shop → Promotions. Automatic cart rules ("buy 2 get 1 free"), as opposed to coupons, which
 * need a code, and product types, which describe what a product is.
 */
class PromotionController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $query = Promotion::query();

        // A promotion's real state is a combination of its flag, its dates and its usage cap,
        // so the filters describe what the shopper would experience rather than one column.
        $now = now();
        match ($status) {
            'active' => $query->usable(),
            'inactive' => $query->where('is_active', false),
            'scheduled' => $query->where('is_active', true)->whereNotNull('starts_at')->where('starts_at', '>', $now),
            'expired' => $query->where('is_active', true)->whereNotNull('ends_at')->where('ends_at', '<', $now),
            default => null,
        };

        if ($request->filled('s')) {
            $term = '%'.$request->s.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('cart_message', 'like', $term));
        }

        $promotions = $query->orderBy('priority')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('falcon-cms::admin.shop.promotions.index', [
            'promotions' => $promotions,
            'allCount' => Promotion::count(),
            'activeCount' => Promotion::usable()->count(),
            'inactiveCount' => Promotion::where('is_active', false)->count(),
            'scheduledCount' => Promotion::where('is_active', true)->whereNotNull('starts_at')->where('starts_at', '>', $now)->count(),
            'expiredCount' => Promotion::where('is_active', true)->whereNotNull('ends_at')->where('ends_at', '<', $now)->count(),
        ]);
    }

    /**
     * Bulk activate / deactivate / delete from the list screen.
     *
     * Ids are filtered through the model rather than trusted, and the action is allowlisted, so
     * a hand-edited form cannot reach anything the screen does not offer.
     */
    public function bulk(Request $request)
    {
        $action = $request->input('action') !== '-1' ? $request->input('action') : $request->input('action2');
        $ids = array_filter(array_map('intval', (array) $request->input('promotion_ids', [])));

        if (!in_array($action, ['activate', 'deactivate', 'delete'], true) || empty($ids)) {
            return back()->with('error', 'Please select an action and at least one promotion.');
        }

        $promotions = Promotion::whereIn('id', $ids)->get();
        if ($promotions->isEmpty()) {
            return back()->with('error', 'Nothing to update.');
        }

        $count = $promotions->count();

        if ($action === 'delete') {
            Promotion::whereIn('id', $promotions->pluck('id'))->delete();

            return back()->with('success', $count.' promotion'.($count === 1 ? '' : 's').' deleted. Orders that already used them keep their discount.');
        }

        Promotion::whereIn('id', $promotions->pluck('id'))
            ->update(['is_active' => $action === 'activate', 'updated_at' => now()]);

        return back()->with('success', $count.' promotion'.($count === 1 ? '' : 's').' '.($action === 'activate' ? 'activated' : 'deactivated').'.');
    }

    public function create()
    {
        return $this->form(new Promotion([
            'is_active' => true,
            'priority' => 10,
            'trigger_type' => 'product',
            'trigger_qty' => 1,
            'reward_type' => 'free_item',
            'reward_scope' => 'same',
            'reward_qty' => 1,
        ]));
    }

    public function edit($id)
    {
        return $this->form(Promotion::findOrFail($id));
    }

    protected function form(Promotion $promotion)
    {
        return view('falcon-cms::admin.shop.promotions.form', [
            'promotion' => $promotion,
            'products' => DB::table('posts')->where('type', 'product')
                ->whereNull('deleted_at')->orderBy('title')->get(['id', 'title']),
            'categories' => DB::table('product_categories')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $promotion = Promotion::create($this->validated($request));

        return redirect()->route('admin.shop.promotions.edit', $promotion->id)
            ->with('success', 'Promotion created.');
    }

    public function update(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->update($this->validated($request));

        return redirect()->route('admin.shop.promotions.edit', $promotion->id)
            ->with('success', 'Promotion updated.');
    }

    public function destroy($id)
    {
        Promotion::findOrFail($id)->delete();

        return redirect()->route('admin.shop.promotions.index')
            ->with('success', 'Promotion deleted.');
    }

    /**
     * Validate and normalise the form.
     *
     * Types are allowlisted and every number is clamped rather than stored as posted: these
     * values drive what customers are charged, so a hand-edited form must not be able to
     * introduce a negative quantity or a 500% discount.
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'cart_message' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0|max:9999',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'trigger_type' => 'required|in:'.implode(',', Promotion::TRIGGER_TYPES),
            'trigger_ids' => 'nullable|array',
            'trigger_ids.*' => 'integer',
            'trigger_qty' => 'required|numeric|min:0.01',
            'reward_type' => 'required|in:'.implode(',', Promotion::REWARD_TYPES),
            'reward_scope' => 'required|in:'.implode(',', Promotion::REWARD_SCOPES),
            'reward_ids' => 'nullable|array',
            'reward_ids.*' => 'integer',
            'reward_qty' => 'required|integer|min:1|max:999',
            'reward_value' => 'nullable|numeric|min:0',
            'max_applications' => 'nullable|integer|min:1|max:999',
            'usage_limit' => 'nullable|integer|min:1',
        ], [], [
            'trigger_qty' => 'Buy quantity',
            'reward_qty' => 'Reward quantity',
        ]);

        $rewardValue = (float) ($data['reward_value'] ?? 0);
        if ($data['reward_type'] === 'percent_off') {
            $rewardValue = min($rewardValue, 100);   // over 100% would pay the customer
        }
        if ($data['reward_type'] === 'free_item') {
            $rewardValue = 0;                        // the value is the item itself
        }

        // A reward drawn from a specific list needs that list; without it the rule would match
        // every product in the shop, which is almost never what was meant.
        $rewardIds = array_values(array_unique(array_map('intval', $data['reward_ids'] ?? [])));
        if ($data['reward_scope'] === 'same') {
            $rewardIds = [];
        }

        // Stored as plain text and escaped on output — the storefront prints it with {{ }},
        // so a shop owner cannot inject markup into a customer-facing page.
        $message = trim((string) ($data['cart_message'] ?? ''));

        return [
            'name' => $data['name'],
            'cart_message' => $message !== '' ? $message : null,
            'is_active' => $request->boolean('is_active'),
            'priority' => (int) ($data['priority'] ?? 10),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'trigger_type' => $data['trigger_type'],
            'trigger_ids' => $data['trigger_type'] === 'cart_total'
                ? []
                : array_values(array_unique(array_map('intval', $data['trigger_ids'] ?? []))),
            'trigger_qty' => max(0.01, (float) $data['trigger_qty']),
            'reward_type' => $data['reward_type'],
            'reward_scope' => $data['reward_scope'],
            'reward_ids' => $rewardIds,
            'reward_qty' => max(1, (int) $data['reward_qty']),
            'reward_value' => max(0, $rewardValue),
            'max_applications' => $data['max_applications'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
        ];
    }
}
