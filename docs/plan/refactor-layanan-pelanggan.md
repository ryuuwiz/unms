# Refactor Plan: Layanan Pelanggan
Tab "Kontak Member" → "List Kontak" → Klik Detail Pelanggan yang dipilih → Muncul halaman data pelanggan.
Terdapat section Layanan/Pemasangan berisi tabel dengan header:
Label Layanan
PPP USERNAME
Paket / Router
Status
Aksi
Button Tambah Layanan akan ke halaman dengan contoh berikut:
Form Tambah Layanan untuk Pelanggan:
RYU KURNIANTO · Nomor Reg: BF26090331
Nama Pelanggan: RYU KURNIANTO
No. Reg: BF26090331
Data Layanan
Atur jenis layanan dan paket yang akan digunakan untuk pemasangan ini. Username PPP / IP Statis akan diatur nanti melalui ticket pemasangan.
—
Jenis Layanan: PPPOE
—
Paket Layanan: Select Paket Layanan
Harga default mengikuti paket, bisa diubah di bagian Pengaturan Harga Layanan.
—
Harga Paket (Default)
Rp 0
Auto terisi saat pilih paket.
Alamat Pemasangan
Alamat pemasangan bisa sama dengan alamat utama pelanggan atau berbeda (misal cabang, lantai lain, rumah orang tua, dll).
—
Pilih Sumber Alamat:
Gunakan alamat utama pelanggan
Alamat pemasangan berbeda
Alamat Utama Pelanggan
No. 5, Curug Asri No.15B , JL.RAYA CURUG , Kel. CURUG , Kec. BOJONG SARI , DEPOK
Koordinat: -6.425593, 106.753773
—
Perumahan/Cluster (opsional): Pilih Perumahan / Cluster
Pilih perumahan, lalu ketik untuk mencari. Sistem otomatis bisa menambahkan nama perumahan + kelurahan, kecamatan & kota ke alamat pemasangan, serta koordinat jika tersedia.
—
Alamat Pemasangan (Detail): (input textarea) No. 5 - Curug Asri No.15B
Jika memilih Gunakan alamat utama maka sistem akan otomatis mengisi alamat dan koordinat dari data pelanggan dan mengunci field di bawah. Pilih Alamat pemasangan berbeda jika ingin mengisi alamat lain / memilih perumahan.
Input:
Latitude
Longitude
SHOW MAP LOCATION BERDASARKAN LATITUDE DAN LONGITUDE.
Klik pada peta atau geser marker untuk mengubah titik pemasangan. Kolom Latitude dan Longitude akan terisi otomatis.
Pengaturan Tagihan Pertama
Atur bagaimana sistem membuat invoice pertama setelah layanan ini disimpan. Layanan akan berstatus PROSES sampai ticket pemasangan selesai.
"Invoice pertama tetap dibuat di awal saat layanan disimpan, sehingga pelanggan bisa bayar duluan. Untuk mode jatuh tempo mengikuti pemasangan selesai,tempo/expired awal tetap memakai fixed date, lalu nanti dikunci ulang saat ticket pemasangan selesai."
Select:
Tagih Proporsional
Hitung tagihan sesuai hari sampai jatuh tempo {tanggal_jatuh_tempo} (perkiraan jatuh tempo: {yyyy-mm-dd}). Siklus billing layanan memakai fixed date.
Tagih 1 bulan penuh
Langsung buat tagihan 1 bulan penuh berdasarkan harga layanan. Siklus billing memakai fixed date.
Gratis (Promo)
Buat invoice nominal 0 dan status langsung lunas. Siklus billing layanan memaki fixed date.
Pengaturan Harga Layanan
Harga dasar ini akan menjadi acuan penagihan bulanan untuk layanan ini.
Select:
Gunakan harga paket (auto)
Gunakan harga khusus (manual)
Harga Layanan (Custom, per bulan): Input → Rp. Masukan angka saja, mis: 250000
"Konfigurasi harga tersimpan di kolom price_mode dan price_custom pada layanan, sehingga jika harga paket diubah, layanan lama bisa tetap memakai harga saat dibuat."
Isi hanya jika memilih harga khusus (manual). Jika kosong, sistem akan memakai harga dari paket.
Estimasi Invoice Pertama
Estimasi ini dihitung otomatis berdasarkan paket, mode harga, dan pilihan tagihan pertama. (Jatuh tempo awal: tanggal {tanggal_jatuh_tempo} — perkiraan: yyyy-mm-dd)
—
Perkiraan yang harus dibayar
Rp 0
Pilih paket untuk melihat estimasi.
—
Rincian
Mode tagihan: -
Harga dasar: Rp 0
Periode sampai: 2026-10-10
"Estimasi ini hanya untuk panduan. Nilai final mengikuti perhitungan backend saat layanan disimpan."
Section: 
Ringkasan Pelanggan
Nama: RYU KURNIANTO
No. Reg: BF26090331
No. HP: 081314984970
Status: 
Section:
Mode Billing
 Default - Fixed Date
Semua layanan baru tetap memakai jatuh tempo fixed date secara default: tanggal 10.
"Opsi tempo mengikuti pemasangan selesai belum aktif di Settings. Form hanya menampilkan mode fixed date."
Section:
Catatan
Layanan ini akan tercatat di Layanan / Pemasangan pada detail pelanggan.
Status awal: PROSES, belum dibuat PPP / IP statis.
Invoice pertama langsung dibuat saat layanan disimpan.
Site ID dibuat otomatis di backend dari APP_ID + 7 digit unik.
Ticketing pemasangan, gangguan, pencabutan, dan lainnya dikelola di halaman detail layanan.
