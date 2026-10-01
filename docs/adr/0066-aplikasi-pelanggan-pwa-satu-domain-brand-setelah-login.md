# ADR 0066: Aplikasi Pelanggan adalah Portal Ber-brand yang Dipasang sebagai PWA di Satu Domain, Brand Ditentukan Setelah Login

Pelanggan setiap merek (mis. `BEST` = BESTFIBER, `WIFI` = WIFIGO, `MIIX` = MyArsyila) mendapat "aplikasi" dengan nama dan ikon mereknya sendiri. Kami memutuskan bahwa aplikasi itu adalah **Portal Pelanggan yang sudah ada, dipasang sebagai PWA**, bukan aplikasi native atau aplikasi terpisah. Unit whitelabel-nya adalah **Brand Pelanggan** (ADR-0061), bukan tenant. **Semua brand berbagi satu domain Portal** (ADR-0049). Karena brand diturunkan dari No. Registrasi, brand baru diketahui setelah login. Karena itu, *manifest* PWA dibuat dinamis dari brand pelanggan yang sedang login, dan tombol pemasangan baru ditawarkan setelah login. Halaman sebelum login memakai Petunjuk Brand (brand terakhir yang login di perangkat itu) dan jatuh ke brand Perusahaan bila tidak ada.

ADR-0061 diperluas: Brand Pelanggan kini berisi nama, nama pendek, logo, ikon aplikasi, dan warna utama. Profil Perusahaan memiliki atribut yang sama karena menjadi brand cadangan. Identitas legal, rekening, dan kontak tetap satu milik Perusahaan, termasuk kontak CS yang dipakai tombol Kontak Dukungan.

## Considered Options

- **Domain per brand** (`app.bestfiber.id`, `app.wifigo.id`): brand diketahui sebelum login, *manifest* statis per domain, dan nama GOBILLING tidak pernah terlihat. Opsi ini ditolak demi satu domain, satu sertifikat, dan tanpa pemetaan host ke brand. Akibatnya, domain generik terlihat di URL.
- **Path per brand** (`/best`, `/wifi`): opsi ini ditolak dengan alasan yang sama dengan domain per brand.
- **Aplikasi native per brand atau satu aplikasi native generik**: opsi ini ditolak karena membutuhkan lapisan API dan listing app store per merek, dan bertentangan dengan arsitektur monolit ADR-0007.
- **Manifest statis milik Perusahaan**: opsi ini ditolak karena nama dan ikon merek tidak akan pernah sampai ke layar utama pelanggan.

## Consequences

- Satu ponsel hanya bisa memasang satu Aplikasi Pelanggan. Jika dua pelanggan dari merek berbeda memakai ponsel yang sama, ikon di layar utama tetap ikon merek pertama yang dipasang.
- Aplikasi yang sudah terpasang tidak langsung mengikuti perubahan No. Registrasi atau atribut brand. Ikon dan nama baru menunggu browser memperbarui *manifest*.
- Pindah ke domain per brand di kemudian hari akan memutus aplikasi yang sudah terpasang, karena PWA terikat ke origin-nya. Pelanggan harus memasang ulang dari domain baru.
- Petunjuk Brand hanya menentukan tampilan halaman sebelum login. Setelah login, brand selalu diturunkan ulang dari No. Registrasi.
- Halaman Tagihan Mandiri (tautan bertanda tangan) mengambil brand dari invoice-nya, bukan dari Petunjuk Brand.
