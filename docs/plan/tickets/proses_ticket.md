Button proses yang muncul di tickets harus sesuai dengan scope role divisi dan permission divisi.

# Divisi NOC 
klik Proses NOC di tickets/view/{id}
Proses Ticket {id_tiket} {divisi_noc}
- Jenis Ticket
- Status Ticket: On Progress, Selesai, Cancel
- Mode Registrasi Mikrotik: Proses Registrasi Mikrotik (Sistem akan mengirim perintah ke router (PPPoE) dan baru menyimpan histori jika berhasil), Sudah Registrasi Mikrotik (PPP sudah dibuat manual / sebelumnya. Sistem hanya update histori ticket).
- Pilihan paket: Paket Bawaan (Gunakan paket layanan saat ini dan hanya router yang memiliki paket ini), Paket Berbeda (Pilih paket lain yang tersedia pada router yang dipilih).
- Router (NOC): Pilih router tempat PPP / IP statis akan dibuat.
- Paket di Router: Mode Paket Bawaan: hanya paket bawaan layanan dan router yang memiliki paket tersebut yang dapat dipilih. Mode Paket Berbeda: dapat memilih paket lain yang tersedia pada router.
- PPP Username & Password: Generate otomatis dari No. Reg (PPP saat ini: (tidak diubah jika mode auto)), Isi manual (username wajib unik). (Mode otomatis: jika username PPP layanan sudah ada, sistem tidak akan generate ulang.
Mode manual: username wajib unik, password boleh dikosongkan (akan di-generate).)
- Catatan Proses *: (contoh: PPP sudah dibuat di router R1, ODP 02-03, pelanggan sudah konfirmasi aktif). Catatan ini akan tersimpan di histori proses ticket.

# Divisi ADMIN 
klik Proses ADMIN di tickets/view/{id}
Proses Ticket {id_tiket} {divisi_admin}
- Jenis Ticket
- Status Ticket
- Aksi Admin: Ubah Paket Layanan 
(Paket hanya tersedia di router aktif, silahkan hub NOC jika ingin mengubah Paket tertentu.
Jika dipilih, paket layanan akan diubah saat proses ini disimpan.
Pengaturan harga (auto/manual) tetap mengikuti konfigurasi layanan.)
- Catatan Proses *
Catatan ini akan tersimpan di histori proses ticket.

# Atur Teknisi Ticket {id_tickets}
Pilih Teknisi yang Menangani
Klik kartu teknisi untuk memilih / menghapus pilihan. 
(kartu foto dan nama teknisi)
• Biarkan semua tidak terpilih jika ingin menghapus semua teknisi (unassign).
• Hanya user dengan divisi teknisi yang muncul di daftar ini.

# Divisi Customer Service 
klik Proses Customer Service di tickets/view/{id}
Proses Ticket {id_tiket} {divisi_customer_service}
- Jenis Ticket
- Status Ticket
- Aksi Customer Service
CS dapat menambahkan catatan komunikasi dengan pelanggan pada kolom Catatan Proses di bawah.
Perubahan teknis (router, paket, dsb.) hanya dapat dilakukan oleh divisi terkait.
- Catatan Proses *
Catatan ini akan tersimpan di histori proses ticket.