# ADR 0059: Antrean Aksi Pelanggan Terpisah dari Rekonsiliasi, dan Secret Manual NOC Tidak Diambil Alih

**Status**: Accepted

## Konteks
NOC melaporkan PPP Secret pelanggan baru tidak masuk ke router, tanpa notifikasi, dan pemulihan terlalu lambat (target < 10 detik). Penyebabnya:
- Job per pelanggan (provisi, isolir, un-isolir, ganti profil, hapus secret) dan job router-wide (`RecoverPppRouterJob`, `ProvisionRouterJob`) memakai kunci `WithoutOverlapping` yang sama, `mikrotik-router-{id}`. Job router-wide memegangnya sampai menit-an. Job per pelanggan di-`release(5)` dan setiap release dihitung sebagai attempt, sehingga `tries = 3` habis dalam ~15 detik tanpa `handle()` pernah jalan.
- `MikrotikService` membungkus semua galat jadi `MikrotikException`, sehingga pemeriksaan "bukan galat koneksi = jangan retry" selalu benar: provisi tidak pernah di-retry saat router sesaat tidak terjangkau.
- Secret yang sudah ada di router dengan nama sama ditimpa billing kecuali komentarnya diawali `MANUAL:`/`NOC:`/`SYSTEM:`/`WHITELIST:`, padahal NOC membuat secret manual tanpa konvensi komentar.

## Keputusan
1. Job per pelanggan memakai kunci sendiri `mikrotik-layanan-router-{id}` (trait `AntreanLayananRouter`). Maksimal 2 sesi API bersamaan per router: satu rekonsiliasi + satu aksi pelanggan.
2. Batas percobaan memakai waktu (`retryUntil` 10 menit) dan `maxExceptions = 5`, backoff `[2, 5, 10, 30, 60]`. Galat permanen (dideteksi lewat `MikrotikException::bisaDicobaLagi()` yang menelusuri `getPrevious()`) langsung digagalkan.
3. Kepemilikan secret ditentukan komentar `UNMS:` saja. Secret tanpa `UNMS:` tidak pernah diubah, diisolir, dibuka, atau dihapus; provisi layanan bernama sama gagal dengan pesan jelas + Notifikasi NOC.

## Alternatif yang ditolak
- **Tetap satu kunci + retryUntil**: job tidak lagi gagal, tapi aksi pelanggan bisa menunggu menit-an di belakang rekonsiliasi; target 10 detik tidak tercapai.
- **Provisi sinkron di request web untuk semua aksi**: memblokir request dan melewati serialisasi per router; hanya dipakai di Aktivasi Pemasangan (dengan job sebagai cadangan).
- **Tetap mengambil alih secret bernama sama**: berisiko menimpa kredensial pelanggan yang dikelola NOC langsung di router.

## Konsekuensi
- Beban router sedikit naik saat rekonsiliasi berjalan bersamaan dengan aksi pelanggan.
- Secret lama tanpa komentar `UNMS:` yang seharusnya milik billing (mis. hasil migrasi) harus diberi komentar `UNMS:` oleh NOC sebelum bisa dikelola sistem; kegagalannya terlihat di lonceng dan WhatsApp.
