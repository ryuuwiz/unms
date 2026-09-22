# ADR 0042: Prefix No. Registrasi Pelanggan Dikelola Dinamis via Tabel, Bukan Hardcode

## Konteks
Sebelumnya prefix No. Registrasi pelanggan (`BF`, `ARS`, dst.) hanya berupa string hardcode `'BF'` sebagai default parameter `Pelanggan::generateNoReg()`, dan pengguna hanya bisa memilih prefix lain dengan mengetik seluruh `no_reg` secara manual (bebas, tanpa validasi format). Tidak ada daftar resmi prefix yang bisa dikelola staff, dan tidak ada UI untuk memilihnya. Kebutuhan bisnis: staff perlu memilih prefix dari daftar resmi (mewakili brand/unit bisnis, misal `BF` = Bestfiber, `ARS` = Arsyila) lewat dropdown saat mendaftarkan pelanggan baru, dan daftar itu perlu bisa bertambah tanpa deploy kode.

## Keputusan yang Diambil

1. **Tabel `pengaturan_prefix_registrasi`** (`kode`, `nama`, `is_active`) menggantikan hardcode string sebagai sumber daftar prefix, dikelola lewat halaman admin (`App\Livewire\Settings\PengaturanPrefixRegistrasi`, permission dedicated `prefix_registrasi.*`). Alternatif yang ditolak: enum PHP (seperti `TipePelanggan`) — ditolak karena kebutuhannya eksplisit dinamis (staff nonteknis harus bisa menambah prefix tanpa deploy).
2. **Nonaktifkan, bukan hapus** (`is_active` boolean): prefix yang sudah pernah dipakai tidak boleh dihapus permanen dari daftar pilihan agar riwayat tetap konsisten secara UX, meskipun `no_reg` yang sudah terbit tidak punya FK ke tabel ini (murni string).
3. **Dropdown berdampingan dengan field manual**: field teks `no_reg` bebas tetap ada (custom penuh tetap didukung, sesuai kontrak lama), dropdown prefix hanya mengisi field itu otomatis via `Pelanggan::generateNoReg($prefix)` (parameter yang sebelumnya ada tapi tidak pernah dipakai).
4. **Dropdown wajib diisi jika field `no_reg` dikosongkan**: fallback diam-diam ke `'BF'` hardcode dihapus dari alur Create form (validasi `Rule::requiredIf`), supaya prefix yang terpakai selalu berasal dari daftar resmi yang staff kelola, bukan kebetulan default lama. Model `Pelanggan::generateNoReg(null)` sendiri tetap fallback ke `'BF'` sebagai default parameter (dipertahankan untuk pemanggilan programatik seperti factory/seeder di luar form Create).
5. **Permission dedicated** (`prefix_registrasi.lihat/buat/ubah`) dipilih daripada menumpang `peran.lihat` (seperti yang dilakukan `PengaturanGateway`), karena menumpang permission yang tidak relevan dinilai sebagai jalan pintas kebetulan, bukan keputusan sadar, dan berisiko pada access control ke depan.

## Konsekuensi
- Menambah prefix baru (misal ekspansi ke brand/cabang baru) tidak lagi butuh deploy kode — cukup lewat halaman Pengaturan.
- Form Create Pelanggan sekarang punya 1 constraint baru: field `no_reg` kosong mengharuskan dropdown prefix terisi. Field Edit Pelanggan tidak terpengaruh (scope dropdown ini sengaja dibatasi hanya di Create).
