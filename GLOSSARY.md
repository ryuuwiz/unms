# UNMS

Sistem manajemen ISP: pelanggan, layanan, invoice, dan pembayaran.

## Pembayaran

**Koneksi Gateway**:
Satu akun penyedia payment gateway (mis. iPaymu) beserta kredensialnya.
_Avoid_: Pengaturan Gateway

**Hosted Invoice**:
Halaman bayar milik gateway tempat pelanggan memilih metode dan menyelesaikan pembayaran; sistem hanya menerbitkan tautannya.
_Avoid_: Payment link, checkout page, redirect payment

**Channel Pembayaran**:
Satu pilihan bayar konkret yang ditawarkan ke pelanggan di portal (mis. BCA VA, QRIS, Alfamart), milik satu Koneksi Gateway, dengan Fee Admin dan status ON/OFF sendiri.
_Avoid_: Metode Pembayaran (itu cara invoice dilunasi: manual, transfer, gateway)

**Tipe Channel**:
Kelompok Channel Pembayaran: QRIS, Virtual Account, gerai retail.
_Avoid_: Tipe Pembayaran

**Fee Admin**:
Biaya yang ditambahkan ke tagihan pelanggan saat membayar lewat suatu Channel Pembayaran — flat rupiah atau persen dari nominal tagihan. Nol berarti ISP menanggung biaya gateway.
_Avoid_: Biaya gateway, MDR
