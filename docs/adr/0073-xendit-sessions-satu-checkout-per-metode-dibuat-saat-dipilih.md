# ADR 0073: Xendit lewat Payment Sessions, Satu Checkout per Metode, Dibuat saat Dipilih

**Status**: Accepted. Menggantikan pemakaian `POST /v2/invoices` di ADR-0007/0026.

Driver Xendit pindah dari `POST /v2/invoices` ke `POST /sessions` (`mode=PAYMENT_LINK`). Ada dua alasan:
- Sejak 1 Okt 2026 Xendit menggolongkan `/v2/invoices` sebagai legacy API dan mengenakan Monthly Maintenance Fee USD 250.
- Halaman checkout Xendit hanya punya satu nominal untuk semua metode, sedangkan ADR-0072 menuntut total yang berbeda per metode.

Karena itu, setiap Metode Bayar Gateway (Virtual Account, QRIS, GoPay, ShopeePay) mendapat session sendiri yang dibatasi lewat `allowed_payment_channels`, dengan nominal tagihan ditambah biaya metode itu. Pelanggan memilih metode lewat tombol di Halaman Tagihan atau Portal, dan pembayarannya tetap di halaman checkout Xendit. Session dibuat saat tombol ditekan, bukan saat invoice terbit, lalu dipakai ulang selama masih aktif. Alasannya, Xendit mengenakan biaya pemrosesan "per attempt" dan tidak menjelaskan apakah session yang tidak dibayar ikut dihitung.

iPaymu tetap memakai satu link redirect, karena checkout iPaymu sudah menghitung biaya per kanal sendiri. Tombol yang tampil mengikuti driver koneksi default.

## Considered Options

- **Satu session untuk semua metode dengan fee termahal.** Ditolak karena melanggar pass-through persis (ADR-0072).
- **Session untuk semua metode dibuat saat invoice terbit.** Ditolak karena berisiko kena biaya pemrosesan untuk link yang tidak pernah dipakai, dan session tidak bisa diperpanjang.
- **Tetap memakai `/v2/invoices`.** Ditolak karena biaya maintenance legacy dan karena tidak bisa membatasi nominal per metode.

## Consequences

- Link `/v2/invoices` yang sudah terbit tidak diterbitkan ulang. Webhook dan cek status format lama tetap didukung sampai semuanya lunas atau kedaluwarsa.
- Satu invoice bisa punya beberapa Transaksi Payment Gateway aktif (satu per metode). Validasi Ketat Nominal Gateway membandingkan dengan total milik transaksi yang dibayar.
- Setelah go-live, perlu dicek sekali lewat Billing report apakah session yang dibiarkan kedaluwarsa dikenai biaya pemrosesan.
