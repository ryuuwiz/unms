# ADR 0072: iPaymu Hosted Invoice Menggantikan Xendit, Callback Dikonfirmasi ke API

**Status**: Accepted, sebagian digantikan ADR-0073 (Hosted kini hanya fallback) — memperbarui ADR-0026 (gateway default) dan ADR-0007 (Xendit sebagai penerbit).

Pembayaran baru diterbitkan sebagai Hosted Invoice iPaymu (`/api/v2/payment`); pelanggan memilih metode di halaman iPaymu, dan fee dibebankan iPaymu ke pembeli lewat `feeDirection` (mengikuti `bebankan_ke_pelanggan`). Xendit sempat tetap terdaftar untuk menuntaskan link lama, lalu dihapus seluruhnya (driver, command, package, kolom `xendit_*`, fee khusus Xendit) setelah tidak ada lagi transaksi Xendit Pending yang aktif (issue #76). Transaksi Xendit lama tetap tersimpan sebagai riwayat, tetapi tidak lagi dicek statusnya.

Callback iPaymu diverifikasi dua lapis: HMAC-SHA256 `X-Signature` (kunci = Nomor VA merchant, body mentah yang dinormalisasi lalu di-`ksort`), lalu job pembayaran mengonfirmasi `trx_id` ke `/api/v2/transaction` sebelum invoice dilunasi. Lapis kedua dipilih karena dokumentasi signature iPaymu tidak konsisten (header vs body, normalisasi tipe) dan jalur ini melunasi uang.

## Consequences

- Nominal dicocokkan dengan `sub_total`, bukan `total`, karena `total` sudah termasuk fee pembeli.
- Satu `trx_id` mengirim beberapa callback (pending, berhasil): event id = `{trx_id}-{status_code}` agar callback lunas tidak tertolak sebagai duplikat.
- `/transaction` hanya mengenal `trx_id`; sebelum callback pertama, cek status/rekonsiliasi mencari lewat `/history` berdasarkan `ReferenceId` (maks. 5 halaman).
- Hosted Invoice iPaymu tidak memberi QR yang bisa disimpan (QRIS kedaluwarsa 5 menit). Invoice PDF memuat QR **Tautan Tagihan** yang permanen.
- Production iPaymu mewajibkan IP server statis dan setiap domain brand (returnUrl/notifyUrl) terdaftar di dashboard iPaymu.
