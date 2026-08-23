# PRD: Fase Integrasi Mikrotik (RouterOS API)

**Modul:** Integrasi Mikrotik — koneksi API sungguhan (RouterOS API)
**Fase:** Fase 2 sesuai roadmap `ubiquiti-nms-design.md` §10
**Status:** Draft
**Depends on:** PRD #4 (Mikrotik Routers & IP Pool), PRD #5 (Subscriptions & Billing)

---

## 1. Ringkasan

Sejauh ini, modul Router & IP Pool (PRD #4) dan Subscriptions (PRD #5) baru menyimpan **data master** — belum ada koneksi sungguhan ke perangkat Mikrotik. PRD ini membangun jembatan API-nya: koneksi, wrapper RouterOS API, dan otomatisasi provisioning PPPoE + penerapan IP Pool/Queue, sehingga aksi di aplikasi (aktivasi pelanggan, isolir, dsb.) benar-benar tercermin di perangkat Mikrotik — bukan cuma di database.

## 2. Tujuan

- Aktivasi/isolir subscription otomatis membuat/mengaktifkan/menonaktifkan PPPoE secret di router, tanpa NOC login manual ke Mikrotik.
- Data IP Pool & Queue yang sudah didefinisikan di PRD #4 benar-benar diterapkan ke RouterOS.
- Status router (online/offline) mencerminkan konektivitas nyata, bukan toggle manual.
- Semua operasi ke Mikrotik tahan gangguan (retry otomatis, tidak memblokir request web, dan gagalnya kelihatan jelas oleh NOC — bukan gagal senyap).

## 3. Role & Akses

| Role | Akses |
|---|---|
| super_admin | Full akses: test connection, retry manual, override status darurat |
| noc | Melihat log integrasi, retry manual job yang gagal |
| admin | Melihat status provisioning di halaman subscription (read-only) |
| sales, teknisi | Tidak ada akses langsung ke modul ini |

## 4. User Stories

- Sebagai **NOC**, saya ingin tahu apakah router benar-benar online (bukan status yang di-set manual), supaya saya cepat sadar saat ada gangguan infrastruktur.
- Sebagai **admin**, saat saya mengaktifkan subscription baru, saya ingin sistem otomatis membuat PPPoE secret di router yang sesuai tanpa saya login manual ke Mikrotik.
- Sebagai **admin**, saat pelanggan menunggak dan subscription-nya jadi `isolated`, saya ingin PPPoE-nya otomatis dinonaktifkan di router — dan otomatis diaktifkan lagi begitu pelanggan bayar.
- Sebagai **super_admin**, saya ingin IP Pool & Queue yang sudah saya buat di PRD #4 benar-benar diterapkan ke RouterOS, dengan tombol eksplisit (bukan otomatis saat disimpan, supaya bisa saya review dulu).
- Sebagai **NOC**, saya ingin melihat riwayat percobaan koneksi ke tiap router beserta alasan kegagalan, untuk troubleshooting.
- Sebagai **super_admin**, saya ingin melakukan "Test Connection" ke router baru sebelum dipakai produksi.

## 5. Functional Requirements

### Infrastruktur & Service Layer
| ID | Requirement |
|---|---|
| FR-M.1 | Dibuat `MikrotikService` sebagai wrapper `evilfreelancer/routeros-api-php`, dengan method: `testConnection()`, `createPppoeSecret()`, `enablePppoeSecret()`, `disablePppoeSecret()`, `createIpPool()`, `createSimpleQueue()`, `getSystemResource()` (untuk ping/health check) |
| FR-M.2 | Semua pemanggilan `MikrotikService` **wajib** lewat Queue Job — tidak pernah dipanggil sinkron langsung dari HTTP request/controller |
| FR-M.3 | Job Mikrotik berjalan di **queue connection terpisah** (`mikrotik`), bukan default, sesuai rekomendasi tech stack sebelumnya (worker lambat tidak boleh menyumbat job lain) |

### Provisioning PPPoE (terhubung ke Subscriptions)
| ID | Requirement |
|---|---|
| FR-M.5 | Saat subscription baru pertama kali diaktifkan, sistem dispatch `ProvisionPppoeAccountJob`: membuat PPPoE secret di router terkait dengan username/password dari data subscription dan profile sesuai Bandwidth Profile/IP Pool-nya |
| FR-M.6 | Saat status subscription berubah ke `isolated`, dispatch `DisablePppoeAccountJob` — **menonaktifkan** (bukan menghapus) PPPoE secret |
| FR-M.7 | Saat status subscription kembali `active` dari `isolated` (mis. setelah pembayaran via webhook Xendit), dispatch `EnablePppoeAccountJob` |
| FR-M.8 | PPPoE secret **tidak pernah dihapus otomatis** oleh sistem — penghapusan permanen hanya manual di luar cakupan PRD ini, untuk mencegah kehilangan histori saat toggle status berulang |

### IP Pool & Queue
| ID | Requirement |
|---|---|
| FR-M.9 | Halaman detail IP Pool (PRD #4) mendapat tombol baru **"Terapkan ke Router"** — trigger `SyncIpPoolToRouterJob` yang menjalankan `ip pool add` dan `queue simple add` di RouterOS sesuai data yang sudah tersimpan (network, rentang IP, queue limit, priority) |
| FR-M.10 | Penerapan IP Pool **tidak otomatis** saat data disimpan di form PRD #4 — harus aksi eksplisit, supaya admin bisa review dulu sebelum benar-benar mengubah konfigurasi router produksi |

### Monitoring & Health Check
| ID | Requirement |
|---|---|
| FR-M.11 | Job terjadwal `PingRouterJob` berjalan tiap 5 menit (dikonfigurasi), memanggil `getSystemResource()` ke tiap router dan meng-update `status`, `last_ping_at`, `last_ping_status` secara otomatis |
| FR-M.12 | Halaman detail Router menampilkan tombol **"Test Connection"** (khusus super_admin) — hasil ditampilkan langsung di UI (Livewire polling job singkat), tanpa perlu menunggu jadwal ping berikutnya |
| FR-M.13 | Field `status` pada router menjadi **read-only** di form edit (PRD #4) setelah fase ini aktif — hanya bisa diisi otomatis oleh sistem, kecuali override darurat oleh super_admin dengan catatan wajib diisi |

### Logging & Error Handling
| ID | Requirement |
|---|---|
| FR-M.14 | Setiap job Mikrotik (provisioning, enable/disable, sync pool, ping, test connection) mencatat hasilnya ke `mikrotik_job_logs`: status, jumlah percobaan, pesan error jika gagal |
| FR-M.15 | Retry otomatis maksimal 3x dengan backoff (mis. 30 detik, 2 menit, 5 menit) sebelum job ditandai gagal permanen |
| FR-M.16 | Tersedia halaman **"Log Integrasi Mikrotik"**: daftar job dengan filter router/jenis/status, pesan error, dan tombol **retry manual** per baris (khusus super_admin/noc) |
| FR-M.17 | Job yang gagal permanen (setelah 3x retry) memicu notifikasi ke NOC (in-app minimal; WA opsional tergantung urgensi — detail integrasi WA menyusul di fase SysBlast) |

## 6. Data Model (Baru & Perubahan)

**`mikrotik_job_logs`** (baru)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| mikrotik_router_id | bigint FK | |
| subscription_id | bigint FK, nullable | jika terkait provisioning PPPoE |
| ip_pool_id | bigint FK, nullable | jika terkait sync IP Pool |
| job_type | enum(provision_pppoe, enable_pppoe, disable_pppoe, sync_ip_pool, ping, test_connection) | |
| status | enum(pending, success, failed) | |
| attempt_count | tinyint | |
| error_message | text, nullable | |
| payload | json, nullable | request/response ringkas untuk audit |
| created_at, finished_at | timestamp | |

**`subscriptions`** (field tambahan dari PRD #5)
| Kolom | Tipe | Keterangan |
|---|---|---|
| provisioned_at | timestamp, nullable | kapan PPPoE secret berhasil dibuat sungguhan |
| status | enum(**pending_provisioning**, active, isolated, terminated) | **nilai baru `pending_provisioning` ditambahkan** — lihat Business Rules |

**`ip_pools`** (field tambahan dari PRD #4)
| Kolom | Tipe | Keterangan |
|---|---|---|
| applied_to_router_at | timestamp, nullable | kapan berhasil diterapkan sungguhan ke router |

**`mikrotik_routers`** (field tambahan dari PRD #4)
| Kolom | Tipe | Keterangan |
|---|---|---|
| last_ping_at | timestamp, nullable | |
| last_ping_status | enum(success, failed), nullable | |

## 7. Business Rules

- **Status subscription baru mendapat state `pending_provisioning`**: subscription yang baru dibuat berstatus ini sampai `ProvisionPppoeAccountJob` berhasil — baru berubah `active` otomatis setelah job sukses. Jika gagal 3x retry, tetap `pending_provisioning` dengan error terlihat di halaman detail (bukan otomatis dianggap `active`).
- `mikrotik_routers.status` sepenuhnya diisi otomatis mulai fase ini — override manual butuh permission khusus & wajib diisi catatan alasan (dicatat ke `audit_logs`).
- Kredensial router tetap memakai kolom terenkripsi yang sudah ada di PRD #4 — tidak ada penyimpanan kredensial baru di modul ini.
- Retry job dibatasi 3x otomatis; setelah itu butuh intervensi manual dari NOC/super_admin lewat halaman Log Integrasi.
- `SyncIpPoolToRouterJob` bersifat idempoten — dijalankan ulang tidak boleh membuat pool/queue duplikat di RouterOS (cek nama pool yang sudah ada sebelum create).

## 8. Halaman yang Dibutuhkan (UI)

1. Halaman detail **Router**: tambahan section "Koneksi & Status" (last ping, status real-time) + tombol "Test Connection".
2. Halaman detail **IP Pool**: tombol "Terapkan ke Router" + indikator `applied_to_router_at`.
3. Halaman **Log Integrasi Mikrotik**: list job dengan filter, detail error, tombol retry manual.
4. Halaman detail **Subscription**: badge status provisioning (Menunggu Provisioning / Terprovisi / Gagal) + tombol retry manual jika gagal.

## 9. Out of Scope

- Monitoring traffic/bandwidth pemakaian real-time per pelanggan (beda dari sekadar status online/offline router).
- Backup/restore konfigurasi RouterOS otomatis.
- Manajemen upgrade firmware/RouterOS dari aplikasi.
- Penghapusan permanen PPPoE secret dari aplikasi (hanya enable/disable).

## 10. Dependencies

- PRD #4 (Mikrotik Routers & IP Pool) — data master router & IP Pool harus sudah ada.
- PRD #5 (Subscriptions & Billing) — status subscription jadi pemicu provisioning.
- Tech stack: Redis + Horizon (queue terpisah), `evilfreelancer/routeros-api-php`.

## 11. Acceptance Criteria

- [ ] "Test Connection" ke router yang online memberi hasil sukses dalam <10 detik; ke router mati/unreachable memberi pesan gagal yang jelas.
- [ ] Subscription baru yang diaktifkan menghasilkan PPPoE secret sungguhan di router (diverifikasi langsung lewat Winbox/terminal saat testing), dan status subscription otomatis berubah `active` setelah itu.
- [ ] Mengubah status subscription ke `isolated` benar-benar men-disable PPPoE secret di router, bukan hanya mengubah data di database.
- [ ] Pembayaran yang diterima setelah isolir otomatis meng-enable kembali PPPoE secret (terhubung ke alur webhook Xendit di dokumen v1 §6.4).
- [ ] IP Pool yang di-"Terapkan ke Router" menghasilkan IP Pool & Simple Queue sungguhan di RouterOS sesuai spesifikasi yang tersimpan.
- [ ] `mikrotik_routers.status` berubah otomatis mengikuti hasil ping terjadwal tanpa intervensi manual.
- [ ] Job yang gagal 3x tercatat di halaman Log Integrasi dengan pesan error yang jelas dan bisa di-retry manual.
- [ ] Job Mikrotik berjalan di queue worker terpisah dan tidak memblokir job/request lain saat router lambat merespons.
- [ ] Menjalankan `SyncIpPoolToRouterJob` dua kali untuk pool yang sama tidak menghasilkan pool/queue duplikat di RouterOS.