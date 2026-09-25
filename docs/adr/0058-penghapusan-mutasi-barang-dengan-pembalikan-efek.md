# ADR 0058: Mutasi Barang Boleh Dihapus dengan Pembalikan Efek

**Status**: Accepted (memperbarui poin konsekuensi "mutasi tidak diedit setelah tersimpan" di ADR-0057)

## Konteks
ADR-0057 menetapkan bahwa koreksi stok hanya lewat mutasi baru. Di lapangan, salah input (barang, jumlah, unit, tanggal) lebih sering terjadi daripada koreksi fisik, dan mencatat mutasi tandingan membuat riwayat serta laporan bulanan sulit dibaca. Staf meminta baris Barang Masuk/Keluar bisa dihapus langsung dari tabel.

## Keputusan
1. Mutasi **boleh dihapus, tidak boleh diedit**. Hapus butuh izin `barang.hapus` dan tercatat di activitylog.
2. Penghapusan membalik efeknya:
   - Barang Masuk yang membuat unit baru (Pembelian/Saldo Awal): unitnya ikut terhapus. Penghitung kode tidak dimundurkan, sehingga nomor tidak dipakai ulang (ADR-0057 poin 3 tetap berlaku).
   - Pengembalian: status unit kembali ke Terpasang, dan kondisinya dipulihkan dari `kondisi_sebelum_id` yang mulai disimpan di pivot `mutasi_barang_unit`. Data lama tanpa nilai ini dibiarkan kondisinya.
   - Barang Keluar: status unit kembali ke status yang dihasilkan mutasi sebelumnya untuk unit itu.
3. Penghapusan **ditolak** bila (a) mutasi itu bukan mutasi terakhir untuk salah satu unit yang disentuhnya, atau (b) saldo berjalan jenis barang menjadi negatif di tanggal mana pun sesudah mutasi itu.
4. Data Barang yang sudah punya mutasi tetap tidak bisa dihapus.

## Alternatif yang ditolak
- **Mutasi koreksi saja (ADR-0057):** tidak menjawab kebutuhan dan membuat riwayat berantakan.
- **Soft delete:** menambah filter `whereNull('deleted_at')` di setiap perhitungan stok tanpa manfaat nyata, karena jejak audit sudah dicatat activitylog.
- **Hapus bebas tanpa pemeriksaan:** bisa menghasilkan unit yatim dan stok negatif historis.

## Konsekuensi
- Riwayat unit hanya bisa "digulung balik" dari ujung. Menghapus mutasi lama berarti menghapus mutasi sesudahnya lebih dulu.
- Butuh pemeriksaan saldo berjalan per tanggal saat hapus. Volume gudang kecil, jadi dihitung langsung dari mutasi.
