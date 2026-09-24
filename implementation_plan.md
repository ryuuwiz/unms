# Implementation Plan — PPP Address via Profile per Pool, Live Session Address, IP Publik Dedicated

Hasil sesi grilling (lihat `CONTEXT.md`: **Profile PPP per Pool**, **Alamat Sesi PPP**, **IP Publik Dedicated**).
Semua keputusan di bawah sudah disepakati; asumsi yang saya ambil sendiri ditandai **[ASUMSI]**.

## Keputusan

| # | Keputusan |
|---|-----------|
| 1 | `local-address` & `remote-address` di `/ppp/secret` **dikosongkan hanya untuk PPPoE dinamis**. `IP Static` & IP Publik tetap literal. |
| 2 | Alamat dibawa **Profile PPP per Pool**: `{nama_bandwidth}@{nama_pool}` (rate-limit + `local-address`=gateway pool + `remote-address`=nama pool). Layanan literal memakai profile polos `{nama_bandwidth}`. |
| 3 | Cakupan "profile dhcp" = alamat + rate-limit di profile. `dns-server` ditunda (tidak ada sumber datanya). |
| 4 | IP sesi dibaca **live** (`/ppp/active`, `/ip/pool/used`), cache singkat. UNMS tidak lagi mengalokasikan/menulis `ip_dynamic`. Kolom **tidak di-drop**. |
| 5 | Rekonsiliasi mengosongkan nilai literal lama pada secret dinamis (dry-run tersedia, tanpa kick massal). |
| 6 | Inventaris `ip_publik` (tabel baru), 1 IP per layanan pada tahap pertama, banyak baris per layanan didukung skema. |
| 7 | Alamat IP Publik & `ip_static` tidak boleh berada di rentang IP Pool mana pun pada router yang sama. |
| 8 | Penagihan: `hargaTambahan()` dijumlahkan di `generateInvoice()`; rincian di `keterangan`; tanpa prorata otomatis. |
| 9 | Penetapan/pelepasan manual memutus sesi aktif. Layanan `Berhenti`/dihapus melepas IP otomatis; `Suspend` tetap memegang. |
| 10 | Tanpa enum `JenisKoneksi` baru; IP Publik = add-on yang menimpa `resolveRemoteAddress()`/pemilihan profile. |
| A1 | **[ASUMSI]** Diskon promo hanya berlaku pada harga paket, tidak pada add-on IP Publik. |
| A2 | **[ASUMSI]** Status IP Publik (`tersedia`/`terpakai`) diturunkan dari `layanan_pelanggan_id` (tanpa kolom status → tidak bisa drift). |
| A3 | **[ASUMSI]** Harga snapshot: kolom `harga_bulanan` (harga daftar) + `harga_ditagih` (disalin saat penetapan, null saat lepas). Yang ditagih = `harga_ditagih`. |

## Fase 1 — Profile PPP per Pool, secret dinamis kosong

- `ProfilBandwidth::pppProfileName(?IpPool)` → nama profile.
- `LayananPelanggan`: `ipPubliks()` (hasMany), `usesLiteralAddress()`, `profilePool()`; `resolveRemoteAddress()` = IP Publik → `ip_static` valid → `null`; `resolveLocalAddress()` = gateway IP Publik → (jika `ip_static`) gateway pool / subnet → `null`.
- `MikrotikService`
  - `ensurePppProfile(..., ?IpPool $pool = null)`: buat/perbarui profile dengan alamat bila `$pool`.
  - `syncAllBandwidthProfiles()`: sinkron profile polos **dan** varian per pool router.
  - `createOrUpdatePppoeSecret()`: hapus `allocateDynamicIp()`; profile = `pppProfileName(profilePool())`; kirim/unset alamat hanya via `resolve*Address()` (kode unset yang sudah ada otomatis mengosongkan literal lama).
  - Hapus `allocateDynamicIp()`; hapus `IpPool::{usableAddresses,usedAddresses,nextFreeAddress,hasFreeAddress}`.
  - `autoRecoverPppSecrets()`: `expectedProfile` memakai nama baru; drift alamat sudah ada (`remote_address_mismatch`, `local_address_mismatch`) → dengan expected `''` otomatis mengosongkan.
- `UpdatePppoeProfileJob`/`updatePppoeProfile()` memakai nama profile yang sama.
- **Urutan aman per router**: pool → profile → secret. `createOrUpdatePppoeSecret()` sudah memanggil `syncIpPool` lalu `ensurePppProfile` sebelum menulis secret; `autoRecoverPppSecrets()` juga sinkron pool & profile sebelum secret.

## Fase 2 — Alamat Sesi PPP live

- `getPppStatus()` tetap; `ip_address` dari `/ppp/active`; `Show` menampilkan gateway dari `ipPool` bila secret kosong.
- `MikrotikService::getPoolUsage(Router): array` — satu `/ip/pool/used/print` per router, dikelompokkan per nama pool, cache `mikrotik.status_cache_ttl`, **tidak pernah throw** (`null` = tidak diketahui).
- `IpPool::totalAddresses()` (hitung aritmetika, bukan array). `IpPool/Index` menampilkan `terpakai/total` + peringatan ≥ 90%.

## Fase 3 — IP Publik Dedicated

