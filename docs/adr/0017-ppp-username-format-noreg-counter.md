# [SUPERSEDED by ADR-0025] PPP Username Format: No.Reg + 5-Digit Counter

> **Status: SUPERSEDED** oleh [ADR-0025](file:///c:/Ryu/Projects/unms/docs/adr/0025-ppp-username-random-5-digit-suffix.md) (Format suffix beralih dari sequential counter `max+1` menjadi random CSPRNG 5-digit number).

PPP username pelanggan sebelumnya diisi bebas oleh staff (contoh: `user_budi_01`), yang menyebabkan inkonsistensi dan tidak ada hubungan traceable ke identitas pelanggan. Kami memutuskan format baru: `{No.Reg}_{NNNNN}` (contoh: `BF2308202601_00001`), di-generate otomatis oleh sistem saat staff memilih pelanggan di form layanan.

## Considered Options

- **Format bebas (status quo)** — staff input manual, validasi hanya unik. Ditolak karena tidak traceable ke pelanggan dan rawan typo.
- **Format global sequential** — counter angka global lintas semua pelanggan. Ditolak karena tidak informatif; tidak ada cara tahu username milik pelanggan mana hanya dari username-nya.
- **Format No.Reg + counter per-pelanggan (dipilih)** — prefix No.Reg menjamin traceability, counter 5-digit per-pelanggan menjamin uniqueness. Staff tetap bisa override asal format dipatuhi.

## Consequences

- **Validasi**: suffix harus tepat 5 digit angka (`\d{5}`); prefix harus sama dengan `no_reg` pelanggan pemilik layanan tersebut.
- **Counter baru**: dihitung `max(existing counter per pelanggan) + 1`, dilindungi DB transaction untuk menghindari race condition.
- **Migrasi data lama**: dijalankan via artisan command `layanan:migrate-ppp-username`. Command ini: (1) delete secret lama dari router MikroTik via API, (2) update `ppp_username` di DB ke format baru, (3) dispatch `ProvisionPppoeAccountJob` untuk membuat secret baru. Job yang gagal dicatat di log dan dilanjutkan (tidak stop total).
- **MikroTik orphan cleanup**: karena `createOrUpdatePppoeSecret` lookup by username, ganti username = secret baru dibuat. Secret lama dihapus eksplisit oleh artisan command sebelum dispatch provision baru.
