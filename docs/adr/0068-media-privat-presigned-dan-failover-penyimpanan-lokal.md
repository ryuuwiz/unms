# Media Privat lewat URL presigned, dan Failover Penyimpanan ke disk lokal

Bucket S3 media public-read untuk seluruh isinya (ADR-0038) dengan path `{id_media}/{nama_file}` yang berurutan, sehingga foto tiket (rumah pelanggan, tanda tangan MOU), foto profil staf, dan berkas umum bisa dienumerasi siapa pun. Kami memisahkan **Media Publik** (logo Perusahaan, logo Prefix Registrasi, ikon aplikasi: URL permanen di prefix publik bucket) dari **Media Privat** (semua lainnya: objek privat, ditampilkan lewat URL presigned berlaku 60 menit). Path semua media diacak menjadi `public|private/{uuid}/` (tanpa ID berurutan; nama file asli dipertahankan karena dipakai staf untuk mencari berkas, dan tidak bisa ditebak tanpa UUID), dan media lama dipindah sekali ke path baru sehingga URL lama mati. Policy public-read ADR-0038 dipersempit ke prefix publik saja.

Bersamaan dengan itu, upload tidak boleh gagal hanya karena S3 tidak terjangkau: setiap upload mencoba S3 dengan timeout koneksi singkat; bila gagal, file disimpan di disk lokal (Media Publik ke `public`, Media Privat ke `local` yang tidak diekspos web dan dilayani lewat temporary URL bertanda tangan), status "S3 mati" di-cache beberapa menit agar upload berikutnya tidak menunggu timeout, dan job terjadwal memindahkan media lokal ke S3 begitu S3 sehat. Bergantung pada volume persisten `storage/app` (ADR-0067), jadi tetap single replica.

## Considered Options

- **Path acak saja, bucket tetap publik**: ditolak; URL yang bocor tetap hidup selamanya, hanya menyembunyikan.
- **Enkripsi isi file seperti KTP (ADR-0024)**: ditolak untuk foto operasional; setiap gambar harus diproksi dan didekripsi PHP.
- **Health check terjadwal yang mengganti disk global**: ditolak; bereaksi terlambat dan memakai state global yang bisa basi, sedangkan kegagalan upload nyata adalah sinyal yang lebih tepat.

## Consequences

- File yang sudah ada di S3 tidak punya salinan lokal: selama S3 mati, media lama tetap tidak tampil; failover hanya untuk upload baru.
- Membuka gambar privat di tab baru setelah 60 menit butuh refresh halaman.
- KTP dan dokumen Pelanggan (`CUSTOMER_DOCUMENTS_DISK`) di luar cakupan; menunggu keputusan bucket privat terpisah.
