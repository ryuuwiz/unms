# PRD Fase 5: Laporan Lanjutan & Maps

**Referensi**: PRD-Arsitektur-UNMS-Laravel.md (skema `invoice`, `pembayaran`, `router`, `odp`, `perumahan`, `pelanggan`)
**Dua sub-modul independen**: (A) Laporan Keuangan Bulanan dengan Skor Kesehatan, (B) Peta ODP & Estimasi Kabel. Keduanya bisa dikerjakan paralel karena tidak saling bergantung.

---

# Bagian A — Laporan Keuangan Bulanan dengan Skor Kesehatan

## A.1 Prinsip Desain: Snapshot, Bukan Live Query

**Keputusan arsitektur penting**: laporan bulanan **disimpan sebagai snapshot** (tabel `laporan_keuangan_bulanan`), bukan dihitung ulang secara live setiap kali dibuka.

Alasan: laporan bulan Januari yang sudah "final" tidak boleh berubah angkanya hanya karena ada invoice lama yang diedit/dihapus di bulan berikutnya. Laporan keuangan historis harus konsisten sebagai catatan resmi — mirip prinsip akuntansi "tutup buku". Snapshot juga jauh lebih cepat dibuka (tidak perlu agregasi ribuan baris invoice tiap kali admin buka halaman laporan).

### Skema tambahan

```
laporan_keuangan_bulanan
  id
  tahun (int), bulan (int 1-12)
  total_transaksi (int), total_amount (decimal 14,2)
  unpaid_amount (decimal 14,2), collection_rate_persen (decimal 5,2)
  top10_share_persen (decimal 5,2)
  pola_bayar_before (int), pola_bayar_on (int), pola_bayar_after (int), pola_bayar_unknown (int)
  rata_rata_hari_ke_jatuh_tempo (decimal 6,2)  -- positif = lebih awal, negatif = telat
  router_dependency_persen (decimal 5,2), router_dominan (string, nullable)
  transaksi_terhapus_count (int)
  promo_terpakai_count (int), promo_total_nilai (decimal 14,2)
  layanan_baru_count (int), layanan_renewal_count (int), layanan_akan_expired_count (int)
  data_lengkap (json)   -- breakdown: metode pembayaran, tipe pelayanan, router top 10, status layanan, top customers, trend harian
  keunggulan (json)     -- array string, hasil rules engine
  risiko (json)         -- array string, hasil rules engine
  hasil_analisis (text, nullable)   -- narasi "Hasil Analisis & Rekomendasi"
  kesimpulan (text, nullable)       -- narasi "Kesimpulan"
  status (enum: draft, final)
  dibuat_pada, di_finalisasi_pada (nullable)

  -- unique (tahun, bulan)
```

`data_lengkap` menyimpan seluruh breakdown detail (grafik metode pembayaran, tipe layanan, router top 10, trend amount harian, top customers, paid vs unpaid) sebagai satu blob JSON — karena data ini murni untuk ditampilkan ulang, bukan untuk di-query lagi, tidak perlu dinormalisasi ke tabel terpisah.

## A.2 Komponen Skor Kesehatan & Rumus

| Metrik | Rumus | Sumber data |
|---|---|---|
| Collection rate | `total_dibayar / total_ditagihkan * 100` | `invoice` (semua invoice terbit bulan tsb) + `pembayaran` |
| Konsentrasi Top 10 (`top10_share_persen`) | `SUM(amount) 10 pelanggan teratas / total_amount * 100` | `invoice` group by `pelanggan_id`, order by sum desc, limit 10 |
| Pola bayar (before/on/after/unknown) | Bandingkan `pembayaran.dibayar_pada` vs `invoice.tanggal_jatuh_tempo` per invoice lunas | `pembayaran` join `invoice` |
| Rata-rata hari ke jatuh tempo | `AVG(tanggal_jatuh_tempo - dibayar_pada)` dalam hari, dalam hari (positif=lebih awal) | sama seperti di atas |
| Router dependency | `SUM(amount) router dominan / total_amount * 100` | `invoice` join `layanan_pelanggan` group by `router_id` |
| Transaksi terhapus | `COUNT(*)` invoice dengan `status = dihapus` pada periode | `invoice` |
| Promo terpakai | `COUNT(*)` dan `SUM(nilai_diskon)` dari `promo_penggunaan` pada invoice periode tsb | `promo_penggunaan` join `invoice` join `promo` |
| Layanan baru (Service New) | `COUNT(*)` `layanan_pelanggan` dengan `tanggal_mulai` jatuh di periode | `layanan_pelanggan` |
| Layanan diperpanjang (Renewals) | `COUNT(*)` invoice lunas untuk `layanan_pelanggan` yang **bukan** invoice pertama layanan tsb | `invoice` join `layanan_pelanggan` |
| Layanan akan expired | `COUNT(*)` `layanan_pelanggan` dengan `tanggal_expired` jatuh di 30 hari ke depan dari akhir periode | `layanan_pelanggan` |
| Status layanan (breakdown) | `COUNT(*)` group by `status` (aktif/suspend/proses/berhenti) per akhir periode | `layanan_pelanggan` |
| Tipe pelayanan (breakdown) | `COUNT(*)` dan `SUM(amount)` group by `jenis_koneksi` (pppoe/ip_static) | `invoice` join `layanan_pelanggan` |

