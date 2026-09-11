# Panduan Webhook WhatsApp (GOWA / WAHA)

Panduan operasional untuk memahami, menguji, dan men-debug webhook WhatsApp di aplikasi ini.
Dokumen ini menjelaskan implementasi **nyata** di kodebase — bukan dokumentasi API generik.
Untuk detail lengkap REST API GOWA, lihat [`docs/gowa/openapi.yaml`](gowa/openapi.yaml).

> Arsitektur driver multi-provider (`WhatsappGatewayDriverInterface`, `GowaDriver`, `WahaDriver`)
> dan alasan migrasi dari Wablas ke GOWA didokumentasikan lebih lengkap sebagai ADR terpisah bila
> dibutuhkan; dokumen ini fokus pada **jalur webhook** (pesan masuk & status pengiriman).

## 1. Peta arsitektur

```
Gateway WA (GOWA / WAHA)
        │  POST event (message / message.ack / session.status / ...)
        ▼
POST /webhook/whatsapp  (public, CSRF-exempt, route: webhook.whatsapp)
        │
        ▼
WhatsappWebhookController::handle()
        │
        ├─ 1. resolveGowaSysblas(payload)   → cari koneksi Sysblas (provider=gowa) via session/device_id
        ├─ 2. verifyGowaSignature()          → HMAC-SHA256 bila koneksi itu punya webhook_secret
        │        └─ tidak valid → 401 Unauthorized (request dibuang, tidak diproses)
        └─ 3. WhatsappWebhookService::process($payload)   → SELALU log ke WebhookLog dulu, baru diproses
                 │
                 ├─ format {event, session, payload}   → handleWahaEvent()      (GOWA & WAHA, shape sama)
                 │      ├─ message.ack        → handleWahaMessageAck()   → update AntrianWaBlast
                 │      ├─ session.status      → handleWahaSessionStatus() → aktifkan Sysblas terkait
                 │      └─ message / message.any → handleIncomingMessage() → auto-reply + histori tiket
                 │
                 ├─ format flat {message, phone|sender}  → handleIncomingMessage()   (legacy / simulasi lokal)
                 └─ format flat {status, phone|id}       → handleTrackingStatus()    (legacy DLR)
```

Semua pemrosesan **sinkron** dalam request HTTP (bukan queue) — beda dengan webhook Xendit/payment
gateway yang diantre lewat job (lihat [`docs/webhook-xendit-guide.md`](webhook-xendit-guide.md)).
Ini karena volume webhook WA jauh lebih rendah dan efeknya (update `AntrianWaBlast`, balas WA)
tidak butuh SLA respon < 100ms yang ketat seperti gateway pembayaran.

## 2. Route

| Method | Path | Nama Route | Middleware | Keterangan |
|---|---|---|---|---|
| POST | `/webhook/whatsapp` | `webhook.whatsapp` | `webhook/*` → CSRF-exempt (lihat `bootstrap/app.php`) | Endpoint tunggal untuk **semua** provider (GOWA & WAHA). Tidak ada endpoint terpisah per provider. |

File terkait:
- Route: `routes/web.php`
- Controller: `app/Http/Controllers/Webhook/WhatsappWebhookController.php`
- Service pemroses: `app/Services/Whatsapp/WhatsappWebhookService.php`

> Riwayat: sebelumnya ada `/webhook/wablas`, `/webhook/wablas/tracking`, `/webhook/wablas/message`
> (era gateway Wablas). Ketiganya sudah **dihapus** saat migrasi ke GOWA — jangan konfigurasikan
> webhook lama itu lagi di dashboard gateway manapun.

## 3. Cara mendaftarkan webhook ke server GOWA

GOWA mengelola webhook **per device** (`PATCH /devices/{device_id}/webhook`). Aplikasi ini
mendaftarkan `webhook_url` secara **otomatis** setiap kali admin menyimpan koneksi `Sysblas`
dengan provider `gowa` di menu **Pengaturan → Koneksi WhatsApp**:

- Kode: `App\Livewire\Sysblas\Koneksi\Index::daftarkanWebhookGowa()` → `GowaDriver::registerWebhook()`.
- Best-effort: jika registrasi gagal (mis. server GOWA belum reachable), koneksi **tetap tersimpan**,
  hanya muncul toast peringatan — admin perlu daftarkan manual lewat dashboard GOWA atau simpan ulang.
