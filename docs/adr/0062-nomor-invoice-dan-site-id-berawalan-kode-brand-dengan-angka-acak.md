# ADR 0062: Nomor Invoice dan Site ID Berawalan Kode Brand dengan Angka Acak

Menggantikan ADR-0027. Nomor invoice kini `[KodePrefix]INV-[YYYYMMDD tanggal terbit][7 digit acak]` (mis. `BFINV-202609274839201`) dan Site ID `[KodePrefix]APP[8 digit acak]` (mis. `BFAPP48291037`); kode diambil dari Prefix Registrasi milik pelanggan saat terbit (Brand Pelanggan, ADR-0061) dan kosong bila No. Registrasi tidak cocok dengan prefix mana pun. Tujuannya: merek terlihat langsung di setiap nomor yang dipegang pelanggan, dan nomor tidak berurutan sehingga tidak bisa ditebak/dihitung dari luar. Digit diacak dengan CSPRNG dan diacak ulang bila bentrok (pola ADR-0025); 7 digit untuk invoice karena tagihan periodik terbit massal di tanggal yang sama per brand.

## Consequences

- No. Registrasi dan periode tidak lagi terbaca dari nomor invoice (keduanya tetap tercetak di invoice dan pesan).
- **Nomor invoice lama tidak diubah** (sudah terkirim di tautan WhatsApp, dipakai sebagai `external_id` gateway, dan tercetak). **Site ID lama diganti** sekali lewat migrasi data demi konsistensi; penggantian tercatat di activity log layanan dan Site ID lama tidak bisa dicari lagi. Comment PPP Secret di router dan deskripsi link bayar gateway yang sudah terbit tetap memuat Site ID lama (kosmetik: secret UNMS dikenali dari awalan `UNMS:`, dan Xendit tidak mengizinkan deskripsi diubah).
- Nomor yang sudah terbit tidak mengikuti perubahan prefix pelanggan setelahnya.
- Pencocokan cadangan webhook gateway yang mem-parse `INV-` dari `external_id` harus menerima kode di depan `INV-`.
