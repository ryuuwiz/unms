# ADR 0013: Standardisasi Periode Tagihan dan Idempotensi Siklus Invoice

## Konteks
Ditemukan insiden duplikasi invoice (tagihan ganda di bulan yang sama) pada pelanggan karena tiga faktor:
1. **Loop Regenerasi Invoice Kadaluarsa**: Saat invoice melewati jatuh tempo dan berstatus `kadaluarsa`, scheduler `invoice:generate` yang hanya memeriksa status `menunggu_pembayaran` menganggap pelanggan belum memiliki tagihan dan menerbitkan invoice baru setiap hari.
2. **Ketiadaan Atribut Periode Tagihan**: Sistem sebelumnya hanya mengandalkan `tanggal_terbit`. Pada siklus H-7 di akhir bulan, tagihan untuk bulan berikutnya tercatat dengan nomor dan tanggal bulan berjalan sehingga tampak ganda.
3. **Penerbitan Manual Tanpa Guard**: Form pembuatan invoice oleh staf belum memiliki validasi penolakan jika layanan pelanggan sudah memiliki invoice aktif/berjalan pada periode terkait.

## Keputusan yang Diambil

1. **Atribut `periode_tagihan` (`YYYY-MM`)**:
   - Menambahkan kolom `periode_tagihan` (string `YYYY-MM`, misal `2026-09`) pada tabel `invoice`.
   - Untuk skema *prepaid* (bayar di muka), nilai `periode_tagihan` ditentukan berdasarkan bulan target perpanjangan masa aktif (`tanggal_expired` berikutnya), bukan bulan tanggal terbit.

2. **Idempotensi & Pencegahan Regenerasi Ganda**:
   - Scheduler `invoice:generate` tidak boleh menerbitkan invoice baru jika untuk `layanan_pelanggan_id` dan `periode_tagihan` target sudah terdapat invoice dengan status apapun (`menunggu_pembayaran`, `lunas`, `kadaluarsa`), kecuali `dibatalkan`.
   - Invoice yang `kadaluarsa` tetap menjadi invoice tunggal yang dapat langsung dilunasi oleh pelanggan atau diproses admin tanpa perlu menerbitkan invoice pengganti.

3. **Strict Validation di Level Service & UI**:
   - `BillingService::generateInvoice()` menerapkan guard clause transaksional dengan `lockForUpdate()` untuk menolak penerbitan duplikat pada `periode_tagihan` yang sama.
   - Halaman staff [`app/Livewire/Invoice/Create.php`](file:///c:/Ryu/Projects/unms/app/Livewire/Invoice/Create.php) melakukan validasi sebelum simpan dan menampilkan pesan kesalahan informatif jika tagihan untuk periode tersebut sudah ada.

4. **Integritas Database**:
   - Menambahkan composite unique index pada `(layanan_pelanggan_id, periode_tagihan, deleted_at)` di tabel `invoice` untuk mengunci duplikasi pada level database ACID.

## Konsekuensi
- Mencegah 100% bug invoice ganda baik yang dipicu oleh scheduler harian maupun klik manual staf.
- Laporan billing dan keuangan menjadi presisi karena setiap invoice memiliki penanda periode penagihan (`YYYY-MM`) yang jelas.
- Data invoice lama (seeder & eksisting) di-backfill `periode_tagihan`-nya berdasarkan riwayat tanggal terbit/jatuh tempo.
