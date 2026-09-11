# Panduan Webhook Xendit (Payment Gateway)

Panduan operasional untuk memahami, menguji, dan men-debug webhook payment gateway (Xendit &
iPaymu) di aplikasi ini. Dokumen ini menjelaskan implementasi **nyata** di kodebase — untuk
keputusan arsitektur & alasan desainnya, lihat ADR terkait di §9.

> Ada juga `docs/xendit-laravel.md` — itu adalah catatan riset/rekomendasi umum integrasi Xendit
> di Laravel (bukan deskripsi kodebase ini). Dokumen **ini** yang menjelaskan bagaimana aplikasi
> sebenarnya bekerja.

## 1. Peta arsitektur

```
Xendit / iPaymu
        │  POST callback (x-callback-token header)
        ▼
POST /webhook/payment/{gateway}     (public, CSRF-exempt, route: webhook.payment)
   [atau legacy: POST /webhook/xendit* → middleware xendit.token → alias ke handler yang sama]
        │
        ▼
PaymentWebhookController::handle($request, $gateway)
        │
        ├─ 1. Resolve driver via PaymentGatewayManager (xendit | ipaymu)
        ├─ 2. driver->verifyWebhook()        → cocokkan token/signature. Gagal → 401, TIDAK diproses
        ├─ 3. driver->parseWebhookPayload()  → normalisasi ke PaymentCallbackData (DTO generik)
        ├─ 4. Cek duplikat via WebhookLog (provider + event id)  → sudah ada → 200 "ALREADY_PROCESSED" (skip)
        ├─ 5. Catat WebhookLog (status: diterima) — SELALU, sebelum diproses
        ├─ 6. Jika payload dummy test dashboard → langsung 200, tidak masuk antrean
        └─ 7. Dispatch ProcessPaymentWebhookJob(webhookLogId) ke queue → respon 200 "QUEUED" (< 100ms)
                 │
                 ▼  (diproses async oleh queue worker)
        ProcessPaymentWebhookJob::handle()
                 ├─ Idempotency guard: skip jika WebhookLog sudah "diproses"
                 ├─ Cari TransaksiPaymentGateway & Invoice terkait (external_id / event id / no_invoice)
                 ├─ Jika PAID: validasi ketat nominal (exact match, integer Rupiah) → anomali → tolak + log "gagal"
                 ├─ Jika PAID & nominal cocok: PaymentGatewayManager::prosesPelunasan() (row-locked, idempoten)
                 │        └─ commit DB → event(InvoicePaidEvent) dipancarkan SETELAH commit
                 │                 ├─ CatatLogPembayaranListener        (audit log)
                 │                 ├─ TriggerMikrotikAktivasiStubListener (auto-provisi/aktivasi PPPoE)
                 │                 └─ TriggerWaNotifikasiStubListener    (notifikasi WA konfirmasi bayar)
                 ├─ Jika EXPIRED/FAILED: update status invoice/transaksi (jika belum lunas)
                 └─ Gagal 3x percobaan (backoff 10s/60s/300s) → status "gagal", Log::critical
```

**Penting**: `InvoicePaidEvent` yang sama juga dipancarkan oleh `BillingService::prosesPembayaranManual()`
untuk pembayaran manual/kasir (bukan lewat webhook) — jadi ketiga listener di atas berlaku untuk
**semua** metode pelunasan invoice, bukan cuma dari Xendit.

## 2. Route

| Method | Path | Nama Route | Middleware | Keterangan |
|---|---|---|---|---|
| POST | `/webhook/payment/{gateway}` | `webhook.payment` | `webhook/*` → CSRF-exempt | Endpoint **utama**, generik untuk semua provider (`{gateway}` = `xendit` atau `ipaymu`). Verifikasi token dilakukan **di dalam controller** via driver (`XenditDriver::verifyWebhook()`). |
| POST | `/webhook/xendit` | `webhook.xendit` | `xendit.token` (`ValidateXenditCallbackToken`) + `webhook/xendit/*` CSRF-exempt | Alias kompatibilitas mundur → delegasi ke handler yang sama dengan `gateway=xendit` |
| POST | `/webhook/xendit/virtual-account` | `webhook.xendit.va` | sama seperti di atas | Alias lama, sama-sama delegasi |
| POST | `/webhook/xendit/qris` | `webhook.xendit.qris` | sama seperti di atas | Alias lama, sama-sama delegasi |

