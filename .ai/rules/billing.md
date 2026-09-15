---
paths:
  - app/Services/Billing/BillingService.php
---

# Billing

## Ad-hoc invoices bypass the periode_tagihan unique guard via NULL, not app logic
`generateManualInvoice()` (ad-hoc/manual invoices: install fees, penalties, etc.) deliberately leaves `periode_tagihan = null` and has NO idempotency/duplicate guard, unlike `generateInvoice()` (recurring monthly billing). This is safe only because of how `unique_active_layanan_periode` (migration `2026_08_23_170000_add_unique_constraint_to_invoice_table`) is built: on MySQL it's a virtual column `CONCAT(layanan_pelanggan_id, '-', periode_tagihan)` which itself evaluates to NULL whenever `periode_tagihan` is NULL (MySQL `CONCAT` with a NULL arg returns NULL); on SQLite it's a partial unique index over the same two columns. Both treat NULL as non-colliding, so any number of manual invoices can exist for the same `layanan_pelanggan_id` with no schema conflict.

**Why this matters:** if a future change ever backfills `periode_tagihan` on manual invoices (e.g. to show a "billing period" in a report), it will silently start colliding with real monthly invoices under this same unique constraint. Don't set `periode_tagihan` on ad-hoc invoices without re-reading this constraint first. Reuse `applyPromoUsage()` (shared by both `generateInvoice()` and `generateManualInvoice()`) rather than re-duplicating the PromoPenggunaan/terpakai_global bookkeeping for any future invoice-creation path.
