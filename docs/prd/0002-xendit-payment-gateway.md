# PRD Modul: Integrasi Payment Gateway Xendit

**Terkait**: PRD-Arsitektur-UNMS-Laravel.md (Bagian 3.5 & 4)
**Stack**: Laravel 13, PHP 8.4, `xendit/xendit-php` (SDK resmi)
**Produk Xendit yang dipakai**: Virtual Account API, QR Code (QRIS) API. eWallet API opsional di fase lanjutan.

---

## 1. Tujuan Modul

Menyediakan mekanisme pembayaran invoice pelanggan ISP via Virtual Account (BCA, BNI, Mandiri, Permata, BRI) dan QRIS, dengan:
- Kontrol penuh atas tampilan nomor VA/QR di portal pelanggan (tidak redirect ke halaman Xendit)
- Idempotency ketat di webhook agar tidak ada invoice diproses dua kali atau layanan diperpanjang dobel
- Pemisahan tegas antara operasi database dan panggilan API eksternal

---

## 2. Konfigurasi & Environment

**File `config/services.php`** — tambahkan blok:
```
'xendit' => [
    'secret_key' => env('XENDIT_SECRET_KEY'),
    'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
    'env' => env('XENDIT_ENV', 'development'),
],
```

**`.env`**:
```
XENDIT_SECRET_KEY=xnd_development_xxxxxxxxxxxxx
XENDIT_CALLBACK_TOKEN=xxxxxxxxxxxxxxxxxxxx
XENDIT_ENV=development
```

**Instruksi**:
- Jangan pernah commit `XENDIT_SECRET_KEY` atau `XENDIT_CALLBACK_TOKEN` ke repository, termasuk di file `.env.example` (isi placeholder kosong saja).
- `XENDIT_ENV=development` memakai prefix key `xnd_development_*` — semua transaksi bersifat simulasi, tidak ada uang riil berpindah. Ganti ke `production` + key `xnd_production_*` hanya di server live.
- Simpan `callback_token` juga sebagai referensi terpisah dari secret key — dua kredensial ini punya fungsi berbeda (secret key untuk memanggil API, callback token untuk memverifikasi webhook masuk).

---

## 3. Struktur Kode

```
app/
  Services/
    Xendit/
      XenditPaymentService.php      # buat VA, buat QRIS, cek status
      XenditWebhookVerifier.php     # verifikasi x-callback-token
  DTO/
    Xendit/
      XenditVaCallbackData.php
      XenditQrisCallbackData.php
  Http/
    Controllers/
      Webhook/
        XenditVirtualAccountWebhookController.php
        XenditQrisWebhookController.php
  Jobs/
    Payment/
      ProsesPembayaranLunasJob.php   # dipanggil setelah commit — orkestrasi job lanjutan
    Mikrotik/
      AktifkanLayananJob.php
    Notifikasi/
      KirimWaKonfirmasiPembayaranJob.php
  Console/
    Commands/
      CekVirtualAccountExpiredCommand.php
```

---

## 4. Instruksi Implementasi (imperatif, untuk AI coding agent)

### 4.1 `XenditPaymentService`

- Buat method `buatVirtualAccount(Invoice $invoice, string $kodeBank): TransaksiPaymentGateway`.
  - Bangun `external_id` dengan format `INV-{$invoice->no_invoice}-{now()->timestamp}`.
  - Panggil `VirtualAccountApi` dari SDK dengan parameter: `external_id`, `bank_code` = `$kodeBank`, `name` = nama lengkap pelanggan, `expected_amount` = `$invoice->jumlah_setelah_promo`, `is_closed` = `true`, `expiration_date` = `now()->addDays(3)->toIso8601String()`.
  - Kirim header idempotency key = `external_id` yang sama (SDK mendukung parameter `x_idempotency_key`).
  - Simpan hasil ke tabel `transaksi_payment_gateway`: `gateway = 'xendit'`, `channel = 'virtual_account'`, `channel_detail = $kodeBank`, `nomor_pembayaran` dari `account_number` response, `expired_at`, `payload_request`, `payload_response` (json_encode seluruh response untuk audit).
  - Method ini **insert biasa** (bukan dalam `DB::transaction()` dengan operasi lain) — kegagalan panggil Xendit cukup di-throw sebagai exception dan ditangani di controller/action pemanggil, tidak perlu rollback data lain.

