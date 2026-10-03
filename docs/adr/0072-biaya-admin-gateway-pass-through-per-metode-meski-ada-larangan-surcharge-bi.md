# ADR 0072: Biaya Admin Gateway Pass-Through per Metode Bayar meski Ada Larangan Surcharge BI

**Status**: Accepted. Menggantikan bagian "Standar Biaya Admin Berdasarkan Riset Industri" di ADR-0026.

Biaya admin gateway tetap dibebankan ke pelanggan, sebesar biaya yang benar-benar ditagih gateway untuk metode bayar yang dipakai. Biaya itu termasuk PPN 11% dan biaya pemrosesan per transaksi, lalu dibulatkan ke atas ke rupiah penuh. Akibatnya Virtual Account dan QRIS punya total yang berbeda. Keputusan ini diambil dengan sadar, **walaupun PBI No. 23/6/PBI/2021 Pasal 52 ayat (1) melarang merchant mengenakan surcharge atas biaya PJP kepada pengguna jasa**. Larangan itu ditegaskan lagi di PBI 10/2025, dan dokumentasi Xendit menyebut fee ke pelanggan "not allowed" di Indonesia. Risiko kepatuhan ini ditanggung ISP. Riset dan sumbernya ada di `docs/research/payment-gateway-fee-xendit-ipaymu.md`.

Fee flat Rp 4.000 di ADR-0026 bermasalah dalam dua hal. Pertama, fee itu ditempelkan ke satu link Xendit sebelum pelanggan memilih metode, sehingga pembayar QRIS juga ditagih Rp 4.000. Kedua, angkanya tertinggal dari tarif Xendit: VA Rp 9.000 sejak Agustus 2026, ditambah biaya pemrosesan Rp 4.000 sejak 1 Okt 2026, belum termasuk PPN.

## Considered Options

- **ISP menanggung biaya dan memasukkannya ke harga paket.** Ini satu-satunya opsi yang patuh. Ditolak oleh pemilik bisnis.
- **Fee flat yang sama untuk semua metode.** Ditolak karena pembayar QRIS jadi mensubsidi VA, dan substansinya tetap surcharge.

## Consequences

- Tarif per metode untuk Xendit diisi manual karena Xendit tidak punya API harga. Biaya asli dibaca dari Transactions API setelah transaksi lunas, dan staf diberi tahu bila tarifnya berbeda dari pengaturan. Untuk iPaymu, tarif dihitung oleh checkout iPaymu (`feeDirection=BUYER`).
- MDR QRIS 0% (BI, ≤ Rp 100.000, sejak 1 Okt 2026) baru dimasukkan ke hitungan setelah terlihat diterapkan di transaksi asli.
- Biaya pemrosesan untuk percobaan bayar yang gagal tetap ditanggung ISP.
- Bila BI atau gateway menegakkan larangan ini, jalan keluarnya adalah mematikan `bebankan_ke_pelanggan` dan menyesuaikan harga paket.
