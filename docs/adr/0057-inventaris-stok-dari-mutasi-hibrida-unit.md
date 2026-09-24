# ADR 0057: Inventaris — Stok Dihitung dari Mutasi, Pelacakan Hibrida Jenis/Unit, Kode Tidak Dipakai Ulang

**Status**: Accepted

## Konteks
Gudang ISP menyimpan barang habis pakai (kabel, konektor) yang cukup dihitung jumlahnya, dan perangkat (modem/ONT) yang perlu ditelusuri per fisik ke tiket/pelanggan tujuannya (garansi, penggantian). Laporan yang diminta adalah rekap per bulan: Stok Awal, Masuk, Keluar, Stok Akhir. Kode barang berformat `[KATEGORI]-[KONDISI]-[BRAND?]-[NOMOR]` (mis. `MDM-NEW-BF-240`, `MDM-PGT-240`) dengan barcode.

## Keputusan
1. **Stok tidak pernah disimpan sebagai angka yang bisa diedit.** Satu-satunya sumber adalah tabel mutasi (`mutasi_barang`, masuk/keluar). Stok Periode (awal/masuk/keluar/akhir per bulan) selalu dihitung dari mutasi; stok yang sudah ada sebelum sistem dipakai dimasukkan sebagai mutasi masuk bertipe Saldo Awal.
2. **Pelacakan hibrida.** Jenis Barang punya flag `dilacak_per_unit`. Jenis biasa: mutasi berupa jumlah. Jenis dilacak: setiap unit fisik punya baris `unit_barang` dengan kode & barcode sendiri; mutasi keluar wajib menyebut unit spesifik, dan jumlah mutasi = jumlah unit.
3. **Kode unit tidak pernah dipakai ulang.** Nomor berasal dari penghitung per prefix lengkap (`kode_barang_counter`, dikunci `lockForUpdate`), bukan `MAX()+1` dari unit yang ada, sehingga menghapus unit tidak membebaskan nomornya. Unit yang dikembalikan pelanggan tetap memakai kodenya; kondisinya berubah menjadi `PGT`. Kode berkondisi `PGT` hanya di-generate untuk barang bekas yang masuk tanpa kode.
4. **Stok tidak boleh negatif**, divalidasi saat mutasi keluar di dalam transaksi terkunci per jenis barang.
5. **Barcode Code128** (`picqer/php-barcode-generator`, PNG) dicetak sebagai label PDF lewat dompdf yang sudah terpasang; Code128 dipilih agar terbaca scanner 1D biasa.

## Konsekuensi
- Koreksi stok dilakukan dengan mutasi baru (masuk/keluar koreksi), bukan mengubah angka; mutasi tidak diedit setelah tersimpan.
- Rekap bulanan menjumlahkan mutasi setiap kali ditampilkan; cukup untuk volume gudang ISP kecil. Tabel snapshot bulanan bisa ditambahkan bila lambat.
- Penghitung kode terpisah dari data unit: memulihkan unit yang terhapus tidak mengembalikan nomor lamanya ke urutan.
- **Impor Inventaris (migrasi awal)** membuat unit dengan kode lamanya (mis. `MDM-NEW-BF-240`) alih-alih meng-generate; penghitung prefix dimajukan ke nomor tertinggi yang diimpor sehingga kode baru tidak pernah bentrok. Impor hanya berjalan bila belum ada mutasi sama sekali; mengulanginya harus lewat `php artisan inventaris:reset` yang disengaja (menghapus mutasi, unit, dan penghitung; jenis/kategori/kondisi tetap).
