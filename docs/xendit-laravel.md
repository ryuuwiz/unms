Laravel 13 + Xendit Payment Link/Invoice, I strongly recommend treating Xendit as the payment processor, while your Laravel database remains the source of truth for orders and payment state.

The most important rule is:

> Never mark an order as paid because the customer returned to your success page. Mark it paid only after a verified Xendit webhook/server-side confirmation.



Xendit explicitly recommends server-side webhook handling and webhook authentication, and duplicate webhook delivery can occur. 

1. Recommended architecture

Customer
   │
   │ 1. Create Order
   ▼
Laravel 13
   │
   ├── orders
   ├── order_items
   └── payments
   │
   │ 2. Create Xendit Invoice
   ▼
Xendit
   │
   │ 3. Returns invoice/payment URL
   ▼
Customer
   │
   │ 4. Pay
   ▼
Xendit
   │
   │ 5. Webhook
   ▼
POST /webhooks/xendit
   │
   ├── Verify x-callback-token
   ├── Validate event
   ├── Check duplicate
   ├── Verify amount/order
   └── Update payment + order
             │
             ▼
        Order = PAID

Xendit supports invoice/payment-link style flows where an invoice is created and the customer completes payment through the generated URL. The current PHP SDK exposes invoice creation through POST /v2/invoices/. 


---

2. Database design I recommend

Don't put everything into orders.

Use at least:

users
orders
order_items
payments
payment_webhooks

orders

id
order_number
user_id
status
subtotal
tax
discount
total
currency
created_at
updated_at

Example:

ORD-20260829-000001

Recommended order statuses:

pending
processing
paid
cancelled
expired
refunded


---

3. payments

This is the most important table.

payments
-------------------------
id
order_id
provider
provider_payment_id
provider_invoice_id
external_id
status
amount
currency
payment_method
payment_channel
checkout_url
expires_at
paid_at
metadata
created_at
updated_at

For Xendit:

provider = xendit
provider_invoice_id = ...
external_id = ...
status = pending

Payment status

I'd use:

pending
paid
failed
expired
refunded
partially_refunded

Don't use only:

is_paid BOOLEAN

because payment systems are asynchronous and have more than two states.


---

4. Very important: use integer money

Don't store:

decimal(15,2)

for IDR unless you have a specific reason.

Prefer:

$table->unsignedBigInteger('amount');

For example:

Rp150.000

amount = 150000

This avoids floating-point problems.

Your application should calculate:

$total = $subtotal + $tax - $discount;

and send that integer amount to Xendit.


---

5. Add unique constraints

This is extremely important for payment security.

For example:

$table->string('provider_payment_id')->nullable()->unique();
$table->string('provider_invoice_id')->nullable()->unique();
$table->string('external_id')->unique();

Depending on Xendit's exact API object you're integrating with, not every field will necessarily be populated for every flow, so make nullable provider IDs unique where appropriate.

This protects against:

Webhook #1
Webhook #2
Webhook #3

all trying to create the same payment.

Xendit explicitly warns that duplicate webhooks can happen and recommends using unique server-side identifiers to prevent duplicate processing. 


---

6. Add a dedicated webhook table

I highly recommend:

payment_webhooks
-----------------------------
id
provider
event_id
event_type
provider_payment_id
payload
processed_at
failed_at
error_message
created_at

For example:

id: 125
provider: xendit
event_id: abc123
event_type: payment.succeeded
provider_payment_id: py-xxxxx
payload: {...}
processed_at: 2026-08-29 14:32:10

Then:

$table->unique([
    'provider',
    'event_id',
]);

This gives you idempotency.


---

7. Webhook security

Your endpoint could be:

POST /api/webhooks/xendit

Do not authenticate this using normal user authentication.

Instead:

Xendit
   ↓
POST /webhooks/xendit
   ↓
x-callback-token
   ↓
Laravel verifies secret

Xendit provides an x-callback-token header specifically for authenticating webhook requests and recommends keeping the token secret. 

Laravel example:

