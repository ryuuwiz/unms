# ADR 0067: Transaksi Terikat Koneksi Penerbit, Kedaluwarsa dari Gateway, Tautan Tagihan Tanpa Masa Berlaku, dan Webhook WhatsApp Wajib Bertanda Tangan

Callback Xendit di production terus ditolak dengan "token tidak valid". Ada dua Koneksi Xendit (live dan sandbox), dan sistem memverifikasi token dengan baris Xendit *pertama*, bukan dengan koneksi yang menerbitkan tagihannya. Akibatnya beruntun:

- Callback ditolak.
- `xendit:cek-va-expired` menandai transaksi Kedaluwarsa hanya karena waktu lokal sudah lewat.
- Rekonsiliasi hanya memeriksa transaksi Pending, sehingga pembayaran yang sebenarnya lunas tidak pernah dipulihkan.

Di saat yang sama, tautan tagihan di WhatsApp (signed URL yang kedaluwarsa jatuh tempo + 3 hari) sudah mati saat pengingat tunggakan dikirim. Endpoint `/webhook/whatsapp` juga menerima payload tanpa autentikasi yang bisa menulis Histori Tiket dan status antrean WA.

Kami memutuskan:

1. **Transaksi terikat Koneksi penerbitnya.** Transaksi Payment Gateway mencatat Koneksi Payment Gateway yang menerbitkannya. Callback dan cek status untuk transaksi itu selalu memakai token dan API key koneksi tersebut, meskipun koneksi default sudah berganti. Koneksi aktif dan default hanya menjadi cadangan untuk callback yang transaksinya tidak dikenal, atau transaksi lama yang belum mencatat koneksinya.
2. **Sandbox tidak melunasi invoice di production.** Uang mode test bukan uang sungguhan. Di luar production, sandbox tetap boleh melunasi agar alur pembayaran bisa diuji ujung ke ujung.
3. **Kedaluwarsa berasal dari gateway.** Transaksi hanya menjadi Kedaluwarsa bila gateway menyatakannya. Pengecekan berkala bertanya ke gateway lebih dulu, dan `pembayaran:pulihkan` memindai ulang transaksi Kedaluwarsa atau Pending untuk melunasi yang ternyata sudah dibayar.
4. **Hanya satu URL callback:** `/webhook/payment/{gateway}`. Rute lama `/webhook/xendit` dan verifier terpisahnya dihapus. Payload callback yang ditolak tidak disimpan, karena isinya tidak bisa dipercaya. Pemulihan dilakukan dengan bertanya langsung ke API gateway.
5. **Tautan Tagihan tanpa masa berlaku.** Notifikasi WhatsApp memakai `/t/{token}`: token acak 16 karakter per invoice yang dibuat saat pertama kali dibutuhkan, dan bisa diganti admin untuk mencabutnya. Signed URL lama tetap diterima selama tanda tangannya asli, berapa pun tanggal kedaluwarsanya, sehingga tautan yang sudah terkirim kembali berfungsi. Pengalihan setelah membayar di gateway juga memakai Tautan Tagihan.
6. **Webhook WhatsApp wajib bertanda tangan.** `/webhook/whatsapp` hanya menerima event dari koneksi GOWA terdaftar yang memiliki secret dan signature HMAC yang valid. Payload WAHA, format flat legacy, dan koneksi GOWA tanpa secret ditolak dengan 401. Secret tidak dibuat otomatis, agar penyiapan GOWA tetap sederhana.

## Considered Options

- **Verifikasi token terhadap semua koneksi Xendit aktif:** ditolak. Token sandbox jadi bisa melunasi transaksi live.
- **Selalu memakai koneksi default:** ditolak. Callback untuk transaksi yang terbit sebelum default berganti akan ditolak.
- **Hanya token acak, tanpa menyelamatkan signed URL lama:** ditolak. Tautan yang sudah terkirim ke pelanggan akan tetap 403.
- **Tetap menerima koneksi GOWA tanpa secret:** ditolak. Celah pemalsuan webhook tetap terbuka.

## Consequences

- Siapa pun yang memegang Tautan Tagihan (termasuk signed URL lama) dapat melihat dan membayar invoice itu selamanya. Satu-satunya cara mencabutnya adalah mengganti token. Signed URL lama hanya bisa dicabut dengan merotasi `APP_KEY`.
- URL callback di dashboard Xendit, untuk mode Live maupun Test, harus diarahkan ke `/webhook/payment/xendit`. Selama belum diganti, callback akan 404, dan pembayaran baru terlunasi oleh rekonsiliasi polling.
- Koneksi GOWA tanpa secret tidak lagi menerima ACK pengiriman maupun pesan masuk. Status kirim hanya berasal dari respons API saat pengiriman.
- "Kode Pembayaran" di struk gerai retail tetap dibuat oleh Xendit, karena Hosted Invoice tidak mengizinkan kode kustom. Yang bisa kami kendalikan hanya Nama Konsumen, yang diisi No. Registrasi.
