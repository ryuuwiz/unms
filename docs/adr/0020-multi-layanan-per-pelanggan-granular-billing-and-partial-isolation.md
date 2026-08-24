# ADR 0020: Multi-Layanan Per Pelanggan, Granular Billing, dan Partial Isolation

## Status
Accepted

## Konteks
Pada operasional ISP, satu pelanggan (entitas `Pelanggan`) seringkali memiliki lebih dari satu kebutuhan koneksi internet, seperti:
1. Memasang koneksi di rumah utama dan di toko/kantor cabang.
2. Memasang koneksi primer dan koneksi cadangan/backup dengan paket atau router gateway yang berbeda.
3. Membutuhkan tagihan terpisah per lokasi/site dengan siklus tanggal expired independen.

Diperlukan penegasan arsitektural mengenai tata kelola identitas akun PPP, penomoran invoice, isolir gangguan, dan pembayaran agar tidak terjadi benturan data atau pemutusan layanan yang tidak adil.

## Keputusan Arsitektur

1. **Relasi 1:N Pelanggan dan Layanan (`hasMany`)**:
   - Model `Pelanggan` berelasi `hasMany` ke `LayananPelanggan`.
   - Tidak ada batasan jumlah layanan yang dapat didaftarkan untuk satu pelanggan aktif.

2. **Identitas & Kredensial Unik Bertingkat**:
   - `site_id`: Dibuat acak-unik (`SITE-XXXXX`) untuk setiap instance layanan guna mengidentifikasi titik pemasangan fisik.
   - `ppp_username`: Dibuat secara berurutan berbasis nomor registrasi pelanggan (`{no_reg}_00001`, `{no_reg}_00002`, `{no_reg}_00003`, dst.) melalui `LayananPelanggan::generatePppUsername()`.

3. **Granular Billing (1 Invoice per Periode per Layanan)**:
   - Model `Invoice` memiliki relasi wajib ke `layanan_pelanggan_id` dan `pelanggan_id`.
   - Tagihan dihitung dan diterbitkan secara granular per layanan, memungkinkan paket harga, promo, dan periode jatuh tempo yang berbeda antar layanan milik pelanggan yang sama.

4. **Partial Isolation (Isolir Parsial Berbasis Layanan)**:
   - Jika Layanan A menunggak tagihan (Invoice Overdue / status `Suspend`), hanya PPP secret Layanan A yang dinonaktifkan (`disablePppoeSecret`) di MikroTik RouterOS.
   - Layanan B yang lunas tetap aktif dan beroperasi normal tanpa terdampak status Layanan A.

5. **Idempotensi & Perpanjangan Masa Aktif**:
   - Pembayaran invoice suatu layanan (`prosesPembayaranManual`) hanya memperpanjang masa aktif (`tanggal_expired`) layanan yang bersangkutan.

## Konsekuensi
- **Positif**:
  - Fleksibilitas tinggi bagi pelanggan bisnis dan residensial multi-lokasi.
  - Akurasi pelaporan finansial per titik layanan (site).
  - Pengalaman pelanggan yang adil (tidak terjadi pemutusan massal untuk seluruh layanan saat salah satu site menunggak).
- **Pertimbangan**:
  - Antarmuka penerbitan invoice dan pencatatan kasir harus menyajikan filter site/layanan yang jelas agar operator tidak salah memilih layanan.