**Catatan**: baris Promo, Layanan baru/Renewals/Akan expired, Status layanan, dan Tipe pelayanan di atas menutup gap dari draf pertama — field-field ini ada eksplisit di `Data_UNMS.md` bagian "Promo & Resiko", "Ringkasan Billing", dan "Tipe Pelayanan" tapi sebelumnya tidak termasuk di rumus skor kesehatan.

## A.3 Rules Engine: Keunggulan vs Risiko

Jangan hardcode string kalimat langsung di command generator — buat **konfigurasi threshold terpisah** supaya ambang batas bisa disesuaikan tanpa ubah kode generator.

### `config/kesehatan_keuangan.php`

```
return [
    'top10_share_aman_maksimal' => 15,        // %
    'collection_rate_baik_minimal' => 85,      // %
    'collection_rate_waspada_minimal' => 70,   // % (di bawah ini = risiko tinggi)
    'router_dependency_waspada_minimal' => 60, // %
];
```

### `App\Services\Laporan\SkorKesehatanRulesEngine`

Method `evaluasi(array $metrik): array` mengembalikan `['keunggulan' => [...], 'risiko' => [...]]`. Contoh aturan (imperatif, ikuti pola pesan dari data UNMS Anda):

- Jika `top10_share_persen <= config('kesehatan_keuangan.top10_share_aman_maksimal')` → tambah ke `keunggulan`: `"Konsentrasi pelanggan aman (Top10 share {persen}%)."` — sebaliknya tambah ke `risiko` dengan pesan berbeda.
- Jika `transaksi_terhapus_count === 0` → keunggulan: `"Tidak ada transaksi terhapus pada periode ini."`
- Jika `collection_rate_persen < config('...waspada_minimal')` → risiko: `"Collection rate rendah ({persen}%) → unpaid amount Rp {format_rupiah}."`
- Jika `pola_bayar_before` adalah nilai dominan di antara before/on/after → keunggulan: `"Pola bayar dominan: lebih awal (sebelum jatuh tempo) (Before/On/After/Unknown: {n}/{n}/{n}/{n})."`
- Jika `router_dependency_persen >= config('...router_dependency_waspada_minimal')` → risiko: `"Ketergantungan tinggi pada router '{nama_router}' (share {persen}% dari total)."`
- Cek juga proporsi invoice dengan `metode_pembayaran` kosong/tidak tercatat — jika signifikan → risiko soal "alur bayar & komunikasi pembayaran perlu diperkuat" (pola persis seperti contoh di data UNMS Anda).

Struktur rules engine ini **harus dites unit test per aturan** (Bagian A.5) — supaya nanti nambah/ubah threshold tidak mendadak mengubah kalimat yang salah konteks.

## A.3b Analisis Naratif: "Hasil Analisis & Rekomendasi" dan "Kesimpulan"

Dua bagian ini **berbeda** dari daftar Keunggulan/Risiko (A.3) — di `Data_UNMS.md` keduanya berdiri sendiri sebagai teks naratif, bukan daftar poin. Ada dua opsi implementasi, pilih sesuai kebutuhan:

**Opsi 1 — Template deterministik (default, disarankan untuk MVP)**

