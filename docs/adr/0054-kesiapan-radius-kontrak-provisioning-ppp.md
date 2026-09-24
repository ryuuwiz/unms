# ADR 0054: Kesiapan RADIUS — Kontrak Provisioning PPP Ditunda sampai Implementasi RADIUS Dimulai

**Status**: Proposed

## Konteks
Provisioning PPP saat ini berbasis PPP Secret di RouterOS. Migrasi ke RADIUS direncanakan. Titik keterikatan ke `MikrotikService` (transport RouterOS + logika domain dalam satu kelas ±1800 baris):
- Job siklus hidup: `ProvisionPppoeAccountJob`, `EnablePppoeAccountJob`, `DisablePppoeAccountJob`, `UpdatePppoeProfileJob`, `CleanupPppSecretOnOldRouterJob` (`handle(MikrotikService)`).
- Pemanggil sinkron: `Ticket/Show`, `LayananPelanggan/Index::provisionLayanan`, `Pelanggan/Show`.
- Pemicu: `LayananPelangganObserver`, `HandleLayananStatusChangedListener`, `TriggerMikrotikAktivasiStubListener`.
- Khusus RouterOS (tidak ikut RADIUS): sinkron IP Pool/Profile, rekonsiliasi drift, audit orphan, `getPoolUsage`, `getPppStatus`.

## Keputusan
Kontrak **belum diekstrak sekarang**: hanya ada satu implementasi, dan kontrak yang ditebak tanpa implementasi kedua hampir pasti salah bentuk; refactor ±20 test job/mock hanya untuk antarmuka yang tidak dipakai. Saat pekerjaan RADIUS dimulai, ekstrak `PppAccountProvisioner` dengan operasi berbasis `LayananPelanggan` (bukan `Router`):
`provision`, `enable`, `disable`, `remove(PppDeletionContext)`, `changeProfile`, `disconnect`; implementasi `MikrotikSecretProvisioner` membungkus `MikrotikService`. Job dan pemanggil sinkron bergantung pada kontrak; rekonsiliasi/pool/profile tetap di `MikrotikService`.

Catatan desain RADIUS (agar tidak menutup pintu): password tetap reversibel (`ppp_password_terenkripsi`, dibutuhkan CHAP/MS-CHAP); IP statis/publik = `Framed-IP-Address`, pool = `Framed-Pool`; isolir = Auth-Reject atau Disconnect-Request/CoA (bukan disable secret); penanda kepemilikan sebaiknya kolom DB, bukan komentar RouterOS; kebijakan ADR-0053 (input tercatat, batas hapus) berlaku sama.

## Konsekuensi
- Tidak ada perubahan kode sekarang. Biaya ekstraksi ditanggung saat RADIUS dimulai, dengan dua implementasi nyata sebagai pemandu bentuk kontrak.