- Buat method `buatQris(Invoice $invoice): TransaksiPaymentGateway` — pola sama, panggil `QrCodeApi`, `type = 'DYNAMIC'`, `amount` dari invoice, simpan `nomor_pembayaran` = QR string dari response.

- Buat method `cekStatusTransaksi(string $externalId): array` — untuk keperluan reconciliation manual dari dashboard admin (tombol "Cek status pembayaran" di detail invoice), memanggil endpoint get-by-external-id Xendit.

### 4.2 `XenditWebhookVerifier`

- Method `verifikasi(Request $request): bool` — bandingkan header `x-callback-token` dari request dengan `config('services.xendit.callback_token')` memakai `hash_equals()` (bukan `===`, untuk mencegah timing attack).
- Jika tidak cocok, controller harus mengembalikan HTTP 401 dan **tidak** memproses payload sama sekali.

### 4.3 Route & middleware

Di `routes/web.php` atau file route terpisah `routes/webhook.php`:
```
Route::post('/webhook/xendit/virtual-account', [XenditVirtualAccountWebhookController::class, 'handle']);
Route::post('/webhook/xendit/qris', [XenditQrisWebhookController::class, 'handle']);
```
- Kecualikan kedua route ini dari `VerifyCsrfToken` (tambahkan ke `$except` di middleware atau grup route tanpa `web` middleware group, cukup pakai grup minimal).
- Jangan taruh di belakang middleware `auth` — Xendit memanggil sebagai server-to-server, otentikasi dilakukan lewat `x-callback-token`, bukan sesi/login.

### 4.4 `XenditVirtualAccountWebhookController::handle()`

Ikuti urutan berikut **persis**, jangan diringkas:

1. Panggil `XenditWebhookVerifier::verifikasi($request)`. Jika gagal → `return response()->json(['message' => 'unauthorized'], 401);` dan hentikan.
2. Ambil `xendit_event_id` dari payload (field `id` di body callback VA Xendit). Query `webhook_log` — jika sudah ada baris dengan `xendit_event_id` ini dan `status_proses = 'diproses'` → `return response()->json(['message' => 'already processed'], 200);` (bukan error, karena ini kondisi normal dari retry Xendit).
3. Insert baris baru ke `webhook_log`: `event_type = 'virtual_account.paid'`, `xendit_event_id`, `payload` (json seluruh body), `status_proses = 'diterima'`, `diterima_pada = now()`.
4. Parse payload jadi `XenditVaCallbackData` (DTO). Cari `TransaksiPaymentGateway::where('external_id', $data->externalId)->first()`. Jika tidak ditemukan → update `webhook_log.status_proses = 'diabaikan'`, log warning, `return response()->json(['message' => 'transaction not found'], 200)`.
5. Mulai `DB::transaction(function () use (...) { ... })`:
   - `$invoice = Invoice::where('id', $transaksi->invoice_id)->lockForUpdate()->first();`
   - **Guard clause**: `if ($invoice->status === 'lunas') { return; }` — keluar dari closure transaksi tanpa melakukan apa pun lagi (idempotent).
   - Update `$transaksi->update(['status' => 'paid', 'payload_response' => ..., 'updated_at' => now()])`.
   - Update `$invoice->update(['status' => 'lunas', 'tanggal_lunas' => now(), 'metode_pembayaran' => 'virtual_account - ' . $transaksi->channel_detail])`.
   - `Pembayaran::create([...])` dengan `metode = 'payment_gateway'`, `referensi_transaksi = $data->accountNumber ?? $data->paymentId`, `jumlah_dibayar = $data->amount`.
   - Hitung `tanggal_expired` baru untuk `layanan_pelanggan`: jika `tanggal_expired` lama masih di masa depan (`>= now()`), tambahkan durasi paket dari tanggal tersebut; jika sudah lewat, tambahkan dari `now()`. Update kolom.
   - Update `webhook_log` yang di-insert di langkah 3 → `status_proses = 'diproses'`.
   - Catat `log_aktivitas` (aktivitas: `pembayaran_xendit_diterima`, model: `Invoice`, model_id: `$invoice->id`).
6. Setelah closure transaksi selesai (di luar `DB::transaction()`), **jika invoice baru saja berubah jadi lunas** (bukan hasil guard clause skip):
   - `AktifkanLayananJob::dispatch($layananPelanggan->id)->onQueue('mikrotik');`
   - `KirimWaKonfirmasiPembayaranJob::dispatch($invoice->id)->onQueue('wa-blast');`