`App\Services\Laporan\NarasiLaporanGenerator::buatHasilAnalisis(array $metrik, array $keunggulan, array $risiko): string` menyusun paragraf dari potongan kalimat siap pakai berdasarkan kombinasi kondisi (mis. jumlah risiko > jumlah keunggulan → paragraf pembuka "perlu perhatian", sebaliknya → "kondisi sehat"), lalu merangkai poin risiko dengan urutan prioritas jadi rekomendasi bernomor. Cepat, gratis, hasilnya konsisten dan mudah diuji unit test — tapi kalimatnya agak kaku/berulang antar bulan.

**Opsi 2 — Dibantu Claude API (opsional, kualitas narasi lebih natural)**

Kirim seluruh metrik terstruktur (bukan data mentah invoice, cukup angka hasil agregasi) ke Claude API dengan prompt yang meminta paragraf "Hasil Analisis & Rekomendasi" dan "Kesimpulan" dalam Bahasa Indonesia, gaya laporan bisnis. Instruksi implementasi:
- Panggil lewat `POST https://api.anthropic.com/v1/messages`, model `claude-sonnet-4-6` cukup untuk tugas naratif seperti ini (tidak perlu model besar dan mahal untuk merangkai teks dari angka yang sudah jadi).
- **Kirim hanya angka agregat** (`collection_rate_persen`, `top10_share_persen`, daftar keunggulan/risiko, dst) di prompt — jangan pernah kirim data mentah pelanggan (nama, NIK, invoice individual) ke API eksternal, cukup ringkasan angka yang memang akan tampil di laporan.
- Simpan hasil ke `hasil_analisis`/`kesimpulan`, tapi **jangan panggil API ini secara sinkron** saat command generator jalan — dispatch sebagai job terpisah `BuatNarasiLaporanJob` dengan retry, supaya kalau API eksternal lambat/gagal, laporan tetap ter-generate (angka & grafik selalu tersedia; narasi menyusul beberapa saat kemudian atau fallback ke Opsi 1 kalau job gagal setelah beberapa kali retry).
- Beri admin tombol "Regenerasi narasi" di dashboard untuk memicu ulang job ini kapan saja, tanpa perlu regenerasi seluruh laporan.

**Rekomendasi**: mulai dengan Opsi 1 untuk MVP Fase 5 supaya modul ini tidak bergantung pada API eksternal untuk hal dasar. Opsi 2 bisa ditambahkan belakangan sebagai peningkatan kualitas, dengan Opsi 1 tetap jadi fallback otomatis.

## A.4 Command Generator

`php artisan laporan:generate-bulanan {tahun} {bulan}` — dijalankan **otomatis via scheduler** tanggal 1 tiap bulan untuk bulan sebelumnya (`Schedule::command('laporan:generate-bulanan')->monthlyOn(1, '02:00')`), dan **bisa dijalankan manual** untuk regenerasi/backfill data lama.

Instruksi implementasi:

1. Jalankan seluruh query agregasi Bagian A.2 **di level database** (query builder dengan `SUM`/`AVG`/`COUNT`/`GROUP BY`), **jangan** tarik semua baris invoice ke koleksi PHP lalu hitung manual — untuk ISP dengan ribuan invoice per bulan ini akan lambat dan boros memori.
2. Panggil `SkorKesehatanRulesEngine::evaluasi()` dengan hasil agregasi.
3. `DB::transaction()`: `LaporanKeuanganBulanan::updateOrCreate(['tahun' => ..., 'bulan' => ...], [...])`. Gunakan `updateOrCreate` supaya command bisa dijalankan ulang aman (idempotent) selama status masih `draft`.
4. **Jangan overwrite laporan berstatus `final`** — jika admin sudah menandai laporan bulan tertentu sebagai final (lewat tombol di dashboard), command generator harus skip bulan itu kecuali dipanggil dengan flag eksplisit `--force`.

## A.5 Testing

- [ ] Unit test tiap aturan `SkorKesehatanRulesEngine` — beri metrik contoh, pastikan kalimat & kategori (keunggulan/risiko) sesuai threshold
- [ ] Feature test command generator — invoice & pembayaran seed data, jalankan command, cocokkan angka `collection_rate_persen` dan `top10_share_persen` dengan hitungan manual
- [ ] Test idempotency command — jalankan dua kali untuk bulan sama (status draft) → hasil ter-update, bukan duplikat baris
- [ ] Test laporan `final` tidak ter-overwrite tanpa `--force`
- [ ] Feature test metrik Promo, Layanan baru/Renewals/Akan expired, dan Tipe pelayanan — cocokkan dengan hitungan manual dari data seed
- [ ] Unit test `NarasiLaporanGenerator::buatHasilAnalisis()` (Opsi 1) — pastikan tetap menghasilkan paragraf valid meski `keunggulan`/`risiko` kosong (kasus data belum cukup)
- [ ] Jika Opsi 2 dipakai: test `BuatNarasiLaporanJob` fallback ke Opsi 1 saat API Claude gagal setelah retry habis

