# ADR 0021: Standardisasi Penamaan Data Registrasi Billing, Validasi Anti-Duplikasi Layanan, dan Penjadwalan MikroTik Terpadu

## Status
Accepted

## Konteks
1. **Penyelarasan Nomenklatur UI & Domain**:
   Berdasarkan spesifikasi antarmuka dan modul di `docs/data_unms.md` (bagian `# Layanan & Network -> ## Billing`), entitas langganan internet aktif yang menghubungkan pelanggan dengan paket layanan, router gateway, kredensial PPP, dan masa aktif distandardisasi dengan nama resmi **"Data Registrasi Billing"** (menggantikan istilah antarmuka sebelumnya "Layanan Pelanggan").
2. **Pencegahan Duplikasi Layanan (Anti-Duplicate Guard)**:
   Meskipun ADR 0020 mendukung multi-layanan/multi-site (1:N) untuk satu pelanggan, terdapat risiko kesalahan input operator/admin yang secara tidak sengaja mendaftarkan kembali layanan dengan paket dan router yang persis sama untuk pelanggan yang sudah memiliki layanan aktif (`Aktif`, `Proses`, atau `Suspend`).
3. **Pemisahan Ritme Pemulihan (*Auto-Recovery*) vs Pembersihan (*Clean Orphans*)**:
   Insiden putus kabel/modem mati (LOS) menuntut sistem memiliki pemulihan instan saat router restart atau secret hilang. Namun, menjalankan pembersihan akun (*clean orphans*) pada frekuensi tinggi (sub-minute) memicu risiko *race condition* menghapus akun yang sedang didaftarkan serta lonjakan CPU load RouterOS MikroTik hingga 100%.

## Keputusan Arsitektur

1. **Standardisasi Nomenklatur "Data Registrasi Billing"**:
   - Tampilan Menu Navigasi, Judul Halaman (Livewire Page Title), Breadcrumb, Tombol Aksi, dan Notifikasi Toast menggunakan istilah resmi **"Data Registrasi Billing"** merujuk pada `docs/data_unms.md`.
   - Nama class PHP model (`LayananPelanggan`), tabel database (`layanan_pelanggan`), serta foreign keys dipertahankan demi stabilitas basis data dan *backward compatibility*.

2. **Validasi Anti-Duplikasi Bertingkat**:
   - Sistem menolak pendaftaran Data Registrasi Billing baru jika ditemukan record dengan kombinasi `pelanggan_id`, `router_id`, dan `paket_layanan_id` yang sama yang berstatus `Aktif`, `Proses`, atau `Suspend`.
   - Pelanggan tetap dapat memiliki banyak Data Registrasi Billing untuk instalasi site berbeda dengan paket yang berbeda, router yang berbeda, atau setelah layanan lama berstatus non-aktif (`Berhenti` / `Dibatalkan`).

3. **Dual-Tier Resilient Scheduler di `routes/console.php`**:
   - **Fast Sub-Minute Auto-Recovery**:
     ```php
     Schedule::command('mikrotik:recover-ppp')
         ->everyFiveSeconds()
         ->withoutOverlapping(10)
         ->runInBackground();
     ```
     Menjalankan sinkronisasi cepat untuk memulihkan PPP profile & secret yang hilang di RouterOS tanpa menjalankan `--clean-orphans` agar bebas dari *race condition* dan menjaga CPU RouterOS tetap stabil.
   - **Master Nightly Reconciliation & Orphan Cleanup**:
     ```php
     Schedule::command('mikrotik:provisi-router --clean-orphans')
         ->dailyAt('03:00')
         ->withoutOverlapping(60)
         ->runInBackground();
     ```
     Menjalankan orkestrasi lengkap (pemeriksaan resource, IP Pool, Binary Bps Bandwidth Profile, PPP Secret, dan audit/penghapusan orphaned secret) pada jam sepi trafik (03:00 subuh) dengan proteksi `withoutOverlapping(60)` dan `runInBackground()`.

## Konsekuensi
- **Positif**:
  - Konsistensi istilah antara UI, dokumen spesifikasi `docs/data_unms.md`, dan kamus domain `CONTEXT.md`.
  - Mencegah *human error* input ganda data registrasi billing tanpa membatasi kebutuhan multi-lokasi pelanggan.
  - Pemulihan router super cepat (5 detik) pasca-restart/down tanpa risiko menghapus akun valid dan tanpa membuat CPU MikroTik overload.
- **Pertimbangan**:
  - Pesan validasi error harus informatif agar staf admin memahami opsi jika ingin mendaftarkan site baru.
