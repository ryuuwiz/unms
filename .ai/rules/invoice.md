---
paths:
  - 'app/Livewire/Portal/Invoice/**'
---

# Invoice

## Public invoice/checkout access is Tautan Tagihan / signature, not route middleware
`portal.invoice.show` and `portal.tagihan.tautan` (`/t/{invoice:token_tautan}`) are intentionally OUTSIDE the `auth:pelanggan` middleware group in `routes/web.php` — access is checked inside `mount()` via `AuthorizesInvoiceAccess::authorizeAksesTagihan()` (trait in `app/Livewire/Portal/Invoice/Concerns/`), which allows (a) the Tautan Tagihan route (the token is the access key), (b) an old signed URL whose signature is genuine — expiry is deliberately IGNORED (`URL::hasCorrectSignature`) so links already sent keep working, or (c) an authenticated `pelanggan` session that owns the invoice. See ADR-0067.

Notification links and gateway redirect URLs must use `Invoice::tautanTagihan()` (lazy 16-char token, revoked via `gantiTokenTautan()`), never `URL::signedRoute(...)` with an expiry and never the bare `portal.invoice.show` route (a guest returning from the gateway would hit 403). Any new portal invoice/payment page reachable from a notification link must use this same trait, not `auth:pelanggan` middleware.

Payment-link generation is centralized in `PaymentGatewayManager::resolvePaymentUrl(Invoice, ?GatewayChannel)` (sync status, reuse the active link for that metode or create one, return the URL) — `Show::bayar($metode)` calls this instead of duplicating the sync/generate logic. Whether the page shows one button or one per metode comes from the driver's `menghitungBiayaSendiri()`: Xendit (Payment Sessions) gets a VA and a QRIS button, each a session limited by `allowed_payment_channels` with its own Biaya Admin Gateway; iPaymu gets one button because its checkout prices channels itself (ADR-0072, ADR-0073). Links are created on click, never eagerly.
