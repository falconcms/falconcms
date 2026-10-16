# Ordering & Checkout

Everything on this page applies to wholesale customers only. Retail shoppers keep the normal
cart and checkout.

---

## Ordering rules

| Rule | Where you set it | When it is checked |
|---|---|---|
| **Minimum quantity** per product | Product → Wholesale | Adding to the cart, changing the quantity, checkout |
| **Pack size** per product ("sold in packs of 12") | Product → Wholesale | The same; each cart line must be a multiple |
| **Maximum** per product | The last quantity tier's "to" | The quantity box stops there and the cart refuses more |
| **Minimum order value** per level | Wholesale → Levels | Checkout |
| **Minimum items** per level | Wholesale → Levels | Checkout |

On the product page the quantity box opens at the product's minimum (rounded up to a whole pack)
and does not go below it, and the page says *Minimum order: 5. Maximum order: 200.*

Whether retail-priced extras count towards a level's minimum is up to you: **Settings → Minimum
order counts** is *Everything in the cart* or *Only items at a wholesale price*.

### Variable products

A variable product's minimum and maximum count **all its options together**: with a minimum of 10,
5 red and 5 blue are enough. An option may go into the cart below the minimum; the cart says how
many more are needed, and checkout holds the total to it.

To hold each option to the minimum on its own instead, tick **Hold each option to the minimum and
maximum on its own** on the product. Pack sizes are always per cart line.

---

## Coupons and promotions

Wholesale prices are already a discount, so by default:

- **No coupons on wholesale prices** — wholesale customers cannot apply coupon codes;
- **No automatic promotions on wholesale prices** — "buy X get Y" and other
  [promotions](/ecommerce/promotions) do not apply, and the cart does not tell wholesale customers
  they qualify for one.

Both are settings. A level's **spend discounts** still apply (see below).

The "Sale!" badge is not shown on a wholesale price. It stays when the retail product or
variation is on sale itself.

---

## Payment and shipping per level

On each level (**Wholesale → Levels**):

- **Payment methods** — untick what this level may not use. A common choice: no Cash on Delivery
  for distributors, only bank transfer or Pay on account.
- **Shipping methods** — the same for shipping.
- **Free shipping from** — shipping is free from this order value. The cart tells the customer how
  much more gets free shipping.

A method you switch on later is offered until you untick it on the level. Unticking every method
hides none.

---

## Spend discounts

Up to three per level: "**X% off orders of Y or more**". The biggest one reached comes off the
whole order as **Volume discount**, on top of wholesale prices, even with promotions turned off
for wholesale. The cart says how much more gets the next one.

---

## Deposits

Set **Deposit (%)** on a level and its customers get a **Pay a 30% deposit now, the rest later**
payment method at checkout. Tick **only way to pay** to make it the only method (besides Pay on
account).

1. The customer places the order. It goes **on hold** with the deposit and the balance recorded,
   and the confirmation page shows the deposit to pay and your bank details.
2. When the deposit arrives, click **Deposit received** on the admin order screen. The order moves
   to **Processing**.
3. When the rest arrives, click **Balance received**. The order is marked paid.

Invoices and the portal show what is still due.

---

## Purchase order (PO) number

**Settings → PO number at checkout**: *Off*, *Optional* or *Required*. Wholesale customers get a
**Purchase order (PO) number** field at checkout. It is kept on the order and shows on the admin
order, the confirmation page, invoices, emails and the portal's order list.

---

## Tax exemption

On a customer's screen, tick **Tax exempt** and enter the certificate number and, optionally, an end
date. Until that date their orders carry no tax; the order records the certificate number and the
invoice shows it. With prices entered including tax, the tax line goes but prices stay the same.

---

## Buying on account (credit)

Trusted customers can order now and pay later, within a credit limit.

**Turn it on per customer** (customer screen → **Credit account (buy now, pay later)** → Terms):
tick **May buy on account**, set a **credit limit** (empty: no limit) and **days to pay** (empty:
the shop default in Settings). They then get a
**Pay on account** payment method at checkout.

How it works:

- Each order becomes a **charge** on their account, due after their days to pay.
- An order beyond the **available credit** is refused, and so is any new order on account while
  something is **overdue** (Settings → *Overdue accounts*).
- You **record payments** (cheque, bank transfer, bKash…) and **adjustments** on the customer
  screen. A payment settles the **oldest** orders first and marks each one paid once it is covered.
- A cancelled, failed or refunded order is reversed on the account automatically.
- Customers are emailed (and texted, if SMS is on) a few days **before a payment is due**
  (Settings → *Remind before due*) and again once it is **overdue**. This runs daily.

Customers see what they owe, their available credit, what is overdue, unpaid invoices with due
dates and their statement in **portal → Account & invoices**.

---

## Quotes

For large or unusual orders, a customer can ask for your price before buying.

1. A **Request a quote** button sits under the cart's checkout button (for wholesale customers, or
   any signed-in customer: **Settings → Quote requests**).
2. The request appears in **Wholesale → Quotes**. Open it, set a price per line and **send** it. The
   customer is emailed (and texted, if SMS is on).
3. The customer **accepts** it in the portal (or at `/wholesale/quotes`). Its lines go into their cart
   at the quoted prices and quantities, **locked** until they order.
4. Either side can **decline**. An expired or declined quote stops holding its prices.

---

## Saved lists

Customers who order the same things often can keep a cart by name: **Save this cart as a list**,
under the cart's checkout button. Lists belong to the whole company and live in
**portal → Saved lists**.

- **Add to cart** puts a list back in the cart at **today's** prices and rules.
- A list can email a **reorder reminder** every week, 2 weeks, month or 2 months.

---

## Order approval

Company buyers' orders can need the owner's approval: always, or above an amount. See
[Order approval for buyers](/wholesale/customers#order-approval-for-buyers).
