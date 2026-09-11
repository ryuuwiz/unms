# ADR 0035: Konsistensi Batas Laju Pengiriman & Jeda Antar-Pesan, dan Konsolidasi Jalur Pengiriman WhatsApp

## Konteks
`KirimWaBlastJob` menegakkan dua mekanisme pembatasan pengiriman WhatsApp per Koneksi Gateway (`Sysblas`) secara berurutan: **Jeda Antar-Pesan** (`delay_detik` + `jitter_detik`, dicek lebih dulu, blocking) dan **Batas Laju Pengiriman** (`limit_per_menit`, dicek kedua). Riwayat migrasi mengubah default `delay_detik` dari 3 detik menjadi 300 detik tanpa menyesuaikan `limit_per_menit` (tetap 25), sehingga kedua parameter tidak lagi merepresentasikan satu target throughput yang sama — `delay_detik = 300` membatasi throughput ke 1 pesan/5 menit, jauh di bawah apa pun nilai `limit_per_menit`, membuat Batas Laju Pengiriman efektif tidak pernah tersentuh.

Terpisah dari itu, command terjadwal `wa:proses-antrian` (`ProsesAntrianWaCommand`) memiliki jalur pengiriman langsungnya sendiri (`WhatsappClient::sendBatchMessages()`) yang tidak melewati `RateLimiter`/Cache slot milik `KirimWaBlastJob` — jika keduanya berjalan bersamaan pada gateway yang sama, total pengiriman gabungan bisa melampaui batas yang dikonfigurasi admin.

Target operasional saat ini: gateway WhatsApp self-hosted (WAHA/GOWA, berbasis protokol WhatsApp Web) perlu dijaga di kisaran throughput aman ~4 pesan/menit per koneksi untuk mengurangi risiko nomor diblokir (anti-ban), bukan sekadar quota API.

## Keputusan yang Diambil

1. **Kedua parameter pacing harus dikonfigurasi sebagai satu pasangan yang konsisten**, bukan dua angka independen. Default baru: `limit_per_menit = 4` dan `delay_detik = 15` (≈ `60 / limit_per_menit`), dengan `jitter_detik` tetap sebagai variasi acak kecil di atasnya. Perubahan pada salah satu kolom di masa depan harus mempertimbangkan efeknya pada kolom pasangannya.
2. **Batas atas validasi `limit_per_menit` diperketat dari `max:300` menjadi `max:20`** pada form Livewire `Koneksi/Index`, sebagai pengaman keras terhadap risiko ban — bukan sekadar batas UX.
3. **Backfill data existing bersifat konservatif**: hanya baris `sysblas` yang masih memakai nilai default lama (belum pernah diubah admin) yang diperbarui ke pasangan default baru; baris yang sudah dikustomisasi admin ke nilai lain dibiarkan, dianggap keputusan sadar operasional.
4. **Satu-satunya jalur pengiriman nyata adalah `KirimWaBlastJob`.** `ProsesAntrianWaCommand` direfactor menjadi murni Penyapu Antrean Macet: hanya men-dispatch ulang baris antrian blast berstatus `Menunggu` ke `KirimWaBlastJob`, tidak lagi memanggil `sendBatchMessages()` secara langsung. Ini menghilangkan jalur kirim paralel yang tidak menghormati Batas Laju Pengiriman/Jeda Antar-Pesan.

## Konsekuensi
- Throughput WhatsApp per Koneksi Gateway kini benar-benar dapat diprediksi dari satu nilai (`limit_per_menit`), bukan tersembunyi di balik `delay_detik` yang tidak sinkron.
- Siapa pun yang menaikkan `limit_per_menit` di masa depan tanpa menurunkan `delay_detik` yang sepadan tidak akan melihat efek nyata — perilaku ini disengaja dan didokumentasikan di sini, bukan bug.
- `wa:proses-antrian` tidak lagi memiliki jalur eksekusi independen; semua rate-limiting hanya perlu diuji di satu tempat (`KirimWaBlastJob`).
