# ADR 0007: Integrasi Payment Gateway Xendit dan Arsitektur Multi-Auth Portal Pelanggan

## Konteks
Pada implementasi Fase 3 sistem UNMS, dibutuhkan modul pembayaran otomatis terintegrasi menggunakan payment gateway Xendit (Virtual Account 5 bank utama dan QRIS Dinamis), webhook handler dengan proteksi idempotensi tinggi, pengelolaan biaya transaksi (admin fee) oleh Superadmin, serta Portal Pelanggan mandiri (Customer Portal) untuk melihat tagihan dan melakukan pembayaran langsung.

## Keputusan yang Diambil

1. **Arsitektur Portal Pelanggan (Multi-Guard Monolit)**:
   - Portal Pelanggan berjalan di dalam monolit yang sama pada routing `/portal/*` dengan guard autentikasi terpisah (`guard: pelanggan`, provider `akun_pelanggan` pada model `AkunPelanggan`).
   - Menggunakan Livewire 4 dan Flux UI dengan layout khusus pelanggan (`layouts.portal`) yang ramah perangkat seluler (mobile-first).
   - Akun portal otomatis dibuat saat data master Pelanggan disimpan oleh staf (password default `12345678`), dengan kewajiban ubah password saat login pertama atau melalui fitur reset mandiri.

2. **Integrasi Xendit White-Label Native (SDK v7)**:
   - Menggunakan SDK resmi `xendit/xendit-php` v7 via `PaymentRequestApi`.
   - Sistem menampilkan kode Virtual Account (BCA, BNI, BRI, Mandiri, Permata) dan string QRIS dinamis secara native di dalam portal UNMS tanpa mengalihkan (redirect) pelanggan ke halaman eksternal Xendit.
   - Masa kedaluwarsa tagihan gateway (`expired_at`) ditetapkan 3 hari (72 jam) atau menyesuaikan tanggal jatuh tempo invoice.

3. **Manajemen Biaya Transaksi (Admin Fee)**:
   - Disediakan modul konfigurasi biaya gateway yang dapat diatur oleh `super_admin` (nominal flat VA, persentase/flat QRIS, dan opsi apakah biaya disubsidi ISP atau dibebankan ke pelanggan).

4. **Arsitektur Webhook & Idempotensi Ganda**:
   - Endpoint terpusat `POST /webhook/xendit` (dikecualikan dari CSRF, tanpa auth session).
   - Verifikasi header `x-callback-token` menggunakan perbandingan konstan `hash_equals()`.
   - Pencatatan ke tabel audit `webhook_log`.
   - Eksekusi database di dalam `DB::transaction()` dengan `lockForUpdate()` pada baris `invoice` dan guard clause jika invoice sudah berstatus `lunas`.
   - Pemisahan tegas antara database commit dan external I/O melalui event `InvoicePaidEvent` (`DB::afterCommit()`).

5. **Monitoring & Rekonsiliasi Admin**:
   - Menu `Transaksi Gateway` ditempatkan di bawah navigasi **Keuangan & Billing** (`/pembayaran/transaksi-gateway`).
   - Menyediakan fitur rekonsiliasi status manual ke API Xendit per transaksi.

## Konsekuensi
- Portal pelanggan terintegrasi penuh dengan sistem billing tanpa latensi API eksternal atau sinkronisasi antar-database terpisah.
- Risiko double-crediting atau double-extension layanan pelanggan dicegah secara matematis melalui idempotency check berlapis.
- Penambahan integrasi teknis jaringan (MikroTik di Fase 4) dan notifikasi (WA Blast di Fase 5) dapat dihubungkan langsung ke listener `InvoicePaidEvent` tanpa mengubah kode inti payment gateway.
