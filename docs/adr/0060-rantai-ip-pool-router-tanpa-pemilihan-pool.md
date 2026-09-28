# ADR 0060: Rantai IP Pool per Router, Tanpa Pemilihan Pool oleh Staf

**Status**: Digantikan oleh ADR-0063.

## Konteks
NOC memilih IP Pool di Aktivasi Pemasangan, Proses NOC, dan form layanan. Padahal hubungan pool dengan router adalah urusan teknis di belakang layar, dan pilihan manual itu bisa salah pilih. Segmentasi Rumah/Bisnis per pool tidak dibutuhkan secara bisnis.

## Keputusan
1. Pool hanya "dipilih" di satu tempat: menu IP Pool, yaitu saat memilih Router pemiliknya. Tidak ada dropdown IP Pool di layanan, paket, profil, atau tiket.
2. Semua pool di satu router dirangkai lewat `next-pool` RouterOS menurut urutan pembuatan (pool terlama jadi kepala). Pool yang habis otomatis dilanjutkan ke pool berikutnya.
3. Satu Profile PPP `{nama_bandwidth}` per router membawa `remote-address` = kepala rantai dan `local-address` = gateway kepala rantai. IP Statis dan IP Publik Dedicated memakai profile yang sama, dengan alamat literal di secret yang menimpa alamat profile.
4. Layanan PPPoE dinamis ke router tanpa pool ditolak saat aktivasi, Proses NOC, dan Edit layanan.
5. Rename pool bebas, karena rantai dan profile disinkronkan ulang. Hapus pool ditolak hanya bila itu pool terakhir di router yang masih punya layanan PPPoE dinamis.
6. Rekonsiliasi memindahkan secret dari profile lama `{bandwidth}@{pool}` ke `{bandwidth}`. Profile lama tidak dihapus dari router.

## Alternatif yang ditolak
- **Pool per Profil Bandwidth (nama pool di profil)**: tetap membuat admin memilih pool, dan setiap router wajib memakai konvensi nama yang sama.
- **Satu pool per router**: memaksa membuat router baru hanya untuk menambah kapasitas alamat.
- **Pool dipilih dari Tipe Pelanggan atau sisa kapasitas**: butuh atribut atau logika tambahan untuk segmentasi yang tidak dibutuhkan.

## Konsekuensi
- Subnet pelanggan di satu router tidak lagi mencerminkan segmen Rumah/Bisnis.
- Bahwa secret menimpa alamat profile, dan bahwa `next-pool` berlaku lewat profile, harus diverifikasi di router staging sebelum rollout.
- Menghapus pool membuat sesi yang memegang IP dari pool itu mendapat IP baru saat reconnect.
