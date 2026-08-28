# ADR 0027: Format Nomor Invoice Terstruktur Menyertakan No. Registrasi Pelanggan

## Konteks
Sebelumnya format nomor invoice menggunakan pola berurutan global `INV-YYYYMM-NNNNNN` (contoh: `INV-202608-000001`). Untuk mempermudah audit operasional, rekonsiliasi pembayaran manual maupun otomatis pada payment gateway, serta membedakan tagihan antar pelanggan secara instan, nomor invoice diwajibkan menyertakan identitas unik No. Registrasi (`no_reg`) pelanggan.

## Keputusan yang Diambil

1. **Format Standar Penomoran Invoice**:
   - Format baku nomor invoice diubah menjadi:
     `INV-[No.Reg]-[YYYYMM]-[Counter]` (contoh: `INV-BF2309202601-202608-01`).
   - Prefix: `INV-`
   - Segmen 1: `[No.Reg]` pelanggan (contoh: `BF2309202601`, `ARS2309202601`)
   - Segmen 2: `[YYYYMM]` dari `periode_tagihan` atau tanggal terbit invoice (contoh: `202608`)
   - Segmen 3: `[Counter]` 2-digit berurutan (`01`, `02`, dst.) per pelanggan per periode untuk mendukung skema multi-layanan pelanggan pada bulan yang sama.

2. **Logika Pembuatan Nomor Otomatis**:
   - Method `Invoice::generateNoInvoice(?int $pelangganId, ?string $periodeTagihan)` pada model `Invoice` mengecek record pelanggan terkait, menyusun prefix `INV-[no_reg]-[YYYYMM]-`, dan mengambil counter terakhir dengan `lockForUpdate()` untuk menjamin keunikan dan pencegahan race condition.
   - Jika `pelanggan_id` belum disetel saat *creation hook*, sistem mencari `layananPelanggan->pelanggan_id` atau fallback ke general prefix.

## Konsekuensi
- Identitas tagihan pelanggan langsung terbaca dari nomor invoice di semua channel (WhatsApp, PDF Invoice, Portal Pelanggan, Dashboard Admin, dan Callback Payment Gateway).
- Idempotensi `external_id` pada payment gateway menjadi lebih presisi karena nomor invoice sudah mencakup identitas pelanggan dan periode tagihan.
