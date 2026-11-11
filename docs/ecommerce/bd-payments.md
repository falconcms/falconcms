# bKash & Nagad (Pro)

**Falcon Bangladesh Payments** adds bKash and Nagad to the shop checkout. It is a FalconCMS Pro
plugin. It needs the Falcon Shop plugin and FalconCMS 2.8.1 or newer.

Turn it on under **Admin → Plugins**. Its settings are under **Admin → eCommerce → BD Payments**.

Each gateway works in one of two modes:

| Mode | How the customer pays | Who confirms the payment |
|---|---|---|
| **Manual** | Sends money to your bKash / Nagad number, then enters the number they paid from and the TrxID at checkout | You, from the order screen |
| **API** | Is sent to the official bKash or Nagad payment page and pays with PIN & OTP | The shop, automatically, after checking with bKash / Nagad |

Manual mode needs only a bKash or Nagad account. API mode needs a merchant account and its API
credentials.

## Manual mode

### Settings

On the **bKash** or **Nagad** tab, choose **Manual Mode** and fill in:

| Field | Description |
|---|---|
| Enable | Shows the gateway at checkout |
| Title | The name customers see (e.g. `bKash`) |
| Account Type | Personal (Send Money), Merchant (Payment) or Agent (Cash In) |
| Account Number | The number customers send money to. Bangladeshi mobile numbers only (013–019, 11 digits) |
| Customer Instructions | Shown on the checkout when the gateway is selected |

### At checkout

When the customer chooses bKash or Nagad, the checkout shows your number with a **Copy** button,
your instructions, and two fields:

- **Sender number**: the number they paid from.
- **Transaction ID (TrxID)**: the ID bKash or Nagad sent them after the payment.

Both fields are checked as the customer types, and again on the server:

- The number field takes **digits only**. A letter or symbol is removed as it is typed or pasted,
  and the field says why. Too short, too long or a wrong operator (anything but 013–019) is shown
  live. `+880…` and Bangla digits (০১৭…) are accepted and saved as `01XXXXXXXXX`.
- The TrxID must be 8–20 English letters and digits. It is saved in capitals.
- **One TrxID pays for one order.** A TrxID already used on another order is refused. A TrxID from
  a rejected payment can be used again.

The order is placed as **Pending**. It is not marked paid until you check the payment.

### Verifying a payment

Open the order. The **bKash / Nagad** box in the sidebar shows the sender number, the TrxID (with a
copy button) and the amount to look for. Check them in your bKash or Nagad app, then:

- **Verify payment** moves the order to **Processing** and records it as paid. The customer gets the
  usual status email.
- **Reject** moves the order to **Failed**.

Moving the order with the normal status box works too (see
[Payment status](#payment-status-follows-the-order)).

## API mode

### bKash: Tokenized Checkout

On the **bKash** tab, choose **Automated API Mode** and enter the **App Key**, **App Secret**,
**API Username** and **API Password** from your bKash merchant account. Leave **Sandbox / Test Mode**
ticked while testing, and untick it for live payments.

### Nagad: PGW

On the **Nagad** tab, choose **Automated API Mode** and enter your **Merchant ID**, the **Nagad PGW
Public Key** and your **Merchant Private Key**. Nagad's sandbox and live keys both come with a
Nagad merchant account.

### How an API payment works

1. The customer places the order and is sent to the bKash or Nagad payment page.
2. They pay with their wallet number, OTP and PIN.
3. They come back to your shop. The shop checks the payment with bKash or Nagad from the server.
4. The order is marked paid only if the provider confirms it. The payment must belong to this
   order, and be for at least the order total.

Nothing in the return link can mark an order paid on its own. A cancelled or failed payment leaves
the order pending, and the customer sees a message on the order confirmation page.

The callback URLs are shown on each tab. The plugin handles them; there is nothing to set up.

::: tip Testing bKash without a merchant account
bKash publishes sandbox credentials for testing. Use them with **Sandbox / Test Mode** ticked:

| Field | Value |
|---|---|
| App Key | `4f6o0cjiki2rfm34kfdadl1eqq` |
| App Secret | `2is7hdktrekvrbljjh44ll3d9l1dtjo4pasmjvs5vl5qr3fug4b` |
| API Username | `sandboxTokenizedUser02` |
| API Password | `sandboxTokenizedUser02@12345` |

On the bKash payment page, use the test wallet **01770618575**, OTP **123456** and PIN **12121**.
The sandbox accepts only bKash's test wallets. Any other number shows *"The input wallet is not
eligible"*. No real money moves.

Nagad has no public sandbox credentials.
:::

### Saved credentials

The bKash App Secret and API Password, and the Nagad private key, are stored encrypted. After you
save them, the field shows only part of the saved value (for example `2is7hdktrek••••••••`), never
the whole of it. Leave the field blank to keep it, or tick **Remove saved value** to delete it.

## Payment status follows the order

Each bKash or Nagad payment has a status on the **Payment Logs** tab. It changes with the order,
however the order is moved: the status box, a bulk action, or the Verify / Reject buttons.

| Order status | Payment status |
|---|---|
| Processing, Confirmed, Packing, Packed, Delivering, Delivered, Completed | **Completed**. A manual order is also recorded as paid |
| Cancelled | Cancelled |
| Failed | Failed |
| Refunded, Partially Refunded | Refunded |
| Pending, On Hold | Pending (manual payments; a confirmed API payment stays Completed) |

The **Payment Logs** tab lists the latest 25 payments with gateway, mode, order, amount, TrxID,
sender number, payment status and order status.

## Uninstalling

Uninstalling keeps your settings and payment log, unless you tick **Also delete all of its data**.
With that ticked, every bKash and Nagad setting (including the saved credentials) and the payment
log are deleted.
