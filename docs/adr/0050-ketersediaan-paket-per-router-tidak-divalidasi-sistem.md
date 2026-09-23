# ADR 0050: Ketersediaan Paket Layanan per Router Tidak Divalidasi Sistem

## Konteks
Modal "Proses NOC" (lihat CONTEXT.md "Proses Divisi (NOC/Admin/Customer Service)") menambahkan opsi "Paket Berbeda" yang membiarkan NOC memilih Paket Layanan lain untuk suatu Data Registrasi Billing sambil menentukan Router-nya sendiri. Sebuah Paket Layanan (kombinasi Profil Bandwidth) secara teori bisa saja tidak benar-benar dikonfigurasi di RouterOS pada router yang dipilih (mis. profil PPP untuk paket itu belum pernah disinkronkan ke router X karena paket itu memang dijual khusus di router Y).

Dua pendekatan sempat dipertimbangkan untuk mencegah kombinasi Router+Paket yang salah dipilih NOC:
- (a) Menurunkan paket yang "tersedia" dari riwayat Layanan Pelanggan yang sudah pernah aktif di router itu.
- (c) Tabel pivot baru yang dikelola admin untuk mendaftarkan kombinasi Router↔Paket yang sah.

## Keputusan yang Diambil
Tidak ada validasi sistem apa pun. Modal Proses NOC menampilkan seluruh Paket Layanan aktif (`PaketLayanan::aktif()`) tanpa filter per-router, sama seperti dropdown Router yang menampilkan seluruh router online tanpa filter per-paket. Kesesuaian kombinasi Router+Paket sepenuhnya menjadi tanggung jawab manual NOC.

Alasan: ADR-0018 sudah menjamin bahwa Profil Bandwidth disinkronkan ke SEMUA router (pipeline provisi berjalan per-router untuk seluruh profil di database, bukan per-kombinasi), jadi asumsi "paket X tidak ada di router Y" secara teknis jarang benar-benar terjadi pada kondisi steady-state — risiko nyatanya kecil dibanding kerumitan membangun dan merawat sumber kebenaran Router↔Paket baru (opsi a butuh riwayat yang belum tentu representatif untuk paket baru; opsi c butuh UI admin, migrasi, dan constraint baru untuk masalah yang saat ini belum pernah dilaporkan terjadi).

## Konsekuensi
- Tidak ada perubahan skema database untuk fitur ini (tidak ada pivot Router↔Paket baru).
- NOC bisa secara teori memilih kombinasi yang salah tanpa peringatan sistem; mitigasinya murni operasional (SOP internal NOC), bukan teknis.
- ponytail: jika kombinasi salah pilih terbukti jadi masalah nyata di lapangan (tiket berulang karena profil belum tersinkron ke router tertentu), revisit dengan opsi (c) — tabel pivot eksplisit yang dikelola admin — karena lebih auditable daripada opsi (a) yang bergantung riwayat historis.