- `webhook_url` yang didaftarkan selalu `route('webhook.whatsapp')` (endpoint generik, sama untuk
  semua device/provider) — GOWA tidak butuh URL unik per device karena payload event membawa
  identitas `session`/`device_id` sendiri.

Untuk WAHA, registrasi webhook dilakukan manual di sisi konfigurasi server WAHA (di luar cakupan
aplikasi ini) — arahkan ke `route('webhook.whatsapp')` yang sama.

## 4. Bentuk payload & deteksi format

`WhatsappWebhookService::process()` **tidak** membaca header/route berbeda per provider — ia
menebak bentuk payload dari isinya (lihat kode untuk urutan pengecekan persis):

### a. Format event-driven `{event, session, payload}` — GOWA & WAHA

```json
{
  "event": "message",
  "session": "org_2",
  "payload": {
    "id": "3EB0B430B6F8F1D0E053AC120E0A9E5C",
    "from": "6281234567890@s.whatsapp.net",
    "body": "TAGIHAN",
    "fromMe": false
  }
}
```

Event yang ditangani secara eksplisit: `message`, `message.any`, `message.ack`, `session.status`.
Event lain diakui (`type: unhandled_event`) tapi tidak memicu aksi apa pun.

> ⚠️ **Catatan penting**: `docs/gowa/openapi.yaml` **tidak mendokumentasikan** bentuk body webhook
> GOWA yang sesungguhnya dikirim (hanya field *registrasi* webhook yang terdokumentasi). Kode ini
> mengasumsikan bentuknya identik dengan event WAHA di atas — karena nama event (`message`,
> `message.ack`) sama persis dan keduanya berbasis library `whatsmeow` yang sama. **Validasi ulang
> asumsi ini** begitu terhubung ke instance GOWA yang benar-benar berjalan (pakai
> `whatsapp:simulate-webhook` sebagai baseline, lalu bandingkan dengan payload asli dari `WebhookLog`).

`session` pada payload harus cocok dengan kolom `sysblas.session_name` (untuk GOWA, ini adalah
`device_id`; untuk WAHA, nama session) supaya event ter-relasi ke koneksi yang benar
(`handleWahaSessionStatus`, `verifyGowaSignature` mencari `Sysblas` lewat kolom ini).

### b. Format flat legacy `{message, phone|sender}` — pesan masuk

```json
{ "id": "inbound_001", "phone": "081299998888", "message": "info TAGIHAN saya", "pushName": "Budi" }
```

Dipakai oleh perintah `whatsapp:simulate-webhook` untuk simulasi lokal, dan tetap didukung sebagai
fallback jika suatu gateway mengirim bentuk sederhana ini.

### c. Format flat legacy `{status, phone|id}` — status pengiriman (DLR)

```json
{ "id": "gw_msg_124", "phone": "6281234567890", "status": "delivered", "note": "..." }
```

`status` yang dikenali: `sent|delivered|read|success` → `AntrianWaBlast` jadi **Terkirim**;
`failed|rejected|error` → jadi **Gagal** (dengan `pesan_error` dari `note`/`message`).

## 5. Verifikasi keamanan (signature HMAC) — khusus GOWA

- Endpoint webhook **tidak diverifikasi sama sekali** untuk WAHA maupun format flat legacy — siapa
  pun yang tahu URL-nya bisa kirim payload palsu. Ini keterbatasan yang **disengaja diterima** saat
  migrasi (lihat riwayat keputusan), bukan bug yang belum diketahui.
- Untuk **GOWA**, jika koneksi `Sysblas` terkait punya `api_secret` terisi (field ini dipakai ulang
  sebagai *webhook secret*, bukan API token — GOWA otentikasi API pakai Basic Auth, bukan token),
  request diverifikasi dengan HMAC-SHA256 atas *raw body*:

  ```php
  $expected = hash_hmac('sha256', $request->getContent(), $sysblas->api_secret);
  hash_equals($expected, $signature); // header X-Gowa-Signature, fallback X-Hub-Signature-256 (boleh prefix "sha256=")
  ```

  Signature tidak valid atau header tidak ada → **HTTP 401**, payload tidak diproses.
  Jika `api_secret` kosong, verifikasi **dilewati** (kompatibel mundur).

  > ⚠️ Nama header (`X-Gowa-Signature`) adalah **asumsi implementasi**, bukan dari dokumentasi resmi
  > GOWA (openapi.yaml tidak menyebut nama header). Konfirmasi ulang terhadap server GOWA nyata dan
  > sesuaikan `WhatsappWebhookService::verifyGowaSignature()` bila ternyata berbeda.

