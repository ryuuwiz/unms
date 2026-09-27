# ADR 0061: Brand Pelanggan Diturunkan dari Prefix Registrasi, Identitas Legal Tetap Satu Perusahaan

Setiap Prefix Registrasi mewakili merek ISP (mis. `BF` = BESTFIBER, `ARS` = Arsyila), dan pelanggan harus melihat mereknya sendiri di pesan WhatsApp, invoice PDF, dan deskripsi payment gateway. Kami memutuskan **Brand Pelanggan hanya berisi nama dan logo** (disimpan pada Prefix Registrasi), ditentukan dari huruf awal `no_reg` di satu tempat (`PengaturanPrefixRegistrasi::untukNoReg()`), termasuk prefix yang sudah nonaktif; `no_reg` tanpa prefix yang cocok jatuh ke brand Perusahaan. Nama PT, alamat, kontak, rekening, dan NPWP tetap satu milik Perusahaan karena semua merek berada di bawah satu PT dan satu rekening, dan semua merek dikirim dari satu Koneksi Gateway WhatsApp.

## Considered Options

- **Identitas penuh per brand** (kontak, rekening, NPWP, penandatangan per brand) — ditolak: semua merek satu PT dan satu rekening, jadi hanya menduplikasi Profil Perusahaan. Bila kelak sebuah merek menjadi PT terpisah, keputusan ini harus dibalik (brand menjadi Perusahaan sendiri, lihat ADR-0010).
- **Template WhatsApp per brand** — ditolak: melipatgandakan setiap template per brand tanpa kebutuhan isi yang berbeda.
- **Placeholder WhatsApp baru `{brand}`** — ditolak: `{nama_brand}` diubah artinya menjadi Brand Pelanggan agar template yang sudah diedit admin langsung ikut tanpa disunting ulang.

## Consequences

- `{nama_brand}` di Template Pesan WhatsApp kini bukan lagi brand Perusahaan; `{brand}` di Template Deskripsi Tagihan Gateway bernilai sama.
- Menonaktifkan prefix tidak mengubah brand pelanggan lamanya.
