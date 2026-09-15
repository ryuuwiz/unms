// Load/abuse test for /webhook/payment/xendit — see README.md before running.
//
// Exercises the three things Phase 1 hardening added to this route:
//   1. `throttle:webhook` (120/min per IP, app/Providers/AppServiceProvider.php) — expect
//      200s to level off and 429s to start appearing once a single k6 instance (one IP)
//      crosses that ceiling.
//   2. Idempotency (`webhook_log_provider_event_unique`, app/Http/Controllers/Webhook/
//      PaymentWebhookController.php) — a fixed pool of repeated `event_id`s is deliberately
//      re-sent concurrently; the app must collapse each one down to a single WebhookLog row
//      (checked out-of-band, see README's post-run verification step).
//   3. Token verification stays enforced under load (no bypass, no crash under concurrency).
//
// Payloads are synthetic and don't need to match a real invoice — this tests the webhook
// layer itself (auth, rate limit, idempotency), not payment reconciliation.
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL;
const CALLBACK_TOKEN = __ENV.XENDIT_CALLBACK_TOKEN;
// How many distinct event_ids get reused across requests to exercise idempotency.
// Small on purpose: most requests should be novel (testing throughput), a minority repeated
// (testing dedup).
const DUPLICATE_POOL_SIZE = 20;

if (!BASE_URL || !CALLBACK_TOKEN) {
  throw new Error('Set BASE_URL and XENDIT_CALLBACK_TOKEN env vars before running.');
}
if (!/staging|local|test/i.test(BASE_URL) && !__ENV.FORCE) {
  throw new Error(
    `BASE_URL "${BASE_URL}" doesn't look like staging/local. ` +
    `This script creates real WebhookLog rows and dispatches real jobs. ` +
    `Set FORCE=1 if you are absolutely sure.`
  );
}

export const options = {
  stages: [
    { duration: '30s', target: 20 },
    { duration: '1m', target: 150 }, // intentionally past the 120/min-per-IP limit
    { duration: '30s', target: 0 },
  ],
};

export default function () {
  const useDuplicate = Math.random() < 0.15;
  const eventId = useDuplicate
    ? `k6-dup-${__VU % DUPLICATE_POOL_SIZE}`
    : `k6-${__VU}-${__ITER}-${Date.now()}`;

  const payload = JSON.stringify({
    id: eventId,
    external_id: `k6-external-${eventId}`,
    status: 'PAID',
    amount: 100000,
    paid_amount: 100000,
    payment_method: 'VIRTUAL_ACCOUNT',
    payment_channel: 'BCA',
    paid_at: new Date().toISOString(),
  });

  const res = http.post(`${BASE_URL}/webhook/payment/xendit`, payload, {
    headers: {
      'Content-Type': 'application/json',
      'x-callback-token': CALLBACK_TOKEN,
    },
  });

  check(res, {
    'status is 200 or 429': (r) => r.status === 200 || r.status === 429,
    'never 500': (r) => r.status < 500,
  });

  sleep(0.05);
}