7. `return response()->json(['message' => 'ok'], 200);` — pastikan seluruh proses di atas selesai di bawah 5 detik; jangan tunggu job selesai (job hanya di-dispatch, dieksekusi async oleh queue worker).

### 4.5 `XenditQrisWebhookController::handle()`

Pola identik dengan 4.4, perbedaan hanya di parsing DTO (`XenditQrisCallbackData`) dan `event_type = 'qr.payment'` — field-field payload QRIS Xendit berbeda struktur dari VA (ada `qr_id`, `payment_detail` berisi `receipt_id`, `source`), jadi DTO harus dibuat terpisah, jangan dipaksa satu DTO untuk dua jenis callback.

### 4.6 `CekVirtualAccountExpiredCommand`

- Jadwalkan di `routes/console.php` (Laravel 13 memakai file ini, bukan `Kernel.php`) via `Schedule::command('xendit:cek-va-expired')->dailyAt('01:00')`.
- Query `TransaksiPaymentGateway::where('status', 'pending')->where('expired_at', '<', now())->get()`, update masing-masing jadi `status = 'expired'`. Jangan ubah status invoice — invoice tetap `menunggu_pembayaran`, pelanggan bisa memicu pembuatan VA baru dari portal.

---

## 5. Testing

- Xendit mode development menyediakan endpoint simulasi pembayaran untuk VA (`POST https://api.xendit.co/callback_virtual_accounts/external_id={external_id}/simulate_payment`) dan mekanisme serupa untuk QRIS — pakai ini di test otomatis maupun manual QA, jangan simulasikan payload webhook secara manual dari tangan kecuali untuk unit test parsing DTO.
- Buat feature test `XenditVirtualAccountWebhookTest`:
  - Test: payload valid + token benar → invoice jadi lunas, job `AktifkanLayananJob` dan `KirimWaKonfirmasiPembayaranJob` di-dispatch (`Bus::fake()` assert).
  - Test: token salah → 401, invoice tidak berubah.
  - Test: payload dikirim dua kali (event ID sama) → hanya diproses sekali, `Pembayaran` hanya ada 1 baris.
  - Test: invoice sudah lunas sebelumnya, webhook masuk lagi (mis. Xendit retry sebelum idempotency check pertama tercatat) → guard clause di dalam transaksi mencegah dobel proses.
- Buat unit test untuk `XenditVaCallbackData::fromArray()` dan `XenditQrisCallbackData::fromArray()` — pastikan field yang mungkin `null` dari Xendit (mis. `payment_id` di beberapa event) ditangani tanpa exception.

---

## 6. Keamanan

- Verifikasi `x-callback-token` di **setiap** request webhook tanpa kecuali — tidak ada mode "skip verifikasi saat development".
- Pertimbangkan whitelist IP Xendit di level Nginx untuk endpoint `/webhook/xendit/*` sebagai lapisan tambahan (Xendit mempublikasikan daftar IP webhook mereka di dokumentasi resmi — cek dokumentasi terbaru karena IP bisa berubah).
- Jangan pernah log `XENDIT_SECRET_KEY` di `payload_request`/`payload_response` — pastikan hanya body request/response API yang disimpan, bukan header Authorization.
- Rate limit endpoint pembuatan VA/QRIS dari sisi portal pelanggan (mis. `throttle:5,1` per pelanggan) untuk mencegah spam pembuatan VA yang membebani kuota API Xendit.

---

## 7. Checklist sebelum go-live

- [ ] Ganti `XENDIT_SECRET_KEY` dan `XENDIT_CALLBACK_TOKEN` ke versi production di server
- [ ] Daftarkan URL webhook production di Dashboard Xendit (Settings → Webhooks) untuk event `virtual_account.paid` dan `qr.payment`
- [ ] Uji end-to-end dengan nominal kecil di mode production sebelum dibuka ke seluruh pelanggan
- [ ] Pastikan queue worker untuk `mikrotik` dan `wa-blast` berjalan (Horizon atau supervisor) — webhook yang sukses tapi job tidak jalan berarti layanan tidak aktif meski pelanggan sudah bayar
- [ ] Pastikan `CekVirtualAccountExpiredCommand` terjadwal dan scheduler Laravel berjalan (`* * * * * php artisan schedule:run` di crontab)