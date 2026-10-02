# ADR 0063: Router Paket, Profile PPP per Paket, Isolir lewat Profile, Kepemilikan Objek Router dari Nama

**Status**: Accepted — poin (2) profile `ISOLIR` dari IP Pool Isolir pilihan admin digantikan ADR-0071 (profile `EXPIRED` dan pool isolir dibuat billing). Menggantikan ADR-0060 (rantai pool, profile per bandwidth), ADR-0050 (paket per router tidak divalidasi), bagian kepemilikan-dari-komentar ADR-0059, dan pembersihan orphan ADR-0053.

Billing hanya mengelola data dan membuat PPP Profile/Secret lewat API; alokasi IP sepenuhnya urusan MikroTik. Kami memutuskan: (1) **Router Paket** — pasangan paket–router dengan satu IP Pool pilihan — menjadi daftar resmi router yang boleh dipilih NOC untuk sebuah paket, dan setiap Router Paket membuat satu profile bernama paket (`local-address` = `.1` pool, `remote-address` = pool); rantai `next-pool` dihapus. (2) **Isolir** memindahkan secret ke profile `ISOLIR` (dibuat billing dari IP Pool Isolir router) dan memutus sesi, bukan men-*disable* secret. (3) **Berhenti** hanya lewat tiket Pencabutan buatan Admin; NOC menghapus secret sebelum tiket bisa Selesai; data pelanggan tidak dihapus. (4) Billing **tidak memberi komentar** pada objek di router; kepemilikan ditentukan nama yang terdaftar di billing.

## Considered Options

- **Tetap satu profile per Profil Bandwidth + rantai pool (ADR-0060)** — ditolak: admin perlu menentukan pool per paket per router, dan profile tanpa alamat bisa terbentuk diam-diam saat router belum punya pool.
- **Kepemilikan dari komentar `UNMS:` (ADR-0059)** — ditolak karena konfigurasi router diminta tanpa komentar. Akibatnya orphan tidak bisa dibedakan dari secret manual NOC, sehingga pembersihan orphan dihapus.
- **Billing membuat firewall redirect isolir** — ditolak: konfigurasi firewall berbeda per router dan berisiko; tetap manual NOC.

## Consequences

- Profile lama (`{bandwidth}` dan `{bandwidth}@{pool}`) dibereskan rekonsiliasi: secret dipindah ke profile nama paket, profile lama yang namanya cocok dengan data billing dan tidak dipakai secret mana pun dihapus. Komentar `UNMS:` lama dikosongkan.
- Secret manual NOC yang kebetulan bernama sama dengan username PPP billing akan dikelola billing (format `{NoReg}_{5 digit}` membuat bentrok hampir mustahil).
- Invoice terbuka milik layanan Berhenti tetap tercatat sebagai tunggakan tanpa pengingat/link bayar.