## A.6 Visualisasi

Frontend (Filament widget / Livewire + Chart.js sesuai stack Anda) membaca `data_lengkap` langsung dari snapshot — tidak perlu endpoint agregasi terpisah:
- Grafik metode pembayaran & tipe layanan: pie/donut chart
- Grafik router (Top 10): bar chart horizontal
- Trend amount harian: line chart
- Paid vs unpaid: stacked bar atau donut dua warna
- Top customers: tabel biasa (bukan chart)

---

# Bagian B — Peta ODP & Estimasi Kabel

## B.1 Data Maps (Tampilan Lokasi)

Menampilkan 4 layer di satu peta Leaflet: titik pelanggan, titik layanan, perumahan, ODP — dengan filter aktif/nonaktif per layer dan filter tambahan (status pelanggan, kapasitas ODP tersisa, perumahan tertentu).

### Instruksi implementasi

- **Jangan load semua marker sekaligus** bila jumlah pelanggan sudah ribuan — query dibatasi **bounding box** viewport peta saat ini: `WHERE latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?`, di-refresh tiap kali peta di-pan/zoom (event `moveend` Leaflet memicu request baru).
- Gunakan `Leaflet.markercluster` di frontend untuk marker pelanggan yang padat di satu area — mengelompokkan jadi cluster angka saat zoom out, pecah jadi marker individual saat zoom in.
- Endpoint `GET /api/maps/pelanggan?bounds=...&status=...` mengembalikan hanya `id, nama, latitude, longitude, status` (bukan seluruh kolom pelanggan) — payload ringan untuk peta.
- Marker ODP ditampilkan dengan warna berbeda berdasarkan sisa kapasitas port (mis. hijau jika port kosong > 30%, kuning < 30%, merah penuh) — hitung `odp_port` dengan `status = kosong` per `odp_id`, bisa di-cache (kolom `port_terpakai`/`kapasitas_port` di tabel `odp` sudah ada di skema awal, cukup di-update via observer tiap `odp_port` berubah status, bukan dihitung live tiap render peta).

## B.2 Estimasi Kabel

### Skema tambahan

```
survey_titik
  id
  latitude, longitude
  dibuat_oleh FK -> pengguna
  odp_terpilih_id FK nullable -> odp
  jarak_meter (decimal 10,2)              -- hasil Haversine murni
  estimasi_kabel_meter (decimal 10,2)     -- setelah faktor + reserve
  faktor_dipakai (decimal 4,2)
  reserve_meter_dipakai (decimal 8,2)
  keterangan (nullable)
  status (enum: draft, dikonversi_jadi_pelanggan)
  timestamps
```

Tabel ini menyimpan histori survey — bukan cuma kalkulasi sekali pakai — supaya sales/teknisi bisa meninjau ulang hasil survey sebelum deal dengan calon pelanggan, dan hasilnya bisa dikonversi jadi data pemasangan riil nanti (`status = dikonversi_jadi_pelanggan`, terhubung ke ticket pemasangan).

### Rumus

```
jarak_meter = haversine(lat1, lon1, lat2, lon2)   -- jarak garis lurus
estimasi_kabel_meter = (jarak_meter * faktor) + reserve_meter
```

- **Faktor**: pengali karena kabel tidak pernah dipasang garis lurus (mengikuti jalan, tiang, dsb) — default disarankan 1.3–1.5, tapi dibuat **input yang bisa diubah user saat survey** (sesuai field "Faktor" di form Anda), bukan konstanta hardcode.
- **Reserve**: buffer meter tambahan untuk kelonggaran fisik pemasangan (looping di tiang, dsb) — juga input manual per survey.

### Instruksi implementasi — `App\Services\Maps\EstimasiKabelService`

