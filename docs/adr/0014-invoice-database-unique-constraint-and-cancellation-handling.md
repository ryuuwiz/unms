# ADR 0014: Database Unique Constraint untuk Invoice Aktif dan Penanganan Pembatalan Tagihan

## Konteks
Setelah penerapan standardisasi `periode_tagihan` pada ADR 0013, masih ditemukan residu data ganda (*in-flight/legacy*) pada pelanggan `REG-2026-000001` di mana invoice seeder berstatus `menunggu_pembayaran` berdampingan dengan invoice baru yang telah `lunas` pada periode `2026-08`.

Untuk memastikan jaminan integritas data yang kokoh tidak hanya pada tingkat aplikasi (*application-level validation*), sistem memerlukan:
1. Rekonsiliasi data lama dengan status `dibatalkan` demi mempertahankan audit trail.
2. Penegakan integritas data di level mesin database (*ACID database constraint*) agar tidak mungkin terjadi dua invoice non-batal untuk layanan dan periode tagihan yang sama.
3. Proteksi antarmuka Portal Pelanggan agar invoice yang dibatalkan tidak memicu kebingungan atau dapat dibuka untuk transaksi pembayaran.

## Keputusan yang Diambil

1. **Rekonsiliasi Data Duplikat Menjadi Status `dibatalkan`**:
   - Tagihan duplikat berstatus `menunggu_pembayaran` yang periode tagihannya telah memiliki invoice `lunas` dibatalkan statusnya menjadi `StatusInvoice::Dibatalkan` dengan rekaman audit trail dan `keterangan_hapus` yang informatif.
   - Tidak dilakukan *hard delete* agar nomor urut invoice dan riwayat penagihan tetap akuntabel.

2. **Conditional Unique Constraint di Level Database**:
   - Menambahkan conditional / functional unique index `unique_active_layanan_periode` pada kombinasi `(layanan_pelanggan_id, periode_tagihan)` untuk seluruh baris invoice yang berstatus bukan `dibatalkan` (`status != 'dibatalkan'`) dan belum di-soft delete (`deleted_at IS NULL`).
   - Implementasi cross-database compatible:
     - SQLite: Partial unique index (`CREATE UNIQUE INDEX ... WHERE status != 'dibatalkan' AND deleted_at IS NULL`).
     - MySQL 8.0+: Functional unique index berbasis ekspresi `CASE WHEN status != 'dibatalkan' AND deleted_at IS NULL THEN CONCAT(layanan_pelanggan_id, '-', periode_tagihan) ELSE NULL END`.

3. **Perlindungan Akses Portal Pelanggan**:
   - Komponen `App\Livewire\Portal\Invoice\Bayar` otomatis memblokir dan mengalihkan navigasi jika pengguna mencoba mengakses pembayaran atas invoice yang telah dibatalkan.
   - Halaman `App\Livewire\Portal\Invoice\Show` menampilkan badge dan alert banner khusus yang menjelaskan bahwa tagihan telah dibatalkan oleh sistem/staf sehingga tidak perlu dibayar.

## Konsekuensi
- Mencegah secara mutlak duplikasi invoice bahkan jika terjadi *race conditions*, concurrent webhooks, atau eksekusi script manual di luar flow aplikasi standar.
- Memungkinkan staf membatalkan invoice yang salah (misal salah promo) dan menerbitkan invoice baru pada periode yang sama tanpa terbentur unique constraint kaku.
- Meningkatkan transparansi dan kejelasan status tagihan bagi pelanggan di portal mandiri.
