# ADR 0023: Arsitektur Geospasial Data Maps, Manajemen ODP, dan Estimasi Kabel

**Status**: Accepted  
**Date**: 2026-08-25  

## Konteks

Pada operasional ISP manajemen jaringan (GOBILLING), tim sales, NOC, dan teknisi lapangan membutuhkan kemampuan geospasial untuk:
1. Memvisualisasikan sebaran infrastruktur fisik (ODP) dan pelanggan (Pelanggan, Layanan/Site, Perumahan) dalam satu peta interaktif terpadu.
2. Melakukan estimasi cepat kebutuhan kabel drop optik dari titik calon pelanggan / koordinat survey ke ODP terdekat sebelum survey fisik, guna mempercepat penerbitan penawaran (*quotation*).
3. Mengelola master data ODP beserta kapasitas port fisiknya (`odp_port`).

Sebelumnya:
- Koordinat pelanggan dan perumahan disimpan di basis data (`latitude, longitude`), namun belum ada peta sebaran global yang menggabungkan seluruh layer entitas.
- Belum ada modul CRUD ODP untuk mengelola kapasitas port dan deskripsi/PON.
- Estimasi jarak masih bersifat manual tanpa perhitungan faktor belokan jalan (*path factor*) dan cadangan kabel (*slack reserve*).

## Keputusan

### 1. Standarisasi Skema & Bahasa Baku Domain
- Mengikuti arsitektur baku GOBILLING dengan penamaan Bahasa Indonesia:
  - Tabel master: `odp` (`id`, `nama_odp`, `perumahan_id`, `kapasitas_port`, `keterangan`, `latitude`, `longitude`, `created_at`, `updated_at`).
  - Tabel anak port: `odp_port` (`id`, `odp_id`, `nomor_port`, `status`, `layanan_pelanggan_id`). Status port mencakup: `kosong`, `terpakai`, `rusak`.
  - Entitas terkait: `pelanggan` dan `perumahan`.
- Menambahkan kolom `keterangan` (nullable text) pada tabel `odp` via migrasi `add_keterangan_to_odp_table`.

### 2. Otomasi Siklus Hidup Port ODP (`odp_port`) & Import Geospasial
- Saat ODP dibuat dengan kapasitas $N$, sistem secara otomatis men-generate baris `odp_port` dari nomor 1 sampai $N$ dengan status default `kosong`.
- Saat kapasitas ODP dinaikkan ($M > N$), sistem otomatis menambahkan port baru $N+1 \dots M$.
- Saat kapasitas ODP diturunkan ($M < N$), sistem memvalidasi bahwa port yang akan dihapus tidak sedang terikat ke `layanan_pelanggan_id` atau berstatus `terpakai`.
- Penghapusan ODP diproteksi: tidak dapat dihapus jika masih ada port yang berstatus `terpakai`.
- **Import Massal Berkas KML & GeoJSON**:
  - Disediakan parser bawaan `App\Services\Geospatial\KmlParser` dan `GeoJsonParser` (menggunakan XML/JSON parser native PHP yang ringan dan aman).
  - Ekstraksi otomatis titik `Point` (koordinat, nama, deskripsi/PON).
  - Menampilkan modal preview sebelum commit insert batch dan auto-generate port untuk setiap ODP.

### 3. Arsitektur "Data Maps" (Leaflet.js + MarkerCluster & Coverage GeoJSON)
- Peta utama menggunakan **Leaflet.js** dan tile **OpenStreetMap** (bebas biaya lisensi dan mandiri).
- Pemuatan titik koordinat menggunakan endpoint API controller JSON (`GET /api/maps/markers`) dengan payload minimal: `[{id, lat, lng, type, title, subtitle, status, color, detail_url}]` untuk mencegah beban memori HTML di browser.
- Mendukung penapisan 5 layer independen:
  1. `pelanggan`: Pin lokasi pelanggan (hijau = aktif, merah = nonaktif/suspend, kuning = prospek).
  2. `layanan`: Titik instalasi site langganan aktif.
  3. `perumahan`: Titik pusat cluster perumahan / area coverage.
  4. `odp`: Titik ODP dengan label kapasitas port.
  5. `coverage`: Layer Polygon GeoJSON batas cakupan jaringan / perumahan dengan style transparan dan interaksi popup nama area.
- Menggunakan `leaflet.markercluster` untuk pengelompokan titik otomatis saat zoom out agar antarmuka tetap lancar (*60 FPS*).

### 4. Kalkulator Estimasi Kabel Non-Destruktif
- Estimasi kabel dirancang sebagai kalkulator non-destruktif (*transient calculator*) yang tidak menyimpan data permanen ke basis data.
- **Perhitungan Jarak**:
  - Filter pencarian nama/PON/keterangan diterapkan pada level database query `WHERE`.
  - Jarak lurus geografis dihitung di level database MySQL 8 menggunakan fungsi spasial `ST_Distance_Sphere(point(longitude, latitude), point(target_lng, target_lat))`.
  - Diurutkan `ORDER BY jarak ASC` dan dibatasi parameter `Jumlah ODP` (default 5).
- **Perhitungan Panjang Kabel Fisik**:
  - Dihitung di level aplikasi:
    $$\text{Estimasi Kabel} = \text{round}((\text{Jarak Lurus} \times \text{Faktor}) + \text{Reserve})$$
  - `Faktor` (default `1.3`): Mengompensasi belokan jalan, tiang listrik, dan kontur tanah.
  - `Reserve` (default `25m`): Cadangan panjang kabel untuk terminasi *splicing* dan *slack loop* tiang.
- **Aksesibilitas**:
  - Memiliki halaman tersendiri di grup menu **"Jaringan & Infrastruktur"** (`/maps/estimasi-kabel`).
  - Terintegrasi di halaman detail Pelanggan (`/pelanggan/{id}`) dengan koordinat ter-prefill otomatis.
  - Mendukung tombol **"Gunakan Lokasi Saya"** memanfaatkan Geolocation API browser (HTML5).

### 5. Struktur Navigasi Menu
- **ODP (Optical Distribution Point)** ditempatkan di bawah grup **"Jaringan & Infrastruktur"**:
  1. `Router` (`router.index`)
  2. `Log Integrasi` (`mikrotik.logs.index`)
  3. `IP Pool` (`ip-pool.index`)
  4. `ODP (Optical Distribution Point)` (`odp.index`)

- Dibuat grup navigasi mandiri **"Maps & Estimasi Kabel"** (`icon: map-pin` / `map`) yang berisi 2 link:
  1. **Maps Lokasi** (`maps.lokasi` / `maps.index`) — Peta interaktif sebaran 4 layer.
  2. **Estimasi Kabel** (`maps.estimasi-kabel`) — Kalkulator geospasial panjang kabel.

## Konsekuensi

- **Positif**:
  - Seluruh staf operasional, NOC, dan sales memiliki visibilitas geospasial real-time tanpa ketergantungan API berbayar.
  - Proses survey kelayakan calon pelanggan dan quotation panjang kabel menjadi instan dan terukur.
  - Master data ODP dan status port terkelola secara akurat dan konsisten dengan siklus hidup billing pelanggan.
- **Pertimbangan**:
  - Browser membutuhkan izin GPS (HTTPS) untuk fitur "Gunakan Lokasi Saya", dengan fallback input koordinat manual jika izin tidak diberikan.
