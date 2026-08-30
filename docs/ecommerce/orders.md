# Orders

![The orders list](/screenshots/orders.webp)

*Every order with its code, total, payment method and status — **Admin → Shop → Orders**.*

## Order Lifecycle

```
Cart → Checkout → Pending → Processing → Completed
                     ↓
                  Failed / Cancelled
                     ↓
                  Refunded / Partially Refunded
```

## Order Statuses

| Status | Description |
|---|---|
| `pending` | Order placed, payment not yet confirmed |
| `processing` | Payment received, being prepared |
| `completed` | Fulfilled and delivered |
| `failed` | Payment failed |
| `cancelled` | Cancelled by admin or customer |
| `refunded` | Fully refunded |
| `partially-refunded` | Partially refunded |

## Managing Orders

Go to **Admin → Shop → Orders**:

- Filter by status, search by order number, customer name, or email
- Click an order to view full details

### Order Detail View

- **Order summary** — Items, quantities, prices, totals
- **Customer info** — Name, email, billing address, shipping address
- **Status history** — Timeline of all status changes with timestamps and notes
- **Invoice** — Generate printable invoice

### Changing Order Status

1. Open an order
2. Select new status from dropdown
3. Optionally add a note
4. Click **Update Status**

Status changes are logged automatically in the order timeline.

### Bulk Actions

From the orders list, select multiple orders and apply:
- Change status
- Delete
- Export

## Processing Refunds

1. Open the order
2. Click **Refund**
3. Enter refund amount (partial or full)
4. Add a note (optional)
5. Confirm

Refunds are tracked in `refund_log`. The `refunded_amount` field tracks total refunded. Net revenue is calculated as `gross - refunded`.

## Invoices

Click **View Invoice** on any order to open a printable invoice page. Includes:
- Order number, date
- Customer billing details
- Line items with quantities and prices
- Tax and shipping breakdown
- Total paid

## Order Tracking (Frontend)

Customers can track orders without logging in:

1. Visit `/{checkout-page-slug}/track-order`
2. Enter **Order Number** + **Email**
3. See current status and history

## Order Model

```php
use Acme\CmsDashboard\Models\Order;

// Get all orders
$orders = Order::with('items', 'user')->latest()->paginate(20);

// Get a specific order
$order = Order::where('order_number', 'ORD-0001')->first();

// Access order data
$order->status;
$order->items;                    // OrderItem collection
$order->meta['billing_address'];  // Billing details
$order->statusHistory;            // Timeline events

// Update status programmatically
$order->update(['status' => 'completed']);
$order->logStatus('completed', 'Shipped via DHL');
```
