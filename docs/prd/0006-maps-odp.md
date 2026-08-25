# PRD: Maps & Estimasi Kabel

**Modul:** Maps & Estimasi Kabel
**Status:** Draft

---

## 1. Ringkasan

Modul ini terdiri dari dua sub-fitur:

1. **Data Maps** — visualisasi semua titik geografis (Pelanggan, Layanan, Perumahan, ODP) dalam satu peta interaktif dengan layer yang bisa di-toggle.
2. **Estimasi Kabel** — kalkulator terpisah untuk mencari ODP terdekat dari titik survey dan memperkirakan panjang kabel yang dibutuhkan, dipakai saat proses sales/instalasi.

## 2. Tujuan

- Tim lapangan/NOC bisa melihat sebaran pelanggan & infrastruktur secara visual, bukan hanya tabel data.
- Sales/teknisi bisa memperkirakan kelayakan instalasi baru (ODP terdekat & estimasi kabel) **sebelum** survey fisik, mempercepat proses quotation.
- Data koordinat yang sudah tersimpan di modul lain (Customer, Alamat) dimanfaatkan ulang tanpa duplikasi data.

## 3. Role & Akses

| Role | Akses |
|---|---|
| super_admin, admin | Full akses Data Maps & Estimasi Kabel, CRUD data ODP |
| sales | Data Maps (read-only) + Estimasi Kabel (dipakai saat quotation calon pelanggan) |
| noc, teknisi | Data Maps (read-only) + Estimasi Kabel (dipakai saat instalasi/perbaikan) |

## 4. User Stories

- Sebagai **sales**, saya ingin melihat titik ODP terdekat dari lokasi calon pelanggan sebelum survey fisik, supaya saya bisa memberi estimasi awal kelayakan pemasangan.
- Sebagai **NOC**, saya ingin melihat semua titik pelanggan aktif di peta, supaya saya cepat mengenali area yang terdampak saat ada gangguan di satu wilayah.
- Sebagai **teknisi**, saya ingin memakai kalkulator Estimasi Kabel langsung dari lokasi saya di lapangan (tombol "Gunakan Lokasi Saya"), tanpa perlu mengetik koordinat manual.
- Sebagai **admin**, saya ingin mendaftarkan dan mengelola titik ODP (nama, kapasitas, lokasi), supaya data selalu ter-update untuk perhitungan estimasi kabel.
- Sebagai **sales**, saya ingin memfilter pencarian ODP berdasarkan nama/PON/keterangan, supaya saya bisa fokus ke ODP yang relevan dengan area tertentu.

## 5. Functional Requirements

### Data Maps
| ID | Requirement |
|---|---|
| FR-G.1 | Peta utama menampilkan 4 layer yang bisa di-toggle independen: Pelanggan, Layanan (Subscription), Perumahan, ODP |
| FR-G.2 | Klik satu titik menampilkan popup ringkas (nama, status, link ke halaman detail terkait) |
| FR-G.3 | Titik Pelanggan diambil dari `customers.latitude/longitude`; titik Perumahan dari `clusters.latitude/longitude`; titik ODP dari `odps` |
| FR-G.4 | Peta mendukung filter dasar: status pelanggan (aktif/nonaktif) |
| FR-G.5 | Peta memakai **Leaflet.js** dengan tile **OpenStreetMap** — tidak bergantung Google Maps API berbayar |
| FR-G.6 | Peta mendukung marker clustering saat zoom out, supaya tetap ringan meski data mencapai ribuan titik |

### Manajemen ODP
| ID | Requirement |
|---|---|
| FR-G.7 | CRUD data ODP: nama/PON, deskripsi/keterangan, kapasitas (jumlah port/slot), latitude, longitude |
| FR-G.8 | Nama ODP tidak wajib unik secara sistem (ISP kadang memakai penamaan mirip antar cluster), tapi disarankan di level UI (peringatan bila mirip) |

### Estimasi Kabel
| ID | Requirement |
|---|---|
| FR-G.9 | Form Estimasi Kabel terpisah dari Data Maps: input Latitude/Longitude titik survey — manual, atau tombol **"Gunakan Lokasi Saya"** (Geolocation API browser) |
| FR-G.10 | Filter nama ODP/PON/keterangan untuk mempersempit kandidat sebelum kalkulasi jarak dijalankan |
| FR-G.11 | Parameter **Jumlah ODP** — berapa kandidat terdekat yang ditampilkan (mis. 5) |
| FR-G.12 | Parameter **Faktor** — pengali estimasi panjang kabel terhadap jarak lurus (rute kabel fisik tidak pernah lurus), nilai default bisa dikonfigurasi (mis. `1.3`) |
| FR-G.13 | Parameter **Reserve** — cadangan panjang kabel tambahan (meter), ditambahkan ke hasil akhir tiap estimasi |
| FR-G.14 | Tombol **"Cari ODP Terdekat"** menjalankan kalkulasi (lihat §7) dan menampilkan hasil terurut dari yang terdekat: nama ODP, jarak lurus (meter), estimasi panjang kabel (`jarak × Faktor + Reserve`), sisa kapasitas (jika data kapasitas terisi) |
| FR-G.15 | Hasil Estimasi Kabel **tidak disimpan otomatis** sebagai data permanen — murni kalkulator sekali pakai |
| FR-G.16 | Estimasi Kabel dapat diakses juga dari halaman detail Customer, dengan Latitude/Longitude ter-**prefill otomatis** dari titik pelanggan tersebut, untuk mempercepat alur survey ulang/perluasan jaringan |

