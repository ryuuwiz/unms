# ADR 0006: Pemodelan Hierarki Area & Wilayah, Geolokasi Perumahan, dan Proteksi Penghapusan (Guarded Deletion)

## Status
Diterima (Accepted)

## Konteks
Sistem UNMS mengelola cakupan area operasional ISP melalui 4 tingkat hierarki wilayah: **Kota** ➔ **Kecamatan** ➔ **Kelurahan** ➔ **Perumahan/Cluster**.

Data wilayah ini menjadi jangkar lokasi bagi pemasangan instalasi pelanggan (`pelanggan`), penempatan perangkat distribusi optik (`odp`), serta perencanaan rute fiber optik dan peta coverage (`maps`).

Terdapat 3 kebutuhan arsitektural krusial yang harus ditetapkan:
1. **Titik Geolokasi Perumahan**: Perumahan/cluster memerlukan titik koordinat sentral (`latitude`, `longitude`) untuk mempermudah visualisasi sebaran jaringan, navigasi peta teknisi lapangan, dan estimasi jalur fiber optik.
2. **Proteksi Integritas Data (Guarded Deletion)**: Penghapusan bertingkat (*foreign key cascade*) tanpa proteksi di tingkat aplikasi berisiko menghapus seluruh sub-wilayah dan melepaskan relasi pelanggan/ODP yang sudah terpasang.
3. **Navigasi & Interaksi Form Bertingkat (Cascading Select)**: Input data sub-wilayah membutuhkan relasi induk yang presisi tanpa ambiguasi nama kelurahan/kecamatan yang serupa di kota berbeda.

## Keputusan
1. **Penambahan Geolokasi pada Perumahan**:
   - Menambahkan kolom `latitude` dan `longitude` bertipe `decimal(10,7)` (nullable) pada tabel `perumahan`.
   - Mengintegrasikan komponen peta Leaflet interaktif (`<x-map-picker>`) pada form Create & Edit Perumahan, serta modal peta (`<x-map-view>`) pada baris tabel Index Perumahan.

2. **Penerapan Guarded Deletion**:
   - Menerapkan aturan pencegahan hapus (*guarded deletion*) pada level aplikasi/Livewire/Model:
     - **Kota**: Tidak dapat dihapus jika masih memiliki `kecamatans`.
     - **Kecamatan**: Tidak dapat dihapus jika masih memiliki `kelurahans`.
     - **Kelurahan**: Tidak dapat dihapus jika masih memiliki `perumahans`.
     - **Perumahan**: Tidak dapat dihapus jika masih memiliki `pelanggans` atau `odps`.
   - Menampilkan notifikasi error/peringatan yang jelas kepada staf jika penghapusan dicegah.

3. **Reaktivitas Cascading Dropdown**:
   - Form pembuatan/pengeditan Perumahan dan Kelurahan menggunakan dropdown hierarkis berantai dengan reaktivitas Livewire (`wire:model.live` / hook perubahan nilai induk).
   - Menyediakan dropdown filter hierarkis pada tabel Index Kecamatan, Kelurahan, dan Perumahan.

4. **Standar Validasi & Format**:
   - `nama_kota`: Unik secara sistem.
   - `nama_kecamatan`: Unik per `kota_id`.
   - `nama_kelurahan`: Unik per `kecamatan_id`.
   - `nama_perumahan`: Unik per `kelurahan_id`.
   - `singkatan` (Perumahan): Opsional, format huruf kapital/angka (maksimal 10 karakter).

## Konsekuensi
- **Positif**:
  - Integritas data jaringan dan pelanggan terjamin aman dari penghapusan tidak disengaja.
  - Data titik cluster perumahan siap dipakai untuk modul peta dan estimasi kabel survey.
  - Alur pengisian data wilayah konsisten, terstruktur, dan ramah pengguna.
- **Trade-off**:
  - Penghapusan data wilayah induk yang memiliki data turunan membutuhkan pembersihan/pemindahan data anak terlebih dahulu secara manual.