- Migrasi `ip_publik`: `router_id` FK, `alamat_ip` unik, `gateway`, `harga_bulanan`, `harga_ditagih`, `layanan_pelanggan_id` nullable FK `nullOnDelete`, `keterangan`.
- Model `IpPublik` + factory; Policy `IpPublikPolicy`; permission `ip_publik.{lihat,buat,ubah,hapus}` (super_admin, admin, noc); menu "IP Publik" di *Jaringan & Infrastruktur*.
- Validasi `alamat_ip`: IPv4, tidak di dalam rentang IP Pool router yang sama; `gateway` IPv4.
- `App\Actions\IpPublik\TetapkanIpPublikAction` / `LepasIpPublikAction`: cek router sama, `tersedia`, layanan PPPoE dan belum punya IP Publik; salin harga; dispatch `ProvisionPppoeAccountJob` dengan `kickActive`.
- `ProvisionPppoeAccountJob(layanan, kickActive=false)`: bila true, `removeActiveSession()` setelah sukses.
- `LayananPelanggan/Edit`: `flux:select` IP Publik (router layanan, tersedia + yang sedang dipegang) dengan peringatan putus sesi.
- Observer: `Berhenti`/`deleted` → lepas IP otomatis.
- `Pelanggan/Show`: tampil IP Publik.
- Validasi `ip_static` tidak bentrok rentang pool (form Edit layanan; Create layanan tidak mengisi `ip_static`). Sebaliknya, form IP Pool menolak rentang yang memuat IP Publik/`ip_static` router itu.
- Billing: `LayananPelanggan::hargaTambahan()`; `BillingService::generateInvoice()` menagih `hargaDasar() + hargaTambahan()`, diskon hanya pada harga paket (A1), `keterangan` berisi rincian bila ada add-on. `generateManualInvoice()`, `hitungRincianTagihanPertama()` tidak disentuh (aturan `.ai/rules/billing.md` tetap).

## Fase 4 — Dokumen & rilis

- ADR `0051` (supersede 0019/0022/0033/0044), ADR `0052` (IP Publik Dedicated).
- Update status ADR lama → *Superseded by 0051*.
- **Rollout** (per router, disarankan staging dulu): (1) deploy kode; (2) `mikrotik:recover-ppp --dry-run` dan periksa `dry_run_changes`; (3) jalankan tanpa dry-run; (4) sesi lama baru memakai profile/pool baru saat reconnect.

## Test

Pest: `MikrotikServiceTest` (profile per pool, secret dinamis tanpa alamat, unset literal lama, profil polos untuk literal), `IpPoolTest` (usage, totalAddresses), `LayananPelangganObserverTest` (resolve*), `IpPublikTest` (CRUD, validasi rentang pool, policy, tetapkan/lepas/berhenti/hapus, form Edit layanan), `Billing/IpPublikBillingTest` (jumlah, keterangan, snapshot, promo hanya paket, manual invoice tidak berubah).
Test lama untuk perilaku yang dihapus (`allocateDynamicIp`, `nextFreeAddress`, `ip_dynamic`) **diganti**, bukan ditinggal gagal — dilaporkan di akhir.

## Kendala serius / tidak dapat diverifikasi dari sini

1. **Tidak ada RouterOS nyata.** Semua test memakai mock/koneksi gagal. Wajib diuji manual di router staging:
   - `/ppp/profile/add` dengan `remote-address=<nama-pool>` + `local-address=<ip>`, dan nama profile berisi `@`.
   - Secret tanpa alamat benar-benar mendapat IP dari pool profile.
   - `/ip/pool/used` menampilkan sesi PPP dengan field `pool` = nama pool.
   - `/ppp/secret/unset value-name=remote-address` pada secret existing.
2. **Pool penuh tidak lagi terdeteksi saat provisi** (router yang mengalokasikan) → pelanggan gagal login tanpa error di UNMS. Mitigasi: indikator pemakaian pool (Fase 2). Alert otomatis di luar cakupan.
3. **Sesi aktif tidak berubah** sampai reconnect (mengganti profile/mengosongkan alamat tidak berlaku ke sesi berjalan).
4. **Perubahan `ip_pool_id`/`ip_static` di form Edit tidak me-reprovision router seketika** (perilaku lama); diselaraskan oleh rekonsiliasi 15 menit.
5. NAT/firewall/routing publik di luar PPP tidak dikelola UNMS.
6. Tunggakan IP Publik pada invoice yang digabung ikut terserap lewat jalur tunggakan yang sudah ada (jumlah total), tanpa rincian per komponen.

## Status implementasi (2026-09-23)

Fase 1–4 diimplementasikan. Suite penuh via Sail: 713 test, 712 lulus, 1 skip (bukan dari perubahan ini). Pint bersih.
**Belum dilakukan / wajib manual:** verifikasi di RouterOS staging (lihat "Kendala serius" no. 1), dan menjalankan `db:seed --class=RolesAndPermissionsSeeder` di produksi (idempoten) agar permission `ip_publik.*` terbentuk.

## Tindak lanjut audit (2026-09-24)

Diimplementasikan setelah audit integrasi MikroTik dan jawaban Q1–Q13: lihat ADR 0053 dan CONTEXT.md ("Penghapusan PPP Secret", "Orphaned Secret").
Ringkas: scheduler hanya audit orphan; Berhenti menghapus secret (isolir tidak); guard/audit/batas hapus di service; trap `!trap` dicek; job Enable/Disable dan rekonsiliasi tahan data basi; provisi ulang saat username/router/pool/`ip_static` berubah; gateway di luar rentang pool; nama/network pool terpakai dikunci + cleanup router; hapus router diblok bila ada IP Publik terpasang dan pindah router mewajibkan pool tujuan; form Edit status lewat Action; listener bayar ke `mikrotik-high`.
**Belum dikerjakan:** provisi sinkron di request web (F13), ekstraksi kontrak `PppProvisioner` untuk RADIUS (F18), timeout `supervisor-high` 30 dtk vs provisi multi-round-trip, dan kuras `queues:mikrotik` di produksi.