File terkait:
- Route: `routes/web.php`
- Controller generik: `app/Http/Controllers/Webhook/PaymentWebhookController.php`
- Controller alias: `app/Http/Controllers/Webhook/XenditWebhookController.php`
- Middleware alias: `app/Http/Middleware/ValidateXenditCallbackToken.php` + `app/Services/Xendit/XenditWebhookVerifier.php`

> ⚠️ Rute alias `/webhook/xendit*` memverifikasi token **dua kali secara konseptual** — sekali di
> middleware (`XenditWebhookVerifier`, baca `config('services.xendit.callback_token')`), sekali lagi
> di dalam `PaymentWebhookController` via `XenditDriver::verifyWebhook()` (baca dari `PengaturanGateway`
> DB, fallback ke config yang sama). Keduanya harus mengarah ke token yang sama. **Gunakan
> `/webhook/payment/xendit` sebagai default** di dashboard Xendit untuk konfigurasi baru — alias
> `/webhook/xendit*` dipertahankan hanya untuk kompatibilitas mundur.

## 3. Kredensial & konfigurasi

Kredensial gateway **DB-driven** lewat tabel `pengaturan_gateway` (model `PengaturanGateway`,
kolom `credentials` di-encrypt), dikelola lewat menu **Pengaturan → Payment Gateway** di admin.
`.env` / `config/services.php` hanya **fallback** bila belum ada setting di DB:

```php
// config/services.php
'xendit' => [
    'secret_key' => env('XENDIT_SECRET_KEY'),
    'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
    'env' => env('XENDIT_ENV', 'development'),
],
'ipaymu' => [
    'va' => env('IPAYMU_VA', ''),
    'api_key' => env('IPAYMU_API_KEY', ''),
    'env' => env('IPAYMU_ENV', 'sandbox'),
],
```

Urutan resolusi credential (`XenditDriver::getApiKey()` / `getCallbackToken()`):
1. `PengaturanGateway->getCredential('secret_key' | 'callback_token')` (DB, per-provider, bisa multi-akun)
2. `config('services.xendit.secret_key' | 'callback_token')` (env, single fallback)

**Callback Token** didapat dari dashboard Xendit (Settings → Callbacks → Verification Token — bukan
webhook signing secret HMAC ala Stripe; Xendit pakai token statis di header `x-callback-token`).

## 4. Verifikasi keamanan

Xendit **tidak** memakai HMAC signature — cukup token statis yang dikirim balik di header, dibandingkan
dengan `hash_equals()` (constant-time) di `XenditDriver::verifyWebhook()`:

```php
$headerToken = $request->header('x-callback-token')
    ?: ($request->header('webhook-token') ?: $request->header('x-webhook-token'));
hash_equals($expectedToken, $headerToken);
```

- Token kosong di sisi kita → selalu ditolak (gagal aman / *fail closed*).
- Token tidak dikirim / tidak cocok → `401 Unauthorized`, payload **tidak** dicatat ke `WebhookLog`
  sama sekali (beda dengan WhatsApp webhook yang tetap log meski gagal verifikasi — lihat catatan
  di [`webhook-whatsapp-guide.md`](webhook-whatsapp-guide.md)).
- Pengecualian: di environment `testing`, verifikasi **dilewati** (`app()->environment('testing')`)
  supaya test tidak perlu menghitung token asli — jangan andalkan perilaku ini di staging/production.

## 5. Idempotensi & validasi nominal ketat

Dua lapis proteksi supaya webhook duplikat/replay tidak pernah dobel-catat pembayaran:

