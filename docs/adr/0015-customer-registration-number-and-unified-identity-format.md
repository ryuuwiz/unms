# ADR 0015: Standarisasi Format Identitas Pelanggan dan Fleksibilitas Nomor Registrasi

## Konteks
Dalam operasional ISP sehari-hari, staf teknisi, customer service, dan billing sering kali menghadapi kesulitan mengidentifikasi pelanggan jika hanya mengandalkan nama (karena potensi kesamaan nama antar warga) atau hanya nomor registrasi saja (karena sulit mengingat nomor acak/panjang).

Sebelumnya terdapat variasi format tampilan data pelanggan di berbagai modul (misal: `Nama Pelanggan (No. Reg)`, `No. Reg - Nama Pelanggan`, atau hanya `Nama Pelanggan`). Selain itu, format nomor registrasi sebelumnya menggunakan format kaku `REG-YYYY-NNNNNN` yang tidak mendukung kustomisasi kode wilayah/prefix bisnis (seperti `BF2309202601` atau `ARS2309202601`).

Sesuai dokumen referensi `docs/data_unms.md` dan kebutuhan operasional, sistem memerlukan:
1. Format representasi identitas pelanggan terpadu di seluruh modul staf: `[No. Reg]_[Nama Pelanggan]`.
2. Generator nomor registrasi berbasis tanggal pendaftaran (`[Prefix][DDMMYYYY][Counter 2-Digit]`) dengan dukungan input custom unik saat registrasi maupun edit pelanggan.
3. Accessor terpusat di Model `Pelanggan` untuk tampilan tabel, detail, dan dropdown selector dengan informasi kontak sekunder.

## Keputusan yang Diambil

1. **Format Baku Identitas Pelanggan (`identitas_lengkap`)**:
   - Seluruh modul staf backoffice (Tabel & Detail Tiket, Tagihan Invoice, Layanan Pelanggan, Pembayaran, Laporan Keuangan, dan Dashboard) menampilkan data pelanggan dengan format `[No. Reg]_[Nama Pelanggan]` (contoh: `WG2309202601_Budi Santoso`).
   - Sisi Portal Pelanggan mandiri (`/portal`) tetap menggunakan sapaan personal `Halo, [Nama Pelanggan]!` untuk kenyamanan konsumen.

2. **Format Baku Selector Dropdown (`label_selector`)**:
   - Seluruh elemen dropdown pemilihan pelanggan (Form Buat Tiket, Buat Invoice, Buat Layanan) menyajikan format `[No. Reg]_[Nama Pelanggan] ([No. HP] • [Perumahan/Cluster])` (contoh: `WG2309202601_Budi Santoso (08123456789 • Cluster Melati)`).

3. **Fleksibilitas Nomor Registrasi (`no_reg`)**:
   - Generator otomatis menghasilkan format `[Prefix][DDMMYYYY][01..99]` (contoh default: `BF2308202601`), dengan urutan counter harian.
   - Field `no_reg` pada form pendaftaran pelanggan bersifat opsional: jika diisi manual oleh staf (misal `ARS2309202601`), sistem memvalidasi keunikan (`unique:pelanggan,no_reg`); jika dikosongkan, sistem meng-generate secara otomatis.
   - Field `no_reg` pada form edit pelanggan dapat diperbarui oleh staf berwenang dengan validasi keunikan data.

4. **Accessor Terpusat pada Model Eloquent**:
   - Penambahan method dan accessor `identitasLengkap()` / `$pelanggan->identitas_lengkap` dan `labelSelector()` / `$pelanggan->label_selector` pada `App\Models\Pelanggan` untuk mencegah duplikasi penulisan format di template Blade.

## Konsekuensi
- Memudahkan staf mengenali identitas pelanggan secara cepat dan akurat di seluruh tahapan operasional.
- Fleksibel terhadap kebutuhan penamaan ID pelanggan historis atau penyesuaian inisial cabang/perumahan.
- Integritas data tetap terjaga 100% melalui unique constraint database pada kolom `no_reg`.
- Data lama dengan format `REG-YYYY-NNNNNN` tetap didukung secara mulus (*backward compatible*).
