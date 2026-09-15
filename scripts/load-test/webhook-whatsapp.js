// Load/abuse test for /webhook/whatsapp — see README.md before running.
//
// Exercises the Phase 1 critical-DoS fix (previously: no rate limit, fully synchronous
// processing, no body-size cap — see .claude/plans or the PR description for the finding):
//   1. `throttle:webhook` now applies here too — expect 429s once past 120/min per IP.
//   2. A >1MB body must be rejected 413 before any parsing/encryption happens.
//   3. Processing is now queued (ProcessWhatsappWebhookJob) — this script cannot observe
//      job completion directly (see README's post-run verification step for that).
//   4. Correctly-HMAC-signed GOWA payloads must still be accepted under load; unsigned/
//      wrong-signature ones must still be rejected (401), not silently let through.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { hmac } from 'k6/crypto';

const BASE_URL = __ENV.BASE_URL;
const GOWA_SESSION = __ENV.GOWA_SESSION; // must match a real Sysblas.session_name on the target
const GOWA_SECRET = __ENV.GOWA_SECRET; // that Sysblas row's api_secret

if (!BASE_URL || !GOWA_SESSION || !GOWA_SECRET) {
  throw new Error('Set BASE_URL, GOWA_SESSION and GOWA_SECRET env vars before running.');
}
if (!/staging|local|test/i.test(BASE_URL) && !__ENV.FORCE) {
  throw new Error(
    `BASE_URL "${BASE_URL}" doesn't look like staging/local. ` +
    `This script can trigger real outbound WhatsApp sends and ticket writes if it reaches ` +
    `a live session. Set FORCE=1 if you are absolutely sure.`
  );
}

export const options = {
  stages: [
    { duration: '30s', target: 20 },
    { duration: '1m', target: 150 }, // intentionally past the 120/min-per-IP limit
    { duration: '30s', target: 0 },
  ],
};

function signedPost(bodyObj) {
  const body = JSON.stringify(bodyObj);
  const signature = hmac('sha256', GOWA_SECRET, body, 'hex');

  return http.post(`${BASE_URL}/webhook/whatsapp`, body, {
    headers: {
      'Content-Type': 'application/json',
      'X-Gowa-Signature': signature,
    },
  });
}

export default function () {
  const body = {
    id: `k6-wa-${__VU}-${__ITER}-${Date.now()}`,
    event: 'session.status',
    session: GOWA_SESSION,
    payload: { status: 'WORKING' },
  };

  const res = signedPost(body);

  check(res, {
    'status is 200 or 429': (r) => r.status === 200 || r.status === 429,
    'never 500': (r) => r.status < 500,
  });

  // Every ~50th iteration, also check the body-size guard and bad-signature rejection —
  // not the main load pattern, just a periodic assertion that hardening didn't regress
  // under concurrent load.
  if (__ITER % 50 === 0) {
    const oversized = http.post(`${BASE_URL}/webhook/whatsapp`, 'x'.repeat(2 * 1024 * 1024), {
      headers: { 'Content-Type': 'application/json' },
    });
    check(oversized, { 'oversized body rejected 413': (r) => r.status === 413 });

    const tampered = http.post(
      `${BASE_URL}/webhook/whatsapp`,
      JSON.stringify({ id: 'bad-sig-check-2', event: 'session.status', session: GOWA_SESSION, payload: {} }),
      { headers: { 'Content-Type': 'application/json', 'X-Gowa-Signature': 'deadbeef' } }
    );
    check(tampered, { 'bad signature still rejected 401': (r) => r.status === 401 });
  }

  sleep(0.05);
}
