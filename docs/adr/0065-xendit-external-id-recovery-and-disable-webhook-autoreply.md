# ADR 0065: Pemulihan Invoice Xendit melalui `external_id` dan Penghentian Auto-Reply WhatsApp

## Konteks

Invoice lokal dapat menyimpan ID Hosted Invoice Xendit yang kemudian tidak ditemukan oleh
API, misalnya ketika koneksi gateway dibuat dengan akun atau mode yang berbeda. Membuat
invoice baru tanpa memeriksa `external_id` berisiko menghasilkan dua invoice untuk satu
tagihan lokal, terutama bila invoice lama sebenarnya sudah lunas.

Webhook WhatsApp sebelumnya membuat balasan otomatis berbasis keyword dan memasukkannya ke
antrean dengan `jenis = webhook_autoreply`. Balasan tersebut tidak lagi menjadi perilaku
yang diinginkan, tetapi data inbound, verifikasi signature, dan histori tiket tetap penting.

## Keputusan

1. Status Xendit tetap diverifikasi melalui API, bukan dengan scraping URL checkout.
2. Jika lookup berdasarkan ID gagal, driver mencari `external_id` transaksi lokal melalui
   `InvoiceApi::getInvoices()` pada endpoint resmi `/v2/invoices`.
3. Hanya satu hasil dengan `external_id` dan nominal yang cocok yang boleh memulihkan
   referensi lokal. Status `PAID` atau `SETTLED` diproses melalui alur pelunasan idempoten
   yang sama seperti webhook.
4. Tidak ada hasil membuat referensi lama invalid sehingga alur pembayaran dapat membuat
   invoice baru. Hasil lebih dari satu dianggap ambigu: sistem tidak auto-LUNAS dan tidak
   membuat invoice baru otomatis.
5. URL `checkout.xendit.co/web/{id}` hanya merupakan URL hosted payment page; URL tersebut
   bukan sumber kebenaran status pembayaran.
6. Webhook WhatsApp tidak lagi membuat balasan otomatis atau antrean `webhook_autoreply`.
   Item historis yang belum terkirim ditandai `gagal` dengan alasan
   `Webhook auto-reply dinonaktifkan.` dan worker melewati jenis tersebut.
7. Penerimaan webhook, validasi signature, pembaruan status sesi, dan pencatatan histori
   tiket tetap dipertahankan.

## Konsekuensi

- Pembayaran yang benar-benar sudah lunas dapat dipulihkan tanpa membuat invoice duplikat,
  selama API key, mode akun, `external_id`, dan nominal cocok.
- Kondisi ambigu berhenti dengan aman dan memerlukan pemeriksaan admin.
- Operator harus menindaklanjuti pesan WhatsApp inbound secara manual atau melalui tiket.
- Data antrean historis dipertahankan untuk audit, tetapi tidak akan dikirim.