1. **Level aplikasi**: cek `WebhookLog` berdasarkan `(provider, provider_event_id)` sebelum masuk
   antrean — event id yang sudah pernah diterima langsung dijawab `200 ALREADY_PROCESSED` tanpa
   memproses ulang.
2. **Level database**: unique constraint komposit di `webhook_log (provider, provider_event_id)`,
   `transaksi_payment_gateway (gateway, provider_reference_id)`, dan `pembayaran (metode,
   referensi_transaksi)` — mencegah race condition dari pengiriman webhook paralel/duplikat yang
   lolos dari cek level aplikasi.
3. **Row locking**: `prosesPelunasan()` mengunci baris `Invoice` (`lockForUpdate()`) dalam
   `DB::transaction()`, dan langsung `return` lebih awal jika invoice **sudah** lunas (idempoten
   walau job dijalankan ulang).
4. **Validasi nominal ketat** (*strict rejection*): pembayaran hanya disetujui jika
   `(int) paid_amount === (int) total_tagihan` persis (integer Rupiah). Under/overpayment ditolak
   total (tidak auto-lunas), dicatat sebagai anomali di `webhook_log.catatan_error`, invoice
   **tidak** berubah status — perlu intervensi manual admin.

## 6. Efek pemrosesan (`PaymentCallbackData::status`)

| Status hasil parse | Efek |
|---|---|
| `PAID` (nominal cocok) | `prosesPelunasan()`: invoice → Lunas, `Pembayaran` dibuat (idempoten via `firstOrCreate`), masa aktif layanan diperpanjang, `InvoicePaidEvent` dipancarkan setelah commit |
| `PAID` (nominal **tidak** cocok) | Ditolak, `webhook_log.status_proses = gagal`, `catatan_error` berisi detail anomali |
| `EXPIRED` / `FAILED` | Invoice (jika belum lunas) → `payment_gateway_status = EXPIRED`, link pembayaran lama dihapus; `TransaksiPaymentGateway` → `Expired` bila masih `Pending` |
| Lainnya (mis. `PENDING`) | `webhook_log.status_proses = diproses`, tidak ada mutasi invoice |
| Invoice/transaksi tidak ditemukan | `webhook_log.status_proses = diabaikan` (kecuali payload dummy test dashboard → tetap 200) |

## 7. Cara menguji

### a. Simulasi lokal tanpa hit API Xendit sungguhan (paling praktis)

```php
// via tinker atau kode admin action
$service = app(\App\Services\Xendit\XenditPaymentService::class);
$service->simulasikanWebhookLokal($invoice); // atau TransaksiPaymentGateway
```

Method ini membangun payload `PAID` palsu yang realistis, menandatanganinya dengan
`callback_token` yang sesungguhnya terkonfigurasi, lalu memanggil
`PaymentWebhookController::handle()` **langsung secara in-process** (tanpa perlu tunnel publik
seperti ngrok). Cocok untuk uji end-to-end lokal termasuk `InvoicePaidEvent` dan efek sampingnya
(WA notifikasi, aktivasi MikroTik) — lihat kalau `QUEUE_CONNECTION=sync` job akan langsung jalan.

### b. Simulasi lewat HTTP mentah

```bash
BODY='{"id":"inv_test_123","external_id":"INV-2026-000001-abc","status":"PAID","amount":250000,"paid_amount":250000}'
curl -X POST http://localhost/webhook/payment/xendit \
  -H "Content-Type: application/json" \
  -H "x-callback-token: ISI_CALLBACK_TOKEN_ANDA" \
  -d "$BODY"
```

Perhatikan: respon `200 QUEUED` **bukan** berarti pembayaran sudah tercatat — cek
`vendor/bin/sail artisan queue:work` berjalan (atau `QUEUE_CONNECTION=sync` di lokal), lalu cek
`webhook_log.status_proses` menjadi `diproses`.

### c. Uji koneksi API / saldo

Menu **Pengaturan → Payment Gateway** → tombol "Ping/Cek Koneksi" (`PaymentGatewayManager::pingConnection()`),
atau lewat kode: `XenditPaymentService::cekKoneksiApi()`.

