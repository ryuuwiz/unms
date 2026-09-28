# Hapus Permanen Invoice dibatasi hanya untuk invoice tanpa riwayat pembayaran

Super admin butuh cara menghapus invoice (`invoice.hapus_permanen`) yang lebih kuat dari Pembatalan Invoice biasa (`invoice.batalkan`, kini dipegang admin & super admin), yang hanya mengubah status tanpa pernah menghancurkan data. Migration `2026_09_12_100000_harden_pembayaran_table_soft_delete_and_restrict.php` sudah sengaja mengunci `pembayaran.invoice_id` jadi `restrictOnDelete()` justru untuk mencegah `forceDelete()` pada invoice menghancurkan catatan keuangan pelanggan tanpa jejak.

Daripada membalik hardening itu, Hapus Permanen Invoice dibatasi: hanya invoice berstatus `dibatalkan` yang tidak pernah punya baris `Pembayaran` (termasuk yang sudah soft-delete) atau `TransaksiPaymentGateway` sama sekali. `transaksi_payment_gateway.invoice_id` yang sebelumnya `cascadeOnDelete()` diubah jadi `restrictOnDelete()` juga, mengikuti pola yang sama, sehingga DB sendiri yang menjamin batasan ini -- bukan cuma pengecekan di level aplikasi.

Konsekuensi: invoice yang pernah lunas lalu dibatalkan lewat Pembatalan Invoice Lunas (yang menyisakan `Pembayaran` soft-delete) tidak akan pernah bisa dihapus permanen, hanya bisa dibatalkan selamanya. Ini disengaja -- lihat CONTEXT.md "Penghapusan Permanen Invoice".
