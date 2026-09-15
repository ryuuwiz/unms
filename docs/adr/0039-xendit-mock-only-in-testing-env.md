# Xendit Mock Only Ever Triggers on `APP_ENV=testing`, Never `local`

## Context

`XenditDriver::createPaymentLink()` and `checkStatus()` decided whether to
fake a Xendit response (skip the real API call entirely) using
`app()->environment(['local', 'testing'])`. Two separate problems came from
this single check:

1. **Production risk.** If a deployed server's `APP_ENV` was ever
   misconfigured to read `local` (a stale `.env`, a bad deploy, a copy-paste
   from a dev template), the driver would silently fabricate a payment link:
   `https://checkout-staging.xendit.co/v2/inv_mock_<uniqid>`. This is not a
   dead URL — `checkout-staging.xendit.co` is Xendit's real domain, and it
   redirects to a real Xendit checkout shell (`/web/<id>`) that then reports
   "invoice not found" once it looks up the fabricated ID. The failure looks
   exactly like a real Xendit-side problem to the customer, with no
   exception thrown and nothing logged on our side. This is the confirmed
   root cause of a production report where a customer's payment link led to
   a genuine Xendit error page for an invoice that was never created.
2. **Local dev regression.** A developer with a real Xendit test-mode key
   configured (`XENDIT_SECRET_KEY=xnd_development_...`, which actually
   works against Xendit's sandbox) never got to use it: `local` was always
   mocked unconditionally, so "Bayar Sekarang" always produced the same
   unusable fake link even when a perfectly good sandbox key was sitting in
   `.env`. This is very likely what "used to work, now it doesn't" refers
   to — whichever commit introduced the local/testing auto-mock shortcut
   changed local manual QA from "hits real Xendit sandbox" to "always
   fake," as a side effect of making automated tests deterministic.

### Considered and rejected: gating on `PengaturanGateway.sandbox_mode`

A first attempt required `app()->environment(['local', 'testing']) &&
$setting->sandbox_mode` before mocking. This still left `local` in the
dangerous list (a misconfigured `APP_ENV=local` in production would still
mock, if that gateway row's `sandbox_mode` happened to be left on) and
conflated two different concepts: `sandbox_mode` means "use Xendit's
test-mode credentials for a *real* API call," not "skip calling Xendit
entirely." Reusing it for the second meaning is exactly what caused the
local-dev regression above.

## Decision

The internal, fully-offline mock is gated on **`app()->environment('testing')`
alone** — the one `APP_ENV` value that only ever comes from `phpunit.xml`,
never from a real deployment. Every other environment (`local`, `staging`,
`production`, anything else) always attempts a real Xendit API call using
whatever key is configured:

- A real test key configured (as in local dev here) → real sandbox
  invoice, real working checkout page.
- No key / wrong key in a non-testing environment → the existing
  `empty($apiKey)` guard throws a loud, logged error instead of silently
  faking success.

`PengaturanGateway.sandbox_mode` is no longer read by `XenditDriver` at all;
it stays a purely informational/UI field (as it already was for `IpaymuDriver`
and the admin settings screen).

`pingConnection()` was left unchanged: it already only mocks under
`app()->environment('testing')`.

## Consequences

Local dev without any Xendit key configured will now see a real
`empty($apiKey)` error instead of a fake-but-harmless mock link when testing
the payment flow manually — this is correct (matches production behavior
exactly) but means a fresh clone needs a real Xendit test key in `.env` to
exercise this flow end-to-end locally, same as any other environment.
