# ADR 0056: Notifikasi Invoice Terbit Lewat Event dari BillingService, Ditunda ke Pagi di Luar Jam Siang

**Status**: Accepted

## Konteks
Pelanggan tidak mendapat pesan apa pun saat invoice dibuat (tagihan pertama saat registrasi layanan, periodik oleh `invoice:generate`, maupun manual). WhatsApp hanya keluar dari aturan Pengingat Tagihan berbasis tanggal jatuh tempo (H-3/H-1/H0/tunggakan) dan konfirmasi pembayaran. Tagihan pertama jatuh tempo H+1, jadi aturan H-3 tidak pernah mengenainya.

## Keputusan
1. **`InvoiceTerbitEvent` dipancarkan dari `BillingService`** (`generateInvoice()` dan `generateManualInvoice()`, yang juga dipakai `generateFirstInvoice()`), di dalam transaksi; listener antrean `wa-blast` memakai `$afterCommit`. Bukan observer `Invoice::created`: observer ikut menyala untuk seeder, factory, dan tes sehingga mengirim pesan sungguhan.
2. **Bukan tipe aturan baru di mesin pengingat.** Aturan pengingat berjalan per jam dan menunda sampai 1 jam serta bergantung pada aturan yang bisa dimatikan admin; pesan terbit harus keluar begitu invoice dibuat.
3. **Jendela siang 07:00-20:00 WIB (eksplisit `Asia/Jakarta`, bukan zona waktu aplikasi).** Di luar jendela, listener menunda ke 08:30 berikutnya (`withDelay`). Zona waktu eksplisit karena `config/app.php` bernilai `UTC` sementara jam operasional pelanggan WIB.
4. **Template `invoice_terbit` baru**, terpisah dari `pengingat_tagihan_h3`, ditambahkan lewat seeder dan migrasi data `firstOrCreate` (seeder tidak menjangkau DB produksi; migrasi tidak menimpa hasil edit admin). Kanal: WhatsApp dan email (`InvoiceTerbitNotification`) ke `Pelanggan.email` bila terisi.
5. **Tanpa opt-out per invoice**, aturan pengingat lama tidak diubah (boleh tumpang-tindih dengan H-3; admin bisa menonaktifkannya). Kegagalan kirim dicatat dan tidak pernah menggagalkan pembuatan invoice.

## Konsekuensi
- Setiap jalur pembuatan invoice baru harus lewat `BillingService`; membuat `Invoice` langsung tidak mengirim pesan.
- Semua invoice manual (denda, biaya instalasi) langsung mengirim pesan ke pelanggan.
- Listener antrean tidak mendapat method injection pada `handle()`; dependensi lewat konstruktor.

## Amandemen (2026-09-25)
Jendela siang hanya berlaku untuk batch `invoice:generate` (invoice tanpa `dibuat_oleh`). Invoice buatan admin (tagihan pertama saat registrasi, invoice manual) selalu langsung dikirim: pelanggan biasanya sedang dilayani, dan penundaan ke 08:30 tampak seperti pesan hilang.
