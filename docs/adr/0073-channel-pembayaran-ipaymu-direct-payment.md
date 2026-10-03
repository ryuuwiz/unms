# ADR 0073: Channel Pembayaran Dipilih di Portal lewat iPaymu Direct Payment

**Status**: Accepted — menggantikan bagian ADR-0072 yang menjadikan Hosted Invoice satu-satunya alur.

Pelanggan memilih Channel Pembayaran (BCA VA, QRIS, Alfamart, …) di halaman tagihan. Sistem memanggil iPaymu Direct Payment (`/api/v2/payment/direct` dengan `paymentMethod` + `paymentChannel`) lalu menampilkan nomor VA, kode bayar, atau QR di portal. Fee Admin per channel ditambahkan ke nominal dan dikirim dengan `feeDirection: MERCHANT`, jadi pelanggan hanya melihat satu fee. Alasannya: Hosted Invoice iPaymu hanya bisa dikunci per metode (`va`), bukan per bank, sehingga fee per channel dan status ON/OFF per bank tidak bisa diterapkan.

## Consequences

- Tanpa channel ON, portal tetap memakai Hosted Invoice iPaymu (ADR-0072), dengan fee mengikuti `bebankan_ke_pelanggan`. Tabel channel dimulai kosong.
- QRIS iPaymu kedaluwarsa 5 menit; portal menampilkan hitung mundur dan membuat QR baru saat diminta. Nama field QR di respons Direct belum terdokumentasi (`QrString`, lalu `PaymentNo`); `Url` iPaymu disimpan sebagai cadangan.
- Ganti channel membuat transaksi baru; transaksi lama dibiarkan kedaluwarsa, pembayaran ganda ditangani pelunasan susulan (ADR-0069).
- Kode channel divalidasi terhadap daftar `paymentChannel` iPaymu di driver. Form admin bisa mengambil kode, logo, dan fee dari `GET /api/v2/payment-channels`.
- Hanya tipe `va`, `qris`, `cstore`; e-wallet, kartu kredit, dan paylater butuh alur redirect berbeda.