## 6. Data Model

**`odps`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | Nama ODP/PON |
| description | text, nullable | Keterangan |
| capacity | integer | jumlah port/slot total |
| used_capacity | integer, default 0 | opsional — pelacakan port terpakai |
| latitude, longitude | decimal | |
| created_at, updated_at | timestamp | |

**`clusters`** (field tambahan pada modul Alamat/Wilayah — bagian A)
| Kolom tambahan | Tipe | Keterangan |
|---|---|---|
| latitude, longitude | decimal, nullable | ditambahkan supaya titik Perumahan bisa muncul di Data Maps |

> Tidak ada tabel baru untuk menyimpan hasil Estimasi Kabel — sesuai FR-G.15, ini murni kalkulator.

## 7. Business Rules

- Perhitungan jarak antar dua titik koordinat memakai fungsi spasial MySQL 8 `ST_Distance_Sphere()` (formula Haversine) — bukan jarak Euclidean sederhana, supaya akurat secara geografis (lihat catatan arsitektur di `ubiquiti-nms-design-v2-review.md` §2.5).
- Filter nama ODP/PON/keterangan diterapkan **sebelum** kalkulasi jarak (di level query `WHERE`), bukan sesudah — supaya query tetap efisien meski jumlah ODP besar.
- "Jumlah ODP" membatasi hasil maksimal (`LIMIT` di query), diurutkan `ORDER BY jarak ASC`.
- Estimasi panjang kabel dihitung di **level aplikasi** (bukan query database): `(jarak_lurus × Faktor) + Reserve` — supaya rumus mudah diaudit/diubah tanpa migrasi database.
- **Faktor** dan **Reserve** adalah input per-pencarian (bukan konfigurasi global tetap), meski boleh punya nilai default yang bisa dikonfigurasi di pengaturan sistem.
- Tombol "Gunakan Lokasi Saya" memakai Geolocation API browser — butuh koneksi HTTPS & izin lokasi user. Jika izin ditolak/tidak tersedia, sistem menampilkan pesan jelas dan tetap mengizinkan input koordinat manual sebagai fallback.

## 8. Halaman yang Dibutuhkan (UI)

1. Halaman **"Data Maps"** — peta utama dengan toggle layer, filter status, popup detail per titik.
2. Halaman **"Manajemen ODP"** — list + form CRUD ODP.
3. Halaman/komponen **"Estimasi Kabel"** — form parameter (Lat/Long, filter, Jumlah ODP, Faktor, Reserve) + peta kecil pendukung (opsional, visualisasi titik survey & kandidat ODP) + tabel hasil terurut.
4. Widget **"Estimasi Kabel"** versi ringkas di halaman detail Customer (koordinat ter-prefill).

## 9. Out of Scope

- Routing jalur kabel sungguhan mengikuti jalan/saluran bawah tanah (Estimasi Kabel murni jarak udara × faktor, bukan pathfinding sungguhan).
- Penyimpanan permanen hasil kalkulasi Estimasi Kabel sebagai bagian dari rencana instalasi resmi (bisa jadi enhancement terpisah nanti).
- Integrasi drone/satelit imagery.
- Real-time tracking lokasi teknisi di lapangan.

## 10. Dependencies

- PRD #2/B (Customers) — sumber titik Pelanggan.
- PRD #5/E (Subscriptions) — sumber titik Layanan.
- Modul A (Alamat/Wilayah) — perlu tambahan `latitude`/`longitude` pada `clusters`.
- MySQL 8 dengan dukungan fungsi spasial (`ST_Distance_Sphere`).
- Library **Leaflet.js** (frontend) + tile OpenStreetMap.

## 11. Acceptance Criteria

- [ ] Peta menampilkan minimal 4 layer (Pelanggan, Layanan, Perumahan, ODP) yang bisa di-toggle independen.
- [ ] Klik satu titik menampilkan popup dengan info ringkas & link ke halaman detail terkait.
- [ ] Admin bisa menambah/mengedit/menghapus data ODP lengkap dengan koordinat.
- [ ] Form Estimasi Kabel menghasilkan daftar ODP terdekat terurut dari jarak terkecil, sejumlah yang diminta di parameter "Jumlah ODP".
- [ ] Estimasi panjang kabel yang ditampilkan sesuai rumus `(jarak × Faktor) + Reserve`.
- [ ] Tombol "Gunakan Lokasi Saya" berhasil mengisi Latitude/Longitude otomatis di browser yang mendukung & mengizinkan akses lokasi; menampilkan pesan jelas bila ditolak.
- [ ] Filter nama ODP/PON/keterangan mempersempit hasil sebelum kalkulasi jarak dijalankan.
- [ ] Widget Estimasi Kabel di halaman detail Customer ter-prefill otomatis dengan koordinat pelanggan tersebut.
- [ ] Peta tetap responsif (tidak lag berlebihan) saat menampilkan data dalam jumlah besar berkat marker clustering.