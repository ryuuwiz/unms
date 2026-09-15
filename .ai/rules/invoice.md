---
paths:
  - 'app/Livewire/Portal/Invoice/**'
---

# Invoice

## Public invoice/checkout access is signed-URL, not route middleware
`portal.invoice.show` and `portal.invoice.bayar` are intentionally OUTSIDE the `auth:pelanggan` middleware group in `routes/web.php` — access is checked inside `mount()` via `AuthorizesInvoiceAccess::authorizeAksesTagihan()` (trait in `app/Livewire/Portal/Invoice/Concerns/`), which allows EITHER an authenticated `pelanggan` session that owns the invoice OR a valid Laravel signed URL (`request()->hasValidSignature()`) for that specific invoice id. This is what lets `WhatsappService::buildInvoiceParams()`'s `link_pembayaran` (a `URL::signedRoute(...)`, 30-day expiry) work without login from WA/email notifications.

Any new portal invoice/payment page that should be reachable from a notification link must use this same trait/pattern, not `auth:pelanggan` middleware — a signature is scoped to one invoice id, so it doesn't need a separate ownership check.

Payment-link generation is centralized in `PaymentGatewayManager::resolvePaymentUrl(Invoice)` (sync status, reuse or regenerate the gateway link, return the URL) — both `Show::bayar()` and `Bayar::lanjutkanPembayaran()` call this instead of duplicating the sync/generate logic. Xendit here only produces one flat Hosted Invoice link (not per-channel VA/QRIS/e-wallet products), so channel fee cards on the `Bayar` page are informational estimates only, not a live per-channel price — don't wire a channel picker to different backend calls unless the Xendit integration itself changes to per-channel payment requests.