- Kode terkait: `WhatsappWebhookService::resolveGowaSysblas()` dan `::verifyGowaSignature()`,
  dipanggil dari `WhatsappWebhookController::handle()` sebelum `process()`.

## 6. Efek samping per event

| Event / format | Method | Efek |
|---|---|---|
| `message.ack` (GOWA/WAHA) | `handleWahaMessageAck()` | Update `AntrianWaBlast` (cari via nomor tujuan, jendela 3 hari terakhir) → `Terkirim`/`Gagal` |
| `session.status` = `WORKING` | `handleWahaSessionStatus()` | `Sysblas` (dicari via `session_name`) → `is_aktif = true` |
| `message` / `message.any` (bukan `fromMe`) | `handleIncomingMessage()` | Lihat §7 |
| flat `{message, phone}` | `handleIncomingMessage()` | Sama seperti di atas |
| flat `{status, phone}` | `handleTrackingStatus()` | Update `AntrianWaBlast` → `Terkirim`/`Gagal` |

Setiap payload **selalu** dicatat ke tabel `webhook_log` (`WhatsappWebhookService::logWebhook()`)
sebelum diproses, dengan `status_proses`: `diterima → diproses|diabaikan|gagal`. Gunakan ini sebagai
sumber kebenaran saat debugging — payload mentah tersimpan utuh di kolom `payload`.

## 7. Auto-reply pesan masuk (`generateInteractiveReply`)

Pesan masuk dicocokkan ke kata kunci (case-insensitive, `str_contains`), dan pelanggan dikenali dari
nomor HP (`Pelanggan::where('no_hp', ...)`):

| Kata kunci | Balasan |
|---|---|
| `TAGIHAN`, `BAYAR`, `INVOICE` | Daftar invoice belum lunas + link bayar (atau info "tidak ada tunggakan") |
| `TIKET`, `STATUS TIKET`, `GANGGUAN`, `RUSAK` | Status tiket aktif pelanggan |
| `MENU`, `BANTUAN`, `INFO`, `HELP` | Menu bantuan |
| lainnya | Tidak ada auto-reply, tapi jika pelanggan punya tiket aktif, teks tetap dicatat ke `TicketHistori` sebagai `[WhatsApp Pelanggan]: ...` |

Balasan otomatis dikirim lewat `WhatsappService::antrikanPesanKustom()` dengan `jenis: 'webhook_autoreply'`
(masuk ke outbox `AntrianWaBlast` seperti pesan lain, ikut rate-limit anti-ban per koneksi).

## 8. Cara menguji

### a. Simulasi lokal tanpa server WA sungguhan

```bash
vendor/bin/sail artisan whatsapp:simulate-webhook message --phone=081234567890 --message=TAGIHAN
vendor/bin/sail artisan whatsapp:simulate-webhook tracking --phone=081234567890 --status=delivered
```

Command ini memanggil `WhatsappWebhookService::process()` langsung (tanpa HTTP), berguna untuk cek
cepat logika auto-reply/tracking tanpa perlu request HTTP asli.

### b. Simulasi lewat HTTP (uji jalur controller + signature)

```bash
curl -X POST http://localhost/webhook/whatsapp \
  -H "Content-Type: application/json" \
  -d '{"event":"message","session":"org_2","payload":{"from":"6281234567890@s.whatsapp.net","body":"TAGIHAN","fromMe":false}}'
```

Untuk menguji signature GOWA, hitung HMAC dulu:

```bash
BODY='{"event":"session.status","session":"org_2","payload":{"status":"WORKING"}}'
SECRET='isi-api_secret-koneksi-sysblas'
SIG=$(php -r 'echo hash_hmac("sha256", $argv[1], $argv[2]);' "$BODY" "$SECRET")
curl -X POST http://localhost/webhook/whatsapp \
  -H "Content-Type: application/json" -H "X-Gowa-Signature: $SIG" -d "$BODY"
```

