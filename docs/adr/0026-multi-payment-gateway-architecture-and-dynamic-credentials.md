# ADR 0026: Arsitektur Multi-Payment Gateway dan Pengelolaan Kredensial Terenkripsi di Database

## Konteks
Sebelumnya pada ADR 0007, sistem integrasi gateway pembayaran hanya mendukung satu penyedia (Xendit) dengan kredensial yang di-*hardcode* di file `.env` (`XENDIT_SECRET_KEY`, `XENDIT_CALLBACK_TOKEN`). Kebutuhan operasional ISP mengharuskan dukungan multi-provider payment gateway (seperti iPaymu, Xendit, Midtrans, dll) yang dapat dikonfigurasi langsung dari antarmuka web admin, menyimpan seluruh kredensial terenkripsi di database tanpa dependensi file `.env`, serta mengarahkan pelanggan langsung ke *Hosted Payment Link* resmi penyedia gateway tanpa membangun form checkout kustom internal.

## Keputusan yang Diambil

1. **Penyimpanan Kredensial Dinamis & Terenkripsi di Database**:
   - Model `PengaturanGateway` ditransformasikan menjadi entitas multi-row (1 baris per koneksi provider: `xendit`, `ipaymu`, dll) dengan cast `'credentials' => 'encrypted:array'`, sehingga seluruh kunci rahasia (API Key, Secret, VA Number, Callback Token) dienkripsi menggunakan `APP_KEY` Laravel.
   - Mengadopsi penanda `is_default` dan `is_aktif` untuk menentukan gateway utama yang digunakan oleh sistem, mirip pola yang diterapkan pada modul `Sysblas` (WhatsApp Gateway).

2. **Abstraksi Driver & Manager Pattern**:
   - Membangun `PaymentGatewayContract` dan `PaymentGatewayManager` sebagai jembatan tunggal untuk seluruh modul sistem (Invoice, Portal Pelanggan, Webhook, Rekonsiliasi).
   - Setiap provider diimplementasikan sebagai Driver terisolasi (`XenditDriver`, `IpaymuDriver`) yang menangani protokol komunikasi API spesifik, formatting request payload, dan verifikasi signature callback.

3. **Alur Pembayaran Langsung via Hosted Payment Link & Pembebanan Biaya Admin**:
   - Tidak menyediakan form atau halaman checkout kustom internal di portal pelanggan demi meminimalkan beban regulasi/PCI-DSS dan menyajikan metode pembayaran terlengkap yang disediakan resmi oleh gateway.
   - Sesi pembayaran dibuat secara *Hybrid Trigger*: Eager saat invoice terbit (disimpan ke `payment_gateway_url` dan `transaksi_payment_gateway`), dan otomatis di-regenerate secara on-demand jika sesi kedaluwarsa saat pelanggan menekan tombol *"Bayar Sekarang"* di Portal.
   - **Standar Biaya Admin Berdasarkan Riset Industri**: Secara default biaya transaksi dibebankan ke pelanggan (`bebankan_ke_pelanggan: true`) dengan standar pasar non-nol:
     - **Virtual Account (VA)**: Nominal **Rp 4.000,00** (ditambahkan via `InvoiceFee` pada Xendit / dibebankan via `feeDirection: 'BUYER'` pada iPaymu).
     - **QRIS**: Persentase **0,70%** (sesuai regulasi Merchant Discount Rate BI).
     - ISP dapat mengubah nominal/persentase ini atau memilih mensubsidi biaya admin melalui menu pengaturan.

4. **Webhook Callback Terpadu & Delegasi Verifikasi Signature**:
   - Menggunakan rute webhook generic `POST /webhook/payment/{gateway}` yang ditangani oleh `PaymentWebhookController`.
   - Controller memuat record gateway dari database dan mendelegasikan verifikasi signature (misal HMAC-SHA256 iPaymu atau Token Xendit) langsung ke driver terkait.
   - Mutasi status invoice tetap dilindungi dengan `DB::transaction()` dan `lockForUpdate()` untuk menjamin idempotensi absolut.

5. **Generalisasi Skema Database & Kompatibilitas Mundur**:
   - Kolom tabel `invoice` digeneralisasi menjadi `payment_gateway_id`, `payment_gateway_url`, `payment_gateway_provider`, dan `payment_gateway_status` dengan accessor kompatibilitas mundur untuk atribut lama (`xendit_invoice_url`).
   - Audit trail `transaksi_payment_gateway` dan `webhook_log` menampung identifier generik lintas penyedia.

## Konsekuensi
- Menghilangkan sepenuhnya ketergantungan konfigurasi payment gateway dari file `.env`, memudahkan onboarding dan pergantian vendor gateway kapan saja melalui antarmuka admin.
- Penambahan gateway baru di masa depan cukup dengan menambahkan satu kelas Driver baru tanpa perlu memodifikasi controller webhook, view invoice, atau skema tabel database utama.
