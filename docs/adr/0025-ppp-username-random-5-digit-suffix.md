# PPP Username Format: No.Reg + Random 5-Digit Token

## Context
Sebelumnya, [ADR-0017](file:///c:/Ryu/Projects/unms/docs/adr/0017-ppp-username-format-noreg-counter.md) menetapkan format `ppp_username` sebagai `{No.Reg}_{NNNNN}` dengan suffix berupa *sequential counter* per-pelanggan (`00001`, `00002`).

Namun, format *sequential counter* memiliki beberapa keterbatasan:
1. **Prediktabilitas**: Nomor urut `_00001`, `_00002` mudah ditebak, membuka celah eksplorasi username jika kredensial PPP diketahui sebagian.
2. **Kesan Status/Hirarki**: Angka berawalan nol ganda (`00001`) sering menimbulkan kebingungan operasional saat layanan pertama dihapus/diterminasi dan layanan kedua tetap aktif (`00002`).

Kami memutuskan untuk memperbarui mekanisme pembuatan `ppp_username` dengan mempertahankan prefix `{No.Reg}` (untuk penjaminan traceability ke identitas pelanggan) namun mengubah suffix menjadi **5-digit angka acak (*CSPRNG token* `10000`–`99999`)** yang dijamin unik global di seluruh sistem.

## Considered Options

- **Sequential Counter (`00001`, `00002`) [ADR-0017 - Superseded]**: Mudah dipahami namun prediktif dan menimbulkan inkonsistensi saat layanan lama di-soft delete.
- **Argon2 / Heavy Hash Derivation**: Ditolak karena Argon2 adalah *memory-hard key derivation function* untuk password rahasia. Menggunakan Argon2 untuk username publik menimbulkan overhead CPU/RAM tinggi yang tidak perlu.
- **Full UUID (36 Karakter Hex)**: Ditolak karena string username menjadi terlalu panjang (`49 karakter`), menyulitkan teknisi lapangan saat mengonfigurasi modem/ONT pelanggan atau saat troubleshooting terminal MikroTik.
- **CSPRNG 5-Digit Number `random_int(10000, 99999)` (Dipilih)**: Menggunakan hardware CSPRNG bawaan OS yang cepat, aman, menghasilkan rentang pasti 5 digit numerik (tidak berawalan nol ganda), dan sangat ramah bagi teknisi lapangan.

## Decisions

1. **Format Baku**:
   - `{No.Reg}_{NNNNN}` (contoh: `BF2308202601_84920`).
   - Prefix: `no_reg` pelanggan aktif.
   - Suffix: 5 digit angka acak dari `random_int(10000, 99999)`.

2. **Skop Keunikan & Collision Handling**:
   - Pengecekan keunikan dilakukan secara global di tabel `layanan_pelanggan` (termasuk `withTrashed()`).
   - Menggunakan *retry loop safety guard* (maksimal 10 percobaan). Jika 10 kali collision berturut-turut, sistem melempar runtime exception.

3. **Immutabilitas**:
   - `ppp_username` bersifat *strictly immutable* (tetap seumur hidup layanan).
   - Perubahan paket bandwidth, migrasi IP Pool, atau perpindahan router tidak mengubah `ppp_username` agar tidak memutus konfigurasi CPE/ONT pelanggan.

4. **Validasi**:
   - Regex validasi: `/^[A-Z0-9]+_[0-9]{5}$/`.

## Consequences

- Method `LayananPelanggan::generatePppUsername(Pelanggan $pelanggan)` diperbarui menggunakan `random_int(10000, 99999)` dengan verifikasi keunikan global.
- Helper `LayananPelanggan::extractCounter(string $pppUsername)` tetap kompatibel karena memvalidasi regex 5-digit angka `_([0-9]{5})$`.
- Seluruh unit & feature test yang menguji auto-fill atau pembuatan `ppp_username` disesuaikan untuk menguji kepatuhan pola regex (`^BF[0-9]{10}_[0-9]{5}$`) alih-alih hardcode string `_00001`.
