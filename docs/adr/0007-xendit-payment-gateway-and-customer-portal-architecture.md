# ADR 0007: Integrasi Payment Gateway Xendit (Hosted Invoice) dan Arsitektur Multi-Auth Portal Pelanggan

## Konteks
Pada sistem UNMS, dibutuhkan modul pembayaran online terintegrasi menggunakan payment gateway Xendit dengan hosted payment page (Xendit Invoice API), webhook handler dengan proteksi idempotensi tinggi berbasis transaksi database ber-kunci baris (`lockForUpdate`), verifikasi signature via middleware, serta Portal Pelanggan mandiri (Customer Portal) yang mengalihkan pelanggan ke link pembayaran resmi Xendit tanpa membangun UI transaksi mandiri.

## Keputusan yang Diambil

1. **Arsitektur Portal Pelanggan (Multi-Guard Monolit)**:
   - Portal Pelanggan berjalan di dalam monolit yang sama pada routing `/portal/*` dengan guard autentikasi terpisah (`guard: pelanggan`, provider `akun_pelanggan` pada model `AkunPelanggan`).
   - Menggunakan Livewire 4 dan Flux UI dengan layout khusus pelanggan (`layouts.portal`) yang ramah perangkat seluler (mobile-first).
   - Customer Portal cukup menampilkan tombol *"Bayar Sekarang"* yang mengarahkan (redirect) pelanggan ke tautan hosted `xendit_invoice_url`, tanpa memerlukan form kartu/VA/QRIS custom di aplikasi internal.

2. **Integrasi Xendit Hosted Invoice (SDK v7 `InvoiceApi`)**:
   - Menggunakan SDK resmi `xendit/xendit-php` v7 via `InvoiceApi::createInvoice()`.
   - Menggunakan model *Hybrid Trigger dengan Auto-Regenerate*: Link `xendit_invoice_url` dibuat otomatis saat invoice lokal terbit (Eager), dan jika masa berlaku link Xendit habis (`EXPIRED`) saat invoice lokal masih berstatus `menunggu_pembayaran`, sistem membuat link baru on-demand saat pelanggan mengklik tombol bayar.
   - Breakdown tagihan dikirim terstruktur via parameter `items` (rincian paket internet & potongan promo) dan `fees` (biaya admin gateway jika berlaku).
   - Masa kedaluwarsa link Xendit diset dinamis mengikuti tanggal jatuh tempo invoice lokal (dengan batas toleransi minimal 24 jam).
   - Parameter `success_redirect_url` dan `failure_redirect_url` diarahkan kembali ke `route('portal.invoice.show', $invoice->id)`.

3. **Skema Database & Denormalisasi Terkontrol**:
   - Kolom `xendit_invoice_id` dan `xendit_invoice_url` disimpan langsung pada tabel `invoice` untuk mempermudah akses langsung di Blade/Portal dan pesan WhatsApp.
   - Riwayat sesi penerbitan tetap dicatat ke tabel `transaksi_payment_gateway` (`channel = 'invoice'`) sebagai audit trail lengkap (termasuk `payload_request` dan `payload_response`).

4. **Arsitektur Webhook, Middleware, & Idempotensi Transaksional**:
   - Endpoint webhook `POST /webhook/xendit` dilindungi oleh dedicated middleware `ValidateXenditCallbackToken` yang memverifikasi header `x-callback-token` menggunakan `hash_equals()`. Request tidak valid langsung ditolak dengan HTTP 401 sebelum masuk controller.
   - Endpoint webhook wajib mengembalikan HTTP 200 dengan cepat (< 5 detik).
   - Seluruh mutasi database dibungkus dalam `DB::transaction()` dengan `lockForUpdate()` pada baris `invoice`:
     - **Idempotency Guard**: Jika invoice sudah berstatus `lunas`, transaksi langsung selesai tanpa double-credit atau perpanjangan dobel.
     - **Event `PAID`**: Mencatat `pembayaran`, memperbarui `invoice.status = lunas`, memperpanjang `tanggal_expired` pada `layanan_pelanggan`, dan memperbarui log.
     - **Event `EXPIRED`**: Mengubah status `transaksi_payment_gateway` menjadi `expired` dan mengosongkan `xendit_invoice_url` pada invoice lokal agar siap di-regenerate saat dibutuhkan.

5. **Pemisahan Database Commit & External I/O (Event-Driven)**:
   - Pemrosesan eksternal yang lambat (koneksi API MikroTik RouterOS dan pengiriman WhatsApp Blast) dipisahkan dari request webhook HTTP.
   - Event `InvoicePaidEvent` dipancarkan post-commit (`DB::afterCommit()`), dengan listener asinkron:
     - `AktifkanLayananMikrotikListener` (`queue: mikrotik`)
     - `KirimWaKonfirmasiPembayaranListener` (`queue: wa-blast`)

6. **Monitoring & Rekonsiliasi Admin**:
   - Menu `Transaksi Gateway` ditempatkan di bawah navigasi **Keuangan & Billing** (`/pembayaran/transaksi-gateway`).
   - Menyediakan fitur sinkronisasi dan rekonsiliasi status manual ke API Xendit per invoice/transaksi.

## Konsekuensi
- Kompleksitas frontend Customer Portal berkurang drastis karena metode pembayaran dikelola sepenuhnya oleh UI hosted Xendit yang selalu terbarui dengan channel pembayaran terbaru.
- Integritas data keuangan dan masa aktif layanan terjamin secara absolut terhadap race-condition webhook retry berkat kombinasi `lockForUpdate()`, idempotency check, dan isolasi `DB::afterCommit()`.
- Beban request webhook sangat ringan dan toleran terhadap kegagalan jaringan eksternal karena orkestrasi MikroTik & WhatsApp berjalan di antrean background worker terpisah.
