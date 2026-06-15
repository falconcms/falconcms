# E-commerce Overview

Falcon CMS includes a complete e-commerce system built directly into the package — no extra plugins needed.

## Features

- **Products** with images, pricing, inventory, SKU
- **Variable products** — size, color, and custom options
- **Product Categories & Tags** — dedicated, first-class taxonomy
- **Shopping cart** — session-based, persistent
- **Coupon codes** — percentage or fixed discount
- **Checkout** — guest or registered customer
- **Multiple payment gateways** — PayPal, Stripe, SSLCommerz
- **Order management** — status workflow, invoices, refunds
- **Product reviews** with moderation
- **Wishlist**
- **Order tracking** — customers track by order number + email

## Setup Checklist

1. **Create pages** — Falcon CMS auto-creates Shop, Cart, Checkout, and Account pages during `falcon:install`. If missing, create them and assign in settings.

2. **Configure Shop Settings** — Go to **Admin → Shop → Settings**:
   - Currency and format
   - Tax rate
   - Shipping method and rate
   - Payment gateways

3. **Add Products** — Admin → Products → Add New

4. **Test checkout** — Add a product to cart and complete a test order

## Shop Settings

Navigate to **Admin → Shop → Settings**:

| Setting | Description |
|---|---|
| Currency | USD, EUR, BDT, GBP, etc. |
| Currency position | Before or after price |
| Decimal places | Price formatting (e.g., 2 → `$10.00`) |
| Shop page | Post ID of your main shop/products page |
| Cart page | Post ID of cart page |
| Checkout page | Post ID of checkout page |
| Account page | Post ID of customer account page |
| Guest checkout | Allow checkout without an account |
| Shipping method | `flat_rate`, `free`, or `calculated` |
| Flat rate | Cost for flat rate shipping |
| Tax enabled | Enable/disable tax calculation |
| Tax rate | Percentage (e.g., `10` for 10%) |

## Payment Gateways

### PayPal

1. Go to **Admin → Settings → Integrations**
2. Enable PayPal
3. Enter Client ID and Secret (from PayPal Developer Dashboard)
4. Select Sandbox / Live mode

### Stripe

1. Enable Stripe in Integrations settings
2. Enter Public Key and Secret Key (from Stripe Dashboard)

### SSLCommerz (South Asia)

1. Enable SSLCommerz
2. Enter Store ID and Signature Key

## Shop Dashboard

**Admin → Shop → Overview** shows:

- Total Orders
- Net Revenue (gross minus refunds)
- Gross Revenue
- Total Refunded
- Average Order Value
- Order status breakdown chart

All stats are filterable by: Today, Last 7 days, Last 30 days, This month, This year, All time, Custom range.
