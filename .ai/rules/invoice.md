---
paths:
  - 'app/Livewire/Portal/Invoice/**'
---

# Invoice

## Public invoice/checkout access is Tautan Tagihan / signature, not route middleware
`portal.invoice.show` and `portal.tagihan.tautan` (`/t/{invoice:token_tautan}`) are intentionally OUTSIDE the `auth:pelanggan` middleware group in `routes/web.php` — access is checked inside `mount()` via `AuthorizesInvoiceAccess::authorizeAksesTagihan()` (trait in `app/Livewire/Portal/Invoice/Concerns/`), which allows (a) the Tautan Tagihan route (the token is the access key), (b) an old signed URL whose signature is genuine — expiry is deliberately IGNORED (`URL::hasCorrectSignature`) so links already sent keep working, or (c) an authenticated `pelanggan` session that owns the invoice. See ADR-0067.

Notification links and gateway redirect URLs must use `Invoice::tautanTagihan()` (lazy 16-char token, revoked via `gantiTokenTautan()`), never `URL::signedRoute(...)` with an expiry and never the bare `portal.invoice.show` route (a guest returning from the gateway would hit 403). Any new portal invoice/payment page reachable from a notification link must use this same trait, not `auth:pelanggan` middleware.

Payment-link generation is centralized in `PaymentGatewayManager::resolvePaymentUrl(Invoice)` (sync status, reuse or regenerate the gateway link, return the URL) — `Show::bayar()` calls this instead of duplicating the sync/generate logic. When at least one Channel Pembayaran is ON, the customer picks it on the invoice page (ADR-0073): `Show::pilihChannel()` → `PaymentGatewayManager::bayarLewatChannel()` (iPaymu Direct Payment) and the VA number / payment code / QR is rendered in the portal. Only `PaymentGatewayManager::channelTersedia()` decides which channels are offered. `Show::bayar()` (iPaymu Hosted Invoice, ADR-0072) is always offered: as the "Bayar Sekarang" button when no channel is ON, otherwise as the last "Metode lain" card in the picker.
