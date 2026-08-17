# ADR 0004: Siklus Hidup Billing, Invoice, dan Pembayaran Manual

## Konteks
Pada Fase 2 arsitektur UNMS, sistem membutuhkan modul penagihan (billing) manual yang mencakup penerbitan invoice, auto-generation tagihan berkala sebelum jatuh tempo, pencatatan pembayaran manual oleh admin/kasir, dan penyesuaian masa aktif layanan pelanggan (`layanan_pelanggan.tanggal_expired`).

## Keputusan yang Diambil

1. **Format Penomoran Invoice**:
   - Format standar adalah `INV-YYYYMM-NNNNNN` (contoh: `INV-202608-000001`), dengan nomor urut 6-digit yang di-reset setiap pergantian bulan kalender.
   - Penomoran ditangani secara transaksional dengan penguncian baris (`lockForUpdate`) untuk mencegah duplikasi pada concurrency tinggi.

2. **Aturan Perpanjangan Masa Aktif Layanan (PRD 4.2)**:
   - **Kondisi 1 (Pembayaran Tepat Waktu / Sebelum Expired)**: Jika `tanggal_expired` lama masih di masa depan, masa aktif baru ditambahkan secara akumulatif (`tanggal_expired_baru = tanggal_expired_lama + masa_aktif_paket`).
   - **Kondisi 2 (Pembayaran Setelah Expired / Suspend)**: Jika `tanggal_expired` sudah terlewati, masa aktif baru dihitung dari tanggal bayar (`tanggal_expired_baru = now() + masa_aktif_paket`).
   - Status layanan yang sebelumnya `suspend` atau `proses` otomatis diaktifkan menjadi `aktif`.

3. **Pencatatan Transaksi Pembayaran**:
   - Pembayaran dilakukan di dalam `DB::transaction()` dengan `lockForUpdate()` pada baris `invoice`.
   - Menggunakan guard clause: jika invoice sudah berstatus `lunas`, transaksi dibatalkan (idempotency protection).
   - Pembayaran di Fase 2 bersifat pembayaran penuh per invoice (`jumlah_dibayar = jumlah_setelah_promo`).

4. **Auto-Generate Invoice (Scheduler)**:
   - Command terjadwal harian `invoice:generate` berjalan untuk menerbitkan invoice pada H-7 sebelum `tanggal_expired` layanan pelanggan aktif yang belum memiliki tagihan berjalan.

## Konsekuensi
- Mencegah kehilangan sisa hari bagi pelanggan yang membayar sebelum jatuh tempo, sekaligus adil bagi operasional ISP untuk pelanggan yang menunggak.
- Menjamin integritas data keuangan tanpa risiko double payment saat diproses admin atau diintegrasikan dengan payment gateway pada Fase 3.