### c. Uji koneksi & kirim pesan uji coba (bukan webhook, tapi berguna untuk verifikasi end-to-end)

```bash
vendor/bin/sail artisan whatsapp:ping --sysblas=1 --send=081234567890
```

Atau lewat UI: **Pengaturan → Koneksi WhatsApp** → tombol "Ping" / "Tes Pesan" / "Tarik Device GOWA".

### d. Test otomatis (Pest)

- `tests/Feature/Webhook/WhatsappWebhookTest.php` — semua skenario di atas termasuk signature valid/invalid/kosong.
- `tests/Feature/Whatsapp/GowaDriverTest.php` — driver GOWA (kirim pesan, cek status, dsb).
- `tests/Feature/Sysblas/SysblasKoneksiTest.php` — CRUD koneksi, tarik device, ping.

```bash
vendor/bin/sail artisan test --compact --filter="Whatsapp|Gowa|Sysblas"
```

## 9. Troubleshooting

| Gejala | Kemungkinan penyebab | Cek |
|---|---|---|
| Webhook masuk tapi tidak ada efek | Bentuk payload tidak cocok 3 format yang dikenali → `type: unknown`/`unhandled_event` | Lihat `webhook_log.payload` mentah, bandingkan dengan §4 |
| `401 Unauthorized` terus-menerus dari GOWA | Nama header signature salah, atau `api_secret` di `Sysblas` tidak sama dengan `webhook_secret` yang didaftarkan ke GOWA | Cek §5, pastikan `PATCH /devices/{id}/webhook` di GOWA memakai secret yang sama |
| `AntrianWaBlast` tidak pernah jadi `Terkirim` walau pesan sukses di WA | `session` pada payload `message.ack` tidak cocok, atau matching by nomor+3-hari-terakhir meleset (ada retry rate-limit tertunda) | Cek `response_log` kolom `waha_ack`, cek `KirimWaBlastJob` (rate limit 1 pesan/5 menit — lihat §10 di bawah) |
| Auto-reply tidak terkirim balik | Nomor pelanggan tidak match format `WhatsappClient::normalizePhoneNumber()`, atau koneksi `Sysblas` default nonaktif | Cek `Sysblas::getDefault()`, cek `AntrianWaBlast` baris `jenis=webhook_autoreply` |
| `Sysblas` tidak auto-aktif walau `session.status: WORKING` | `session_name` di payload tidak persis sama dengan kolom `sysblas.session_name` (case-sensitive) | `handleWahaSessionStatus()` pakai exact match |

## 10. Terkait: rate-limit anti-ban pengiriman

Bukan bagian dari alur webhook (ini soal outbound), tapi sering muncul bersamaan saat debug: setiap
koneksi `Sysblas` punya jeda minimal **300 detik (5 menit) per pesan** secara default
(`delay_detik`, lihat `Sysblas/Koneksi` UI bagian "Proteksi Batas Laju & Anti-Ban WhatsApp") untuk
mencegah nomor WhatsApp diblokir — ini kenapa pesan di `AntrianWaBlast` bisa terlihat "Menunggu"
cukup lama sebelum benar-benar terkirim (lihat `KirimWaBlastJob`).

## 11. Referensi kode

| Komponen | File |
|---|---|
| Route | `routes/web.php` |
| Controller webhook | `app/Http/Controllers/Webhook/WhatsappWebhookController.php` |
| Service pemroses webhook | `app/Services/Whatsapp/WhatsappWebhookService.php` |
| Driver GOWA | `app/Services/Whatsapp/Drivers/GowaDriver.php` |
| Driver WAHA | `app/Services/Whatsapp/Drivers/WahaDriver.php` |
| Client multi-provider | `app/Services/Whatsapp/WhatsappClient.php` |
| Model koneksi gateway | `app/Models/Sysblas.php` |
| Model outbox pesan | `app/Models/AntrianWaBlast.php` |
| UI kelola koneksi | `app/Livewire/Sysblas/Koneksi/Index.php` |
| Command uji coba | `app/Console/Commands/WhatsappPingCommand.php`, `WhatsappSimulateWebhookCommand.php` |
| Spesifikasi REST API GOWA | `docs/gowa/openapi.yaml` |
