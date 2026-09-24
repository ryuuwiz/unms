# ADR 0052: IP Publik Dedicated sebagai Add-on Berbayar

**Status**: Accepted

## Konteks
Pelanggan bisnis membutuhkan IP publik dedicated. `ip_static` sudah ada tetapi berisi alamat privat tanpa inventaris dan tidak dapat ditagih terpisah.

## Keputusan
- Tabel inventaris `ip_publik` (router, `alamat_ip` unik, `gateway`, `harga_bulanan`, `harga_ditagih`, `layanan_pelanggan_id`). Status tersedia/terpakai diturunkan dari `layanan_pelanggan_id`, tanpa kolom status.
- Add-on pada layanan PPPoE, bukan `JenisKoneksi` baru dan bukan atribut paket. Tahap pertama maksimal satu IP per layanan (skema mendukung banyak).
- Secret memakai `remote-address` = IP publik dan `local-address` = gateway inventaris, dengan profile polos. Penetapan/pelepasan memutus sesi aktif.
- Penagihan: `harga_ditagih` disalin saat penetapan. `generateInvoice()` menagih `hargaDasar() + hargaTambahan()`, rincian di `Invoice.keterangan` (belum ada tabel item), diskon promo hanya atas harga paket, tanpa prorata otomatis (biaya pasang/prorata lewat invoice manual yang sudah ada).
- `Suspend` tetap memegang IP dan tetap ditagih; `Berhenti`/dihapus melepasnya.

## Konsekuensi
- NAT/firewall/routing publik di luar PPP tidak dikelola UNMS.
- Blok publik yang di-route (`routes` pada secret) tidak didukung pada tahap ini.
- Invoice yang menyerap tunggakan hanya menampilkan total tunggakan, tanpa rincian komponen.
