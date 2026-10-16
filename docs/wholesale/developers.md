# For Developers

Falcon Wholesale is built on the shop's [logic hooks](/api/hooks#e-commerce-logic-hooks), and exposes
hooks of its own so a theme or another plugin can change what it does. Register them in a theme's
`functions.php` or a plugin's service provider. A filter returns its first argument, changed or not.

---

## Who is wholesale, and at what price

| Hook | Type | Arguments | Use it to |
|---|---|---|---|
| `falcon_wholesale_customer_level` | filter | `?Level $level, ?User $user` | Make someone wholesale by another rule, or take it away. Return a `Level` or `null`. |
| `falcon_wholesale_price` | filter | `?float $price, array $context` | Change the wholesale price: quantity tiers, a price list per customer, rounding. `$context` holds `post_id`, `variation_id`, `level`, `quantity` (the cart line's quantity in the cart), `retail` (`['price', 'sale_price']`) and `source` (`variation`, `product`, `category`, `level` or `null`). Return `null` for the retail price. |
| `falcon_wholesale_category_discount` | filter | `float $percent, int $postId, Level $level` | Change the category discount (0–100). |
| `falcon_wholesale_tiers` | filter | `array $tiers, int $postId, Level $level` | Change the quantity tiers that apply: each `['level' => int, 'min' => int, 'max' => ?int, 'type' => 'percent'\|'price', 'value' => float]`. |
| `falcon_wholesale_tier_label` | filter | `?string $label, array $tier, int $postId, int $quantity` | Change how a line's price tier is written ("20+ pcs · ৳30.00 each"). |
| `falcon_wholesale_hidden_price_html` | filter | `string $html, $product, string $context, bool $guest` | Change what replaces a hidden price (`$context` `price`) or buy button (`cart`). |

```php
// 5% more off from 50 pieces.
add_falcon_filter('falcon_wholesale_price', function ($price, $context) {
    return $price !== null && $context['quantity'] >= 50 ? $price * 0.95 : $price;
});
```

## Ordering rules

| Hook | Type | Arguments | Use it to |
|---|---|---|---|
| `falcon_wholesale_product_rules` | filter | `array $rules, int $postId, Level $level` | Change `['min_qty' => ?int, 'qty_step' => ?int, 'max_qty' => ?int]` for a product (`max_qty` defaults to the last tier's "to"). |
| `falcon_wholesale_min_order` | filter | `array $minimum, Level $level, array $cart` | Change `['amount' => ?float, 'quantity' => ?int]` for an order. |

## Applications and accounts

| Hook | Type | Arguments | When |
|---|---|---|---|
| `falcon_wholesale_application_fields` | action | `?WholesaleCustomer $account` | Inside the application form. Print extra fields here. |
| `falcon_wholesale_application_rules` | filter | `array $rules, Request $request` | Validation rules for the form. Add your fields' rules. |
| `falcon_wholesale_application_data` | filter | `array $data, Request $request` | What is saved. Put extra fields under `$data['meta']`. They show on the customer screen. |
| `falcon_wholesale_auto_approve` | filter | `bool $approve, WholesaleCustomer $account` | Approve some applications automatically (for example a verified BIN). |
| `falcon_wholesale_application_submitted` | action | `WholesaleCustomer $account` | An application was sent or sent again. |
| `falcon_wholesale_customer_approved` | action | `WholesaleCustomer $account` | An account was approved. |
| `falcon_wholesale_customer_rejected` | action | `WholesaleCustomer $account, ?string $reason` | An account was rejected or revoked. |
| `falcon_wholesale_customer_level_changed` | action | `WholesaleCustomer $account, ?int $oldLevelId` | An approved customer moved to another level. |

```php
// Ask for annual volume on the application.
add_falcon_action('falcon_wholesale_application_fields', function () {
    echo '<label>Annual volume <input name="annual_volume" type="number" required></label>';
});
add_falcon_filter('falcon_wholesale_application_rules', fn ($rules) => $rules + ['annual_volume' => 'required|integer|min:0']);
add_falcon_filter('falcon_wholesale_application_data', function ($data, $request) {
    $data['meta'] = ['annual_volume' => (int) $request->input('annual_volume')];
    return $data;
});
```

## Screens

| Hook | Type | Arguments | Use it to |
|---|---|---|---|
| `falcon_wholesale_price_note` | filter | `string $html, $post, array $data` | Change or replace the note under the product price. `$data` holds `label`, `level`, `savings`, `rules`, `variable`. |
| `falcon_wholesale_admin_customers_query` | action | `Builder $query, Request $request` | Narrow the admin customer list (for example an account manager's own customers). |
| `falcon_wholesale_customer_fields` | action | `WholesaleCustomer $account` | Add rows (`<tr>`) to the admin customer form. |
| `falcon_wholesale_customer_saved` | action | `WholesaleCustomer $account, Request $request` | Save those rows. |

## Login, portal and private store

| Hook | Type | Arguments | Use it to |
|---|---|---|---|
| `falcon_wholesale_portal_tabs` | filter | `array $tabs` | Add a portal tab: `[slug, label, lucide icon]`. |
| `falcon_wholesale_portal_tab_content` | action | `string $tab, WholesaleCustomer $account` | Print your tab's content. |
| `falcon_wholesale_reordered` | action | `Order $order, int $added, array $skipped` | After "Order again". `$skipped` holds the names of items that could not be added. |
| `falcon_wholesale_company_updated` | action | `WholesaleCustomer $account, Request $request` | The customer saved their company details. |
| `falcon_wholesale_login_form_after` | action | — | Below the wholesale login form. |
| `falcon_wholesale_login_url` | filter | `string $url, ?string $redirectTo` | Change where customers are sent to sign in (the login page, with `redirect_to`). |
| `falcon_wholesale_private_store_allows` | filter | `bool $allowed, Request $request, string $mode` | Open a page to everyone in private store mode, or close one. |

## Quick order and price import

| Hook | Type | Arguments | When |
|---|---|---|---|
| `falcon_wholesale_quick_order` | action | `int $added, array $problems` | After a quick order put lines in the cart. |
| `falcon_wholesale_prices_imported` | action | `array $result` | After a price sheet was imported (`products`, `variations`, `unknown` SKUs, `errors`). |

## Credit and invoices

| Hook | Type | Arguments | Use it to |
|---|---|---|---|
| `falcon_wholesale_credit_available` | filter | `?float $available, WholesaleCustomer $account` | Change the credit left (`null` = no limit). |
| `falcon_wholesale_credit_allows_order` | filter | `true\|string $verdict, WholesaleCustomer $account, float $total` | Allow or refuse an order on account. A string is the message shown. |
| `falcon_wholesale_credit_charged` | action | `CreditEntry $charge, Order $order` | An order was charged to the account. |
| `falcon_wholesale_payment_recorded` | action | `CreditEntry $payment, WholesaleCustomer $account, float $amount` | A payment was recorded. `$payment` is its first ledger line (a payment spread over several orders makes one line per order). |
| `falcon_wholesale_invoice_data` | filter | `array $data, Order $order` | Change what the invoice or challan shows (`seller`, `footer`, `dueDate`, `owed`…). |

`FalconWholesale\Support\Credit` has `balance()`, `available()`, `unpaid()`,
`recordPayment()` and `adjust()`.

## Emails

Every wholesale email uses one layout (`resources/views/emails/layout.blade.php`): your Shop email
header image, or your logo, or your shop name; your theme's primary colour; and the Shop email
footer text. It works in the usual email clients and narrows for phones. A theme can override any
email view under `falcon-wholesale::emails.*`.

## Quotes, teams, SMS and reports

| Hook | Type | Arguments | When / use it to |
|---|---|---|---|
| `falcon_wholesale_quote_requested` | action | `Quote $quote` | A customer asked for a quote. |
| `falcon_wholesale_quote_price` | filter | `float $price, array $item, Quote $quote` | Change a quoted line price (for example rounding). |
| `falcon_wholesale_quote_sent` | action | `Quote $quote` | The shop sent the quote. |
| `falcon_wholesale_quote_accepted` | action | `Quote $quote` | The customer accepted it (it is in their cart). |
| `falcon_wholesale_quote_declined` | action | `Quote $quote, string $by` | Declined by `customer` or `shop`. |
| `falcon_wholesale_member_invited` / `_joined` / `_removed` | action | `CompanyMember $member` | Team changes. |
| `falcon_wholesale_send_sms` | filter | `?bool $sent, string $to, string $message` | Send through your own provider: return `true` or `false`. Leave `null` to use the HTTP provider. |
| `falcon_wholesale_sms_message` | filter | `string $message, string $event, array $context` | Change the text (`approved`, `order`, `payment`, `reminder`, `quote`, `manual`, `test`), after the shop's own words are filled in. |
| `falcon_wholesale_role_added` / `falcon_wholesale_role_removed` | action | `int $userId` | The Wholesale Customer role was given or taken away. |
| `falcon_wholesale_sms_sent_by_hand` | action | `WholesaleCustomer $account, string $message, bool $sent` | Staff texted a customer from their screen. |
| `falcon_wholesale_report` | filter | `array $report, Carbon $from, Carbon $to` | Add figures to the report. |

## B2B checkout, approval, lists and reps

| Hook | Type | Arguments | When / use it to |
|---|---|---|---|
| `falcon_wholesale_tier_quantity` | filter | `int $quantity, int $postId, int $lineQuantity, array $cart` | The cart quantity a line's tier is measured against (mix & match). |
| `falcon_wholesale_payment_gateways` | filter | `array $gateways, Level $level` | The payment methods a level is offered, after its hidden ones are removed. |
| `falcon_wholesale_shipping_methods` | filter | `array $methods, Level $level, array $cart` | The shipping methods a level is offered (free shipping applied). |
| `falcon_wholesale_customer_price` | filter | `?float $price, int $postId, ?int $variationId, WholesaleCustomer $account` | Change or supply a contract price. |
| `falcon_wholesale_customer_price_saved` | action | `WholesaleCustomer $account, int $postId, ?int $variationId, float $price` | A contract price was saved. |
| `falcon_wholesale_order_needs_approval` | filter | `?string $reason, ?CompanyMember $member, float $total` | Return a reason to hold an order for approval, or `null`. |
| `falcon_wholesale_approval_requested` | action | `Order $order, WholesaleCustomer $account` | An order went on hold for approval. |
| `falcon_wholesale_order_approved` / `_declined` | action | `Order $order, WholesaleCustomer $account` | The decision was made. |
| `falcon_wholesale_list_saved` | action | `SavedList $list` | A cart was saved as a list. |
| `falcon_wholesale_deposit_gateway` | filter | `array $gateway, Level $level` | The "Pay a deposit" method's title and text. |
| `falcon_wholesale_deposit_received` / `falcon_wholesale_balance_received` | action | `Order $order` | The shop confirmed a payment. |
| `falcon_wholesale_spend_discount` | filter | `?array $rule, float $subtotal, Level $level` | The spend discount reached (`['over', 'percent']`), or `null`. |
| `falcon_wholesale_tax_exempt` | filter | `bool $exempt, ?WholesaleCustomer $account` | Decide whether the shopper pays tax. |
| `falcon_wholesale_rep_commission` | filter | `float $commission, array $orderData, object $rep` | Change a rep's commission on an order. |

## In code

Changes to an account should go through `FalconWholesale\Support\Accounts` (`apply`, `approve`,
`reject`, `setLevel`), so the actions above fire and the emails go out.
`FalconWholesale\Support\Customers::currentLevel()` tells you the signed-in shopper's level.

## Shortcodes

See [Settings & Tools → Shortcodes](/wholesale/settings#shortcodes).

## Data

| Table | What |
|---|---|
| `wholesale_levels` | Levels |
| `wholesale_customers` | One wholesale account per user: application, status, level |
| `wholesale_product_rules` | Per product: price per level and tiers (JSON), minimum quantity, pack size, visibility |
| `wholesale_quotes` | Quote requests and quotes, with their lines (JSON) |
| `wholesale_company_members` | People who buy for a company besides its owner, and pending invitations |
| `wholesale_credit_entries` | Each customer's account: charges (+), payments, reversals and adjustments (−) |
| `wholesale_price_index` | What each level pays per product (min and max), for the price filter and sort |
| `wholesale_category_discounts` | Per category and level: discount percent |
| `wholesale_mix_categories` | Categories whose products count together for quantity tiers |
| `wholesale_customer_prices` | Contract prices: account, product, variation (0 = every option), price |
| `wholesale_saved_lists` | Saved lists: account, who saved it, name, items (JSON), reminder |
| `wholesale_sales_reps` | Sales reps: user, commission percent, whether they are emailed orders |

Levels also hold hidden payment and shipping methods, free shipping from, deposit and spend
discounts. Company members hold their approval rules (`needs_approval`, `order_limit`), and
wholesale customers their tax exemption and sales rep. An order's `meta` records
`wholesale_approval`, `wholesale_deposit`, `wholesale_tax_exempt` and `wholesale_rep`; the PO
number is `meta.checkout_fields.po_number`.

Variation prices are stored on the variation row: `shop_product_variations.meta.falcon_wholesale`,
keyed by level id. Settings are CMS options named `wholesale_*`.
