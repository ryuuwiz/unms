# UNMS

Sistem manajemen ISP: pelanggan, layanan, invoice, dan pembayaran.

## Pembayaran

**Koneksi Gateway**:
Satu akun penyedia payment gateway (Xendit, iPaymu, …) beserta kredensialnya.
_Avoid_: Pengaturan Gateway

**Hosted Invoice**:
Halaman bayar milik gateway tempat pelanggan memilih metode dan menyelesaikan pembayaran; sistem hanya menerbitkan tautannya.
_Avoid_: Payment link, checkout page, redirect payment