Method `cariOdpTerdekat(float $lat, float $lon, int $jumlah, float $faktor, float $reserveMeter, ?string $filterNama = null): Collection`:

1. **Jangan hitung Haversine untuk seluruh baris `odp` di PHP** kalau jumlah ODP sudah besar — gunakan **rumus Haversine langsung di query SQL** (MySQL mendukung fungsi trigonometri `SIN`/`COS`/`ACOS`/`RADIANS`) untuk filter awal kandidat terdekat sebelum diproses lebih lanjut di PHP. Contoh pola query (bukan kode final, sesuaikan ke query builder Laravel):
   ```sql
   SELECT *, (
     6371000 * ACOS(
       COS(RADIANS(?)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(?))
       + SIN(RADIANS(?)) * SIN(RADIANS(latitude))
     )
   ) AS jarak_meter
   FROM odp
   HAVING jarak_meter IS NOT NULL
   ORDER BY jarak_meter ASC
   LIMIT ?
   ```
   (`6371000` = radius bumi dalam meter)
2. Filter ODP yang punya **port tersedia** (`odp.kapasitas_port - odp.port_terpakai > 0`) — ODP penuh tidak boleh muncul sebagai kandidat meskipun paling dekat, kecuali user secara eksplisit ingin lihat semua (opsi toggle "tampilkan ODP penuh").
3. Terapkan filter nama/PON/keterangan (`LIKE` sederhana) jika `$filterNama` diisi.
4. Untuk tiap kandidat, hitung `estimasi_kabel_meter` pakai rumus di atas dengan `$faktor` dan `$reserveMeter` dari input user.
5. Return `$jumlah` kandidat teratas, urut dari jarak terdekat.

### Endpoint & UI

- `POST /maps/estimasi-kabel/cari` — body: `latitude`, `longitude`, `filter_nama` (nullable), `jumlah_odp`, `faktor`, `reserve_meter`. Response: daftar ODP kandidat + jarak + estimasi kabel.
- Tombol **"Gunakan Lokasi Saya"** di frontend memakai `navigator.geolocation.getCurrentPosition()` browser — isi otomatis field latitude/longitude, tetap bisa diedit manual (untuk kasus survey dari lokasi berbeda, mis. dari kantor tapi survey titik pelanggan).
- Tombol **"Simpan sebagai Survey"** — insert ke `survey_titik` dengan ODP yang dipilih user dari hasil pencarian (bukan otomatis ODP terdekat — user tetap yang memutuskan, karena pertimbangan lapangan seperti akses tiang tidak selalu tertangkap dari koordinat saja).

## B.3 Testing

- [ ] Unit test rumus Haversine — bandingkan dengan hasil kalkulator jarak eksternal untuk beberapa titik koordinat Indonesia yang diketahui jaraknya
- [ ] Feature test `cariOdpTerdekat` — ODP dengan port penuh tidak muncul di hasil default; muncul saat toggle "tampilkan penuh" aktif
- [ ] Feature test filter nama/PON/keterangan mempersempit hasil dengan benar
- [ ] Test endpoint maps dengan bounding box — pastikan hanya marker dalam viewport yang dikembalikan, bukan seluruh tabel pelanggan
- [ ] Manual test performa: seed ribuan titik ODP/pelanggan dummy, ukur waktu respons endpoint maps & estimasi kabel sebelum dan sesudah optimasi bounding box/index

---

# Bagian C — Checklist Urutan Kerja Fase 5

```
1. Migrasi laporan_keuangan_bulanan & survey_titik
2. Rules engine skor kesehatan + unit test (bisa dikerjakan tanpa UI sama sekali dulu)
3. Command generator laporan bulanan + jadwalkan di scheduler
4. Dashboard laporan (baca dari snapshot, render grafik)
5. Endpoint maps dasar (tampilkan 4 layer, tanpa bounding box dulu — optimasi belakangan setelah data asli mulai banyak)
6. Service estimasi kabel + endpoint pencarian ODP terdekat
7. UI survey (form + tombol lokasi saya + simpan survey)
8. Optimasi performa (bounding box, clustering, index) — setelah modul fungsional, sebelum data produksi membesar
```

Catatan: Bagian A dan B bisa dikerjakan **paralel** oleh dua orang berbeda kalau ada — keduanya tidak saling bergantung sama sekali, keduanya hanya bergantung pada skema Fase 1 (Fondasi) yang sudah ada.