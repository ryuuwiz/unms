# ADR 0053: Penghapusan PPP Secret Hanya atas Input Tercatat, Terbatas, dan Dilindungi

**Status**: Accepted

## Konteks
Audit integrasi MikroTik menemukan beberapa jalur yang menghapus PPP Secret tanpa input manusia: scheduler 03:00 dengan `--clean-orphans` (menghapus secret berkomentar `UNMS:` atau bernama mirip `xxx_NNNNN`), penghapusan duplikat di rekonsiliasi, dan penghapusan-berdasarkan-nama tanpa cek kepemilikan. NOC membuat secret manual yang sah di luar billing, jadi penghapusan otomatis berisiko memutus pelanggan aktif.

## Keputusan
1. **Penjadwal tidak pernah menghapus.** Jadwal malam memakai `--audit-orphans` (laporan ke Job Log). Penghapusan orphan hanya lewat CLI eksplisit `--clean-orphans`, hanya untuk komentar `UNMS:`; pola nama tidak lagi dianggap penanda.
2. **Berhenti menghapus, isolir tidak.** Layanan Berhenti (input admin/NOC) menghapus secret + sesi lewat `CleanupPppSecretOnOldRouterJob`; rekonsiliasi memastikan Berhenti = secret tidak ada. Suspend hanya disable + kick.
3. **Guard di level service**: `deletePppoeSecret`/`cleanOrphanedPppSecrets` wajib membawa `PppDeletionContext` (actor + alasan), menolak username kosong (`where('name', null)` menjadi `?name` yang cocok dengan semua secret), dan menolak secret berkomentar `MANUAL:`/`NOC:`/`SYSTEM:`/`WHITELIST:` atau akun sistem.
4. **Audit**: setiap hapus/tolak tercatat di `MikrotikJobLog` (`payload`: actor, reason, outcome, snapshot tanpa password).
5. **Batas hapus massal**: `MIKROTIK_MAX_DELETES_PER_RUN` (default 10) per router per eksekusi; melebihi batas menghentikan penghapusan, mencatat kandidat, dan memberi tahu NOC (sekali per router per jam).
6. **Duplikat nama** dihapus hanya bila username terdaftar di billing dan tidak dilindungi; selain itu dilaporkan.
7. **Respons `!trap`** diperlakukan sebagai galat, bukan daftar kosong (mencegah "semua layanan hilang" saat policy API kurang). Rekonsiliasi melewati router offline dan membatasi notifikasi kegagalan.

## Konsekuensi
- Secret orphan berlabel `UNMS:` menumpuk sampai NOC menjalankan CLI; laporan audit harian menjadi sumber tinjauannya.
- Secret Berhenti yang dilindungi komentar `NOC:` tidak dihapus dan tetap disable-only.
- Pemulihan dari kesalahan hapus bergantung pada snapshot di Job Log (manual), bukan restore otomatis.