### d. Test otomatis (Pest)

```bash
vendor/bin/sail artisan test --compact --filter="Webhook|PaymentGateway|Pembayaran"
```

Cari test files terkait dengan `grep -rl "webhook.payment\|ProcessPaymentWebhookJob" tests/`.

## 8. Troubleshooting

| Gejala | Kemungkinan penyebab | Cek |
|---|---|---|
| `401 Unauthorized` terus dari webhook asli Xendit | `callback_token` di dashboard Xendit ≠ token di `PengaturanGateway`/`.env` | Cocokkan `x-callback-token` yang dikirim (`Log::warning` mencatat semua header saat gagal) |
| Webhook diterima (`200`) tapi invoice tidak pernah lunas | Job `ProcessPaymentWebhookJob` gagal/tertunda, atau nominal tidak match persis | Cek `queue:work` jalan; cek `webhook_log.catatan_error` untuk anomali nominal |
| Invoice lunas tapi WA notifikasi / aktivasi MikroTik tidak jalan | Listener `InvoicePaidEvent` gagal (mis. layanan belum punya IP Pool valid untuk provisioning) — exception di listener **tidak** membatalkan pelunasan invoice (event dipancarkan setelah commit) tapi *queue worker* bisa mencatat job listener gagal terpisah | Cek log queue worker untuk exception `TriggerMikrotikAktivasiStubListener`/`TriggerWaNotifikasiStubListener` |
| Webhook duplikat dari Xendit (retry) memproses ulang | Seharusnya tidak terjadi (idempotensi §5) — jika terjadi, cek `provider_event_id` benar-benar terisi dari payload | `ProcessPaymentWebhookJob` mensinkronkan `provider_event_id` jika awalnya kosong |
| Status webhook `diabaikan` | Invoice/transaksi tidak ketemu berdasarkan `external_id`/`event id`/`no_invoice` | Cek `external_id` yang dikirim saat `buatPaymentLink()` vs yang dikirim balik Xendit |

## 9. ADR terkait (keputusan arsitektur)

- `docs/adr/0007-xendit-payment-gateway-and-customer-portal-architecture.md` — arsitektur awal integrasi Xendit.
- `docs/adr/0026-multi-payment-gateway-architecture-and-dynamic-credentials.md` — abstraksi multi-gateway (`PaymentGatewayManager`, kredensial dinamis di DB).
- `docs/adr/0028-payment-gateway-webhook-queue-idempotency-and-strict-validation.md` — queue asinkron, idempotensi DB-level, validasi nominal ketat (dasar dari §5–§6 di atas).

## 10. Referensi kode

| Komponen | File |
|---|---|
| Route | `routes/web.php` |
| Controller webhook generik | `app/Http/Controllers/Webhook/PaymentWebhookController.php` |
| Controller alias Xendit | `app/Http/Controllers/Webhook/XenditWebhookController.php` |
| Middleware alias | `app/Http/Middleware/ValidateXenditCallbackToken.php` |
| Job pemroses async | `app/Jobs/PaymentGateway/ProcessPaymentWebhookJob.php` |
| Manager multi-gateway | `app/Services/PaymentGateway/PaymentGatewayManager.php` |
| Driver Xendit | `app/Services/PaymentGateway/Drivers/XenditDriver.php` |
| Driver iPaymu | `app/Services/PaymentGateway/Drivers/IpaymuDriver.php` |
| Service facade Xendit (legacy-friendly API) | `app/Services/Xendit/XenditPaymentService.php` |
| Model kredensial gateway | `app/Models/PengaturanGateway.php` |
| Model log webhook | `app/Models/WebhookLog.php` |
| Event domain | `app/Events/InvoicePaidEvent.php` |
| Listener efek samping | `app/Listeners/CatatLogPembayaranListener.php`, `TriggerMikrotikAktivasiStubListener.php`, `TriggerWaNotifikasiStubListener.php` |