public function handle(Request $request)
{
    $token = $request->header('x-callback-token');

    if (! hash_equals(
        config('services.xendit.webhook_token'),
        $token ?? ''
    )) {
        abort(403);
    }

    // Process webhook...
}

In .env:

XENDIT_SECRET_KEY=...
XENDIT_WEBHOOK_TOKEN=...

And:

// config/services.php

'xendit' => [
    'secret_key' => env('XENDIT_SECRET_KEY'),
    'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
],

Never put the Xendit secret key in frontend JavaScript.


---

8. Don't trust the webhook blindly

Even after validating:

x-callback-token

you should validate the payment against your own database.

For example:

Webhook says:

invoice_id = inv-123
status = PAID
amount = 150000

Find:

Payment::where(
    'provider_invoice_id',
    $invoiceId
)->first();

Then verify:

Xendit amount
       ==
Database payment amount

and:

Xendit currency
       ==
Database currency

and ideally:

Xendit invoice ID
       ==
Database invoice ID

This prevents an incorrectly associated payment from marking another order as paid.


---

9. The most important transaction pattern

Suppose Xendit sends:

PAID

Your webhook should essentially do:

DB::transaction(function () use ($paymentData) {

    $payment = Payment::query()
        ->where('provider_invoice_id', $paymentData['invoice_id'])
        ->lockForUpdate()
        ->firstOrFail();

    if ($payment->status === 'paid') {
        return;
    }

    // Validate amount
    // Validate currency
    // Validate invoice
    // Validate order

    $payment->update([
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $payment->order->update([
        'status' => 'paid',
    ]);
});

Laravel supports database transactions through DB::transaction(), and lockForUpdate() can be used for pessimistic locking. Laravel's documentation recommends wrapping pessimistic locks inside transactions. 

This protects you from concurrent webhook processing.


---

10. Example race condition

Imagine Xendit sends the webhook twice:

Webhook A ──┐
            ├── Laravel
Webhook B ──┘

Without locking:

A → read payment = pending
B → read payment = pending

A → update paid
B → update paid

You can potentially execute business logic twice.

With:

->lockForUpdate()

you get:

A → lock payment
A → update paid
A → commit
B → acquire lock
B → sees already paid
B → exit

Much safer.


---

11. Don't call Xendit inside your DB transaction

This is an important architectural detail.

Avoid:

DB::transaction(function () {

    $order = Order::create(...);

    $invoice = Xendit::createInvoice(...);

    $payment = Payment::create(...);
});

Why?

Because you're holding a database transaction while waiting for an external HTTP API.

Instead:

Step 1

Create your local records:

Order
Payment

inside a short transaction.

Step 2

Commit.

Step 3

Call Xendit.

Step 4

Save the Xendit invoice information.

For example:

pending
    ↓
creating_payment
    ↓
payment_link_created
    ↓
customer_pays
    ↓
paid

This avoids long-running DB transactions.


---

12. Better payment lifecycle

I'd design it like this:

ORDER CREATED
      │
      ▼
PAYMENT PENDING
      │
      ▼
CREATE XENDIT INVOICE
      │
      ├── failed → PAYMENT FAILED
      │
      ▼
PAYMENT LINK CREATED
      │
      ▼
CUSTOMER OPENS LINK
      │
      ▼
CUSTOMER PAYS
      │
      ▼
XENDIT WEBHOOK
      │
      ▼
VERIFY WEBHOOK
      │
      ▼
VERIFY PAYMENT
      │
      ▼
LOCK PAYMENT
      │
      ▼
MARK PAID
      │
      ▼
MARK ORDER PAID


---

13. Customer redirect ≠ payment confirmation

Suppose your Xendit invoice redirects customers to:

/payment/success

Don't do this:

public function success()
{
    $order->update([
        'status' => 'paid'
    ]);
}

That's insecure.

The customer can simply access:

/payment/success?order=123

without paying.

Instead:

Customer
   ↓
Xendit
   ↓
payment
   ↓
Xendit webhook
   ↓
Laravel
   ↓
Order = PAID

The success page should simply display the current state from your database.


---

14. Payment link creation

I'd create a service:

app/
├── Services/
│   └── Xendit/
│       └── XenditPaymentService.php

Rather than putting Xendit code inside:

OrderController

Keep the controller thin:

public function checkout(Order $order)
{
    $payment = $this->paymentService
        ->createPayment($order);

    return redirect($payment->checkout_url);
}

Then:

class XenditPaymentService
{
    public function createPayment(Order $order): Payment
    {
        // Create Xendit invoice
        // Save payment
        // Return payment
    }
}

This makes it much easier to replace Xendit later.


---

15. Use an interface

If you're building this as a serious project, I'd go one step further:

interface PaymentGatewayInterface
{
    public function createPayment(Order $order): PaymentResponse;

    public function getPayment(string $paymentId): PaymentResponse;

    public function expirePayment(string $paymentId): void;
}

Then:

PaymentGatewayInterface
          │
          ├── XenditPaymentGateway
          │
          ├── MidtransPaymentGateway
          │
          └── StripePaymentGateway

Your business logic doesn't become tightly coupled to Xendit.


---

16. Recommended Laravel structure

For Laravel 13:

app/
├── Actions/
│   └── Payments/
│       ├── CreatePayment.php
│       ├── ProcessPaymentWebhook.php
│       └── MarkPaymentAsPaid.php
│
├── Http/
│   └── Controllers/
│       ├── CheckoutController.php
│       └── Webhooks/
│           └── XenditWebhookController.php
│
├── Models/
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Payment.php
│   └── PaymentWebhook.php
│
├── Services/
│   └── Payments/
│       ├── PaymentGatewayInterface.php
│       └── XenditPaymentGateway.php
│
└── Jobs/
    └── ProcessXenditWebhook.php

This is cleaner than:

Xendit code everywhere


---

17. Queue webhook processing

For production, I would also consider:

Xendit
 ↓
Webhook Controller
 ↓
Validate token
 ↓
Store webhook
 ↓
HTTP 200
 ↓
Queue
 ↓
Process payment

This is especially useful because Xendit retries webhook delivery when it doesn't receive a successful 2xx response. 

Laravel queues can also be configured so jobs dispatched within DB transactions wait until the transaction commits by using after_commit. 

However, don't acknowledge a webhook before you've durably recorded enough information to process it.

A good pattern is:

POST webhook
     ↓
Authenticate
     ↓
Validate basic payload
     ↓
INSERT payment_webhooks
     ↓
COMMIT
     ↓
Return 200
     ↓
Queue processing

Then the queue worker processes it idempotently.


---

18. Idempotency is critical

Your webhook handler should be safe if Xendit sends:

payment.succeeded
payment.succeeded
payment.succeeded

or the same event is processed by two workers.

Think of:

processWebhook($event)

as an operation that can safely run:

1 time
10 times
100 times

and the final database state remains:

payment = paid
order = paid

not:

payment = paid
order = paid
order fulfillment = created 3 times


---

19. Protect fulfillment too

This is often overlooked.

Imagine:

Payment paid
      ↓
Create subscription

If the webhook is duplicated:

Webhook 1 → create subscription
Webhook 2 → create subscription

Now the customer gets two subscriptions.

So your business operation should also be idempotent.

For example:

order_id UNIQUE

in:

subscriptions

or:

fulfillments

Then:

Fulfillment::firstOrCreate([
    'order_id' => $order->id,
]);


---

20. Database constraints I recommend

At minimum:

orders
 └── order_number UNIQUE

payments
 ├── external_id UNIQUE
 ├── provider_invoice_id UNIQUE
 └── provider_payment_id UNIQUE

payment_webhooks
 └── provider + event_id UNIQUE

order_items
 └── order_id + product_id (depending on requirements)

fulfillments
 └── order_id UNIQUE

Don't rely solely on Laravel validation for uniqueness.

Use database-level constraints.


---

21. Keep an immutable payment history

For financial systems, I prefer having:

payments

for the current state, plus:

payment_transactions

for history.

For example:

payment_transactions
--------------------------------
id
payment_id
type
status
amount
provider
provider_transaction_id
metadata
created_at

Example:

payment
   │
   ├── created
   ├── pending
   ├── paid
   └── refunded

This is especially valuable once you introduce:

refunds

partial refunds

payment retries

disputes

reconciliation

accounting



---

22. Recommended MVP schema

If this is a relatively simple Laravel application, don't over-engineer it initially.

I'd start with:

users
orders
order_items
payments
payment_webhooks

Orders

id
user_id
order_number
status
subtotal
discount
tax
total
currency
timestamps

Order Items

id
order_id
product_id
product_name
quantity
unit_price
subtotal
timestamps

Notice:

product_name
unit_price

are copied into order_items.

Don't depend on the current product price later.


---

Payments

id
order_id
provider
external_id
provider_invoice_id
provider_payment_id
status
amount
currency
payment_method
checkout_url
expires_at
paid_at
metadata
timestamps


---

Payment Webhooks

id
provider
event_id
event_type
provider_payment_id
payload
processed_at
failed_at
error_message
timestamps


---

23. Security checklist

Before going production, I'd make sure you have:

Xendit

[x] Secret API key stored in .env

[x] Never expose secret key to frontend

[x] Separate Test and Live credentials

[x] HTTPS

[x] Webhook token verification

[x] Server-side webhook processing

[x] Amount validation

[x] Currency validation

[x] Invoice/payment ID validation

[x] Duplicate webhook protection

[x] Idempotent payment processing


Xendit's documentation specifically recommends webhook authentication and server-side handling because manipulating webhook data/client-side handling can create payment-security risks. 

Database

[x] Database transactions

[x] lockForUpdate()

[x] Unique constraints

[x] Integer money values

[x] Payment status enum

[x] Immutable webhook records

[x] Audit/history

[x] Foreign keys

[x] Indexes


Application

[x] Don't trust frontend amount

[x] Don't trust redirect/success page

[x] Don't trust order_id alone

[x] Don't mark paid from frontend

[x] Don't expose payment secrets

[x] Don't call external API inside DB transaction

[x] Make fulfillment idempotent



---

24. My recommended final architecture

For Laravel 13 + Xendit Payment Link, I'd build it like this:

┌──────────────┐
                    │   Customer   │
                    └──────┬───────┘
                           │
                      Create Order
                           │
                           ▼
                 ┌───────────────────┐
                 │    Laravel 13     │
                 │                   │
                 │ Order             │
                 │ OrderItem         │
                 │ Payment           │
                 └─────────┬─────────┘
                           │
                    Create Invoice
                           │
                           ▼
                    ┌─────────────┐
                    │   Xendit    │
                    └──────┬──────┘
                           │
                    Payment Link
                           │
                           ▼
                      Customer pays
                           │
                           ▼
                    ┌─────────────┐
                    │   Xendit    │
                    └──────┬──────┘
                           │
                        Webhook
                           │
                           ▼
              ┌────────────────────────┐
              │ XenditWebhookController│
              └────────────┬───────────┘
                           │
                  Verify callback token
                           │
                           ▼
                  Store webhook event
                           │
                           ▼
                         Queue
                           │
                           ▼
              ┌─────────────────────────┐
              │ ProcessPaymentWebhook   │
              └────────────┬────────────┘
                           │
                    DB Transaction
                           │
                     lockForUpdate()
                           │
                           ▼
                 Validate payment
                           │
                           ▼
                  Payment = PAID
                           │
                           ▼
                    Order = PAID
                           │
                           ▼
                     Fulfillment

One architectural principle I'd emphasize

Separate these three concepts:

Order
  ↓
What the customer bought

Payment
  ↓
How/when the customer paid

Payment Webhook
  ↓
What Xendit told your system

Don't combine them into one table.

That separation will make your application much easier to secure, debug, reconcile, refund, and extend later.

For the current Xendit API, I'd also base the implementation on the current invoice/payment API rather than older integration examples, because Xendit's API documentation has evolved and the current PHP SDK documents invoice creation through the v2 invoice endpoint. 

If you're building this now, **I would use the above architecture as the baseline rather than simply installing an Xendit package and putting createInvoice() directly inside a controller.**