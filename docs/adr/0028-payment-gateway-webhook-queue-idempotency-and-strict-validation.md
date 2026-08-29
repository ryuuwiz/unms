# ADR 0028: Arsitektur Queue Webhook Asinkron, Penjaminan Idempotensi Tingkat Database, dan Validasi Ketat Nominal Payment Gateway

## Konteks
Setelah implementasi multi-gateway pada ADR 0026, pemrosesan webhook callback dari penyedia payment gateway (seperti Xendit dan iPaymu) masih dieksekusi secara sinkron di dalam siklus HTTP request controller (`PaymentWebhookController`). Selain itu, penjaminan idempotensi masih bertumpu pada validasi level aplikasi dan penguncian baris (`lockForUpdate()`), belum diperkuat dengan *Unique Constraints* di level basis data. Pada skenario lonjakan trafik atau pengiriman webhook berulang secara paralel dari gateway, pemrosesan sinkron berisiko mengalami timeout (> 2 detik SLA Xendit) atau race condition duplikasi pencatatan pembayaran dan perpanjangan masa aktif langganan internet.

## Keputusan yang Diambil

1. **Pemrosesan Webhook Asinkron Terpisah (Asynchronous Queue Worker)**:
   - Mengubah rute callback `POST /webhook/payment/{gateway}` untuk hanya melakukan verifikasi *signature/token* cepat, mencatat payload mentah ke tabel `webhook_log` dengan status awal `diterima`, dan seketika merespon `HTTP 200 OK` JSON (< 100ms).
   - Memancarkan job antrean `ProcessPaymentWebhookJob` dengan konfigurasi `afterCommit()` untuk memproses pelunasan invoice, pencatatan kas, dan perpanjangan layanan di latar belakang secara asinkron.
   - Mengonfigurasi mekanisme retry `$tries = 3` dan exponential backoff `$backoff = [10, 60, 300]` detik. Jika seluruh percobaan habis (*exhausted*), status log diubah menjadi `gagal`, exception disimpan di `catatan_error`, dan alert notifikasi internal dikirimkan ke administrator.

2. **Validasi Integritas Ketat Nominal & Mata Uang (Strict Verification)**:
   - Pelunasan otomatis hanya disetujui jika nominal pembayaran pada payload webhook berstatus `PAID` cocok secara eksak (`(int) $paidAmount === (int) $totalTagihan`) dalam integer Rupiah dan menggunakan mata uang resmi `IDR`.
   - Segala bentuk ketidakcocokan nominal (*underpayment* maupun *overpayment*) atau mata uang asing ditolak secara tegas (*Strict Rejection*), ditandai sebagai `anomali_nominal` pada log, dan tidak mengubah status invoice menjadi lunas tanpa intervensi manual.

3. **Penegakan Idempotensi Absolut di Level Database (Unique Constraints)**:
   - Menambahkan *Database Unique Index* pada tabel:
     - `webhook_log`: unique composite pada `['provider', 'provider_event_id']` untuk mencegah pencatatan event id duplikat dari gateway yang sama.
     - `transaksi_payment_gateway`: unique composite pada `['gateway', 'provider_reference_id']` (nullable).
     - `pembayaran`: unique composite pada `['metode', 'referensi_transaksi']` (nullable) guna menjamin tidak akan pernah ada dua baris pembayaran untuk satu transaksi gateway yang sama.
   - Menjalankan deduplikasi aman (*safe deduplication*) pada saat migrasi skema database.

4. **Standarisasi Kolom Multi-Gateway**:
   - Menstandarkan penamaan kolom menjadi generik (`provider_event_id` pada `webhook_log` dan `provider_reference_id` pada `transaksi_payment_gateway`) dengan tetap memelihara mutator/accessor kompatibilitas mundur (`xendit_event_id`, `xendit_reference_id`).

## Konsekuensi
- Webhook endpoint memiliki daya tahan tinggi terhadap lonjakan request dan memenuhi SLA respon cepat dari payment gateway.
- Seluruh mutasi finansial dan masa aktif layanan terlindungi dari bahaya *double fulfillment* saat terjadi pengiriman callback ganda atau retry paralel.
- Diperlukan *queue worker* (`php artisan queue:work`) yang aktif di server untuk memproses job antrean latar belakang.
