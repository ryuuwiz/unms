# Webhook load tests

k6 scripts exercising the webhook hardening added to this app (rate limiting, idempotency,
the WhatsApp DoS fix). Not a project dependency — [k6](https://k6.io) is a standalone binary,
nothing here touches `composer.json`/`package.json`.

## ⚠️ Staging only — never production

Both scripts refuse to run unless `BASE_URL` looks like staging/local, or `FORCE=1` is set.
Running these against production will:

- Trip the real 120/min-per-IP rate limit and lock out real gateway callbacks from that IP
  for up to a minute.
- Create real `WebhookLog` rows (and, for the payment script, dispatch real
  `ProcessPaymentWebhookJob` runs) against production data.
- The WhatsApp script can trigger real outbound WhatsApp sends / ticket-history writes if it
  happens to hit a live, correctly-configured GOWA session.

Only run these against a staging deployment you own, with staging credentials.

## Install k6

```
# macOS
brew install k6
# Debian/Ubuntu
sudo gpg -k && sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install k6
```

## Payment webhook (Xendit)

```
k6 run scripts/load-test/webhook-payment-xendit.js \
  -e BASE_URL=https://staging.example.com \
  -e XENDIT_CALLBACK_TOKEN=<staging callback token>
```

Pass criteria:
- `200`s under normal load (first ~30s ramp).
- `429`s start appearing once concurrency crosses the 120/min-per-IP limit — this is expected
  and correct, not a failure.
- No `500`s at any point.
- **Idempotency (verify separately, k6 can't check this itself):** the script deliberately
  resends ~15% of requests with one of 20 repeated `event_id`s. After the run, on staging:
  ```
  php artisan tinker --execute 'echo App\Models\WebhookLog::where("provider", "xendit")->where("provider_event_id", "like", "k6-dup-%")->select("provider_event_id")->groupBy("provider_event_id")->havingRaw("count(*) > 1")->count();'
  ```
  Must print `0` — if any `event_id` produced more than one row, idempotency broke under
  concurrent load.

## WhatsApp webhook (GOWA)

Requires a real (staging) GOWA `Sysblas` row's `session_name` and `api_secret`:

```
k6 run scripts/load-test/webhook-whatsapp.js \
  -e BASE_URL=https://staging.example.com \
  -e GOWA_SESSION=<staging Sysblas.session_name> \
  -e GOWA_SECRET=<staging Sysblas.api_secret>
```

Pass criteria:
- `200`s under normal load, `429`s past the rate limit (same as above), no `500`s.
- Periodic checks (every 50th iteration) confirm a >1MB body still gets `413` and a bad
  signature still gets `401` even while the endpoint is under concurrent load — hardening
  must not degrade under load.
- **Queue drain (verify separately):** processing is queued (`ProcessWhatsappWebhookJob`,
  `wa-blast` queue), so a `200` here only means "accepted and queued," not "fully processed."
  After the run, check Horizon (staging dashboard) or:
  ```
  php artisan tinker --execute 'echo App\Models\WebhookLog::where("provider", "gowa")->where("provider_event_id", "like", "k6-wa-%")->where("status_proses", "diterima")->count();'
  ```
  Should trend to `0` shortly after the run ends (rows moving `diterima` → `diproses` as the
  queue drains) — a number that stays stuck means jobs are being dropped or failing.

## Not run in CI

These are manual/on-demand tools against a real staging environment, not part of the PR test
suite.
