# Pelunasan Susulan mengakui pembayaran gateway untuk invoice Digabung dan Dibatalkan

Uang yang sudah PAID di gateway harus tercermin di sistem, termasuk bila pelanggan membayar link lama sebuah invoice yang sudah Digabung atau Dibatalkan. Kami memutuskan:

- **Invoice Digabung** yang dibayar akan dilunasi. Invoice itu lalu dilepas dari invoice penggabungnya, dan nominal penggabung yang masih terbuka dikurangi sebesar nominal tersebut. Link Pembayaran Gateway lama milik penggabung dimatikan dan diterbitkan ulang.
- **Invoice Dibatalkan** yang dibayar akan dipulihkan dan dilunasi, asalkan layanan belum punya invoice Lunas lain untuk Periode Tagihan yang sama.

Ini menggantikan aturan lama yang menolak melunasi invoice Digabung dari webhook dan menyerahkannya ke penanganan manual. Aturan lama mencegah perpanjangan ganda, tetapi meninggalkan pelanggan yang sudah membayar tetap tertagih atau terisolir.

Kasus yang tetap dilaporkan untuk ditangani manual (umumnya refund di gateway):

- invoice penggabung sudah Lunas, atau periode yang sama sudah Lunas lewat invoice lain (pembayaran ganda);
- invoice Dibatalkan yang dulunya menggabung tunggakan, karena memulihkan rantai penggabungan secara otomatis terlalu berisiko;
- nominal tidak sama persis (Validasi Ketat Nominal Gateway).

## Considered Options

- Hanya melaporkan Digabung/Dibatalkan untuk ditangani manual: lebih aman, tetapi selisih bisa menumpuk tanpa tertangani dan pelanggan yang sudah bayar tetap terisolir.
- Melunasi apa pun kondisinya: berisiko perpanjangan masa aktif ganda dan tagihan ganda.
