# PRD #4: Mikrotik Routers & IP Pool (Data Master)

**Modul:** Mikrotik Routers & IP Pool — data master saja, belum terhubung API
**Urutan Implementasi:** 4 dari 7
**Status:** Draft
**Depends on:** PRD #1 (Users & Roles)

---

## 1. Ringkasan

Modul ini menyimpan data master router Mikrotik yang dikelola ISP (nama, IP address, kredensial akses, deskripsi) beserta **data IP Pool** yang akan dipakai untuk alokasi IP client PPPoE per router (network, rentang IP, queue limit, priority queue). **Belum ada koneksi API real-time ke Mikrotik di PRD ini** — baik data router maupun IP Pool baru tersimpan di sistem sebagai data master; pembuatan pool/queue sungguhan di RouterOS (via API) adalah fase integrasi terpisah setelah seluruh CRUD dasar selesai.

## 2. Tujuan

- Tersedia data referensi router yang lengkap sebelum fase integrasi API dimulai.
- Kredensial sensitif (API password) tersimpan aman (terenkripsi) sejak awal, bukan ditambahkan belakangan.
- Tersedia data master IP Pool per router (network, rentang IP, queue limit) sebagai dasar alokasi bandwidth & IP client PPPoE, sebelum benar-benar dibuatkan di RouterOS pada fase integrasi.

## 3. Role & Akses Modul Ini

| Role | Akses |
|---|---|
| super_admin | Full CRUD, satu-satunya yang bisa melihat/edit kredensial API |
| admin | View only (tanpa lihat password, hanya field lain); untuk IP Pool: full CRUD (tidak menyangkut kredensial) |
| sales, noc, teknisi | View only (nama & lokasi router saja, tanpa kredensial); IP Pool: view only |

## 4. User Stories

- Sebagai **super_admin**, saya ingin mendaftarkan router Mikrotik baru dengan nama, IP address, dan kredensial akses, sebelum router tersebut benar-benar diintegrasikan.
- Sebagai **super_admin**, saya ingin kredensial API tidak terlihat dalam bentuk plain text oleh siapa pun, termasuk di database.
- Sebagai **super_admin**, saya ingin form input router memvalidasi format nama dan IP sejak awal, supaya tidak ada data yang salah format masuk ke sistem.
- Sebagai **NOC**, saya ingin melihat daftar router dan deskripsinya (lokasi/fungsi) untuk referensi saat troubleshooting, tanpa perlu tahu kredensial aksesnya.
- Sebagai **admin**, saya ingin membuat IP Pool baru untuk router tertentu dengan mendefinisikan network, rentang IP, dan queue limit, supaya alokasi bandwidth per pool sudah terencana sebelum benar-benar diterapkan ke Mikrotik.
- Sebagai **admin**, saya ingin sistem menyarankan rentang IP secara otomatis berdasarkan network & CIDR yang saya masukkan, supaya saya tidak perlu menghitung manual.
- Sebagai **admin**, saya ingin mengatur priority queue (1–8) per pool, supaya nanti prioritas bandwidth antar paket bisa dibedakan saat diterapkan di router.

## 5. Functional Requirements

Form input mengikuti desain berikut ("Informasi Router"):

| ID | Requirement |
|---|---|
| FR-4.1 | CRUD data router dengan field: Nama Router, IP Address, Username, Password Router (opsional), Deskripsi |
| FR-4.2 | **Nama Router** wajib diisi, unik, dan hanya menerima huruf besar (A–Z), angka (0–9), dan underscore (`_`) — mis. `CORE_MKT_01`. Divalidasi di form (client-side) dan di server-side |
| FR-4.3 | **IP Address** wajib diisi, menerima format IPv4 atau IPv6, dan harus berupa alamat yang valid & dapat dijangkau dari server billing (divalidasi format saja di level form; keterjangkauan jaringan bukan validasi form, cukup catatan operasional) |
| FR-4.4 | **Username** wajib diisi — merepresentasikan user Mikrotik yang punya akses cukup untuk pengelolaan profil PPP, queue, dll (helper text ditampilkan di form) |
| FR-4.5 | **Password Router** bersifat **opsional** saat create/edit — dikosongkan jika autentikasi ke router memakai metode lain (mis. key), diisi jika sistem perlu login dengan password. Field punya toggle show/hide (ikon mata) |
| FR-4.6 | **Deskripsi** bersifat opsional, free text — dipakai untuk mencatat lokasi, fungsi, atau catatan lain (mis. "Core router kantor pusat, link ke POP A/B"), menggantikan kolom `location`/`notes` terpisah |
| FR-4.7 | Kolom password dienkripsi di level aplikasi (Laravel `encrypted` cast) saat diisi; jika dikosongkan, kolom tetap `null`, bukan string kosong |
| FR-4.8 | Password tidak pernah ditampilkan kembali dalam bentuk plain text di form edit (kosong = tidak diubah; isi ulang hanya jika ingin mengganti) |
| FR-4.9 | Field `status` (`online`/`offline`/`unknown`) tetap bersifat manual di PRD ini — tidak ada di form input, diatur terpisah oleh super_admin (mis. dari list/detail router). Auto-ping/health-check adalah fase terpisah |
| FR-4.10 | Hanya super_admin yang bisa melihat & mengubah Username/Password; role lain melihat data router tanpa kedua kolom kredensial tersebut |

### IP Pool

Form input mengikuti desain "Form Tambah IP Pool":

| ID | Requirement |
|---|---|
| FR-4.11 | CRUD IP Pool dengan field: Nama Pool, IP Network + CIDR, Rentang IP (opsional), Queue Limit (TX/RX), Priority Queue (Pry TX/Pry RX), Routers |
| FR-4.12 | **Nama Pool** wajib diisi, unik, hanya menerima huruf besar (A–Z), angka (0–9), dan underscore (`_`) — mis. `PPP_POOL_RUMAH` |
| FR-4.13 | **IP Network** wajib diisi sebagai dua input terpisah: alamat network (mis. `192.168.88.0`) dan CIDR/prefix (mis. `24`), digabung jadi satu representasi CIDR (`192.168.88.0/24`) |
| FR-4.14 | **Rentang IP** bersifat opsional, format `IP1-IP2` (mis. `192.168.88.2-192.168.88.254`). Tersedia tombol "Gunakan range saran" yang otomatis mengisi field ini berdasarkan IP Network + CIDR yang sudah diisi |
| FR-4.15 | Jika Rentang IP dikosongkan, sistem hanya menyimpan IP Network & Queue (tanpa rentang alokasi spesifik) — sesuai catatan di form |
| FR-4.16 | **Queue Limit** wajib diisi: TX dan RX dalam satuan Mbps (disimpan dalam Mbps, dikonversi ke bit/s hanya saat nanti diterapkan ke Mikrotik pada fase integrasi) |
| FR-4.17 | **Priority Queue** wajib diisi: Pry TX dan Pry RX, nilai integer 1 (tertinggi) sampai 8 (terendah), default `8`/`8` jika tidak diubah |
| FR-4.18 | **Routers** wajib dipilih dari dropdown daftar router yang sudah terdaftar (relasi ke `mikrotik_routers`) — menentukan router mana yang akan jadi target pool ini saat diterapkan nanti |
| FR-4.19 | Sistem memvalidasi format IP Network, CIDR, dan Rentang IP (jika diisi) sesuai kaidah IP address yang sah, termasuk memastikan Rentang IP berada di dalam network yang didefinisikan |
| FR-4.20 | Form menampilkan catatan bantu (helper notes) seperti pada desain: anjuran pakai IP Calculator, format CIDR untuk netmask, dan penjelasan bahwa Rentang IP kosong berarti sistem hanya menyimpan IP Network & Queue |

## 6. Data Model

**`mikrotik_routers`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string, unique | hanya `A-Z`, `0-9`, `_` — mis. `CORE_MKT_01` |
| ip_address | string | IPv4 atau IPv6, harus dapat diakses dari server billing |
| api_port | integer | default 8728/8729, tidak ada di form (nilai default, bisa diubah lewat pengaturan lanjutan jika diperlukan) |
| username | string | user dengan akses pengelolaan profil PPP/queue |
| password | string, encrypted, nullable | opsional — kosong jika autentikasi pakai metode lain |
| description | text, nullable | lokasi, fungsi, atau catatan lain (gabungan location + notes) |
| status | enum(online, offline, unknown) | default: unknown, diatur manual, terpisah dari form input |
| created_at, updated_at | timestamp | |

**`ip_pools`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| mikrotik_router_id | bigint FK → mikrotik_routers.id | router target pool ini |
| name | string, unique | hanya `A-Z`, `0-9`, `_` — mis. `PPP_POOL_RUMAH` |
| ip_network | string | mis. `192.168.88.0` |
| cidr | tinyint | mis. `24` |
| ip_range_start | string, nullable | bagian dari Rentang IP, kosong jika tidak diisi |
| ip_range_end | string, nullable | bagian dari Rentang IP, kosong jika tidak diisi |
| queue_tx_mbps | decimal(8,2) | |
| queue_rx_mbps | decimal(8,2) | |
| priority_tx | tinyint | 1–8, default 8 |
| priority_rx | tinyint | 1–8, default 8 |
| created_at, updated_at | timestamp | |

## 7. Business Rules & Validasi

- Hanya super_admin yang memegang akses penuh ke Username/Password — role lain, termasuk admin, **tidak** bisa melihat kredensial meskipun view-only ke data lain.
- `name` unik antar router, divalidasi dengan regex `^[A-Z0-9_]+$` (huruf besar, angka, underscore saja).
- `ip_address` divalidasi sebagai IPv4 atau IPv6 yang sah secara format.
- `password` boleh kosong; sistem tidak memaksa field ini terisi (berbeda dari desain sebelumnya yang mewajibkan password).
- Router tidak bisa dihapus jika sudah direferensikan subscription (dependency dengan PRD #5) — hanya bisa diarsipkan/nonaktifkan.
- `name` pada IP Pool unik antar pool, divalidasi dengan regex `^[A-Z0-9_]+$`, sama seperti aturan nama router.
- Kombinasi `ip_network` + `cidr` harus berupa network address yang sah (mis. `192.168.88.0/24`, bukan sembarang IP di dalam network tersebut).
- Jika Rentang IP diisi, `ip_range_start` dan `ip_range_end` wajib berada di dalam network yang didefinisikan oleh `ip_network`/`cidr`.
- `priority_tx` dan `priority_rx` dibatasi rentang 1–8; di luar itu ditolak validasi.
- Satu router boleh memiliki banyak IP Pool (relasi one-to-many dari `mikrotik_routers` ke `ip_pools`).
- IP Pool tidak bisa dihapus jika sudah direferensikan oleh subscription/package mapping di fase berikutnya (aturan ini diberlakukan saat relasi tersebut dibuat).

## 8. Halaman yang Dibutuhkan (UI)

1. List router (nama, IP address, deskripsi ringkas, status manual) — kolom Username/Password disembunyikan dari non-super_admin
2. Form create/edit router sesuai desain "Informasi Router": dua kolom (Nama Router | IP Address), (Username | Password Router dengan toggle show/hide), lalu Deskripsi full-width di bawahnya. Khusus super_admin; field password kosong saat edit = tidak diubah
3. List IP Pool (nama pool, network/CIDR, router terkait, queue TX/RX) — bisa difilter per router
4. Form create/edit IP Pool sesuai desain "Form Tambah IP Pool": Nama Pool, IP Network + CIDR (dua input bersebelahan), Rentang IP (dengan tombol "Gunakan range saran"), Queue Limit (TX/RX), Priority Queue (Pry TX/Pry RX), dropdown Routers, lengkap dengan catatan bantu (IP Calculator, format netmask, penjelasan Rentang IP kosong)

## 9. Out of Scope (untuk PRD ini)

- Koneksi API RouterOS sungguhan (test connection, ping, health-check otomatis)
- Provisioning PPPoE (fase integrasi Mikrotik terpisah)
- Setup WireGuard tunnel itu sendiri (infrastruktur, bukan aplikasi)
- Pembuatan IP Pool & Queue sungguhan di RouterOS via API (data di PRD ini murni tersimpan di database, belum diterapkan ke router — fase integrasi Mikrotik terpisah)
- Mapping IP Pool ke Package (PRD #3) — akan dirancang di PRD fase integrasi Mikrotik saat `mikrotik_profile_mappings` dibuat

## 10. Dependencies

- PRD #1 (Users & Roles) — untuk RBAC ketat pada kredensial
- IP Pool bergantung pada data router yang sudah ada di modul ini sendiri (`mikrotik_routers`) — dropdown Routers di form IP Pool memerlukan minimal satu router terdaftar

## 11. Acceptance Criteria

- [ ] Super admin bisa menambah router baru dengan password tersimpan terenkripsi di database (dicek langsung di kolom DB, harus tidak terbaca plain text) — kecuali dikosongkan.
- [ ] Router bisa dibuat tanpa mengisi password sama sekali (field opsional), dan tersimpan sebagai `null`.
- [ ] Percobaan mengisi Nama Router dengan huruf kecil, spasi, atau simbol selain underscore ditolak sistem.
- [ ] Percobaan mengisi IP Address dengan format tidak valid (bukan IPv4/IPv6) ditolak sistem.
- [ ] Percobaan membuat 2 router dengan `name` sama ditolak sistem.
- [ ] Role selain super_admin tidak melihat kolom Username/Password di UI maupun response apa pun.
- [ ] Edit router tanpa mengisi ulang password tidak mengubah password yang tersimpan.
- [ ] Toggle show/hide pada field password berfungsi di form create/edit.
- [ ] Admin bisa membuat IP Pool baru dengan memilih router dari dropdown, dan pool tersimpan terhubung ke router tersebut.
- [ ] Percobaan mengisi Nama Pool dengan format tidak sesuai (huruf kecil/spasi/simbol selain underscore) ditolak sistem.
- [ ] Tombol "Gunakan range saran" berhasil mengisi Rentang IP otomatis berdasarkan IP Network + CIDR yang sudah diisi.
- [ ] IP Pool bisa dibuat tanpa mengisi Rentang IP, dan sistem hanya menyimpan IP Network & Queue.
- [ ] Percobaan mengisi Rentang IP di luar batas network yang didefinisikan ditolak sistem.
- [ ] Percobaan mengisi Priority Queue di luar rentang 1–8 ditolak sistem.
- [ ] Priority Queue yang dikosongkan otomatis terisi default 8/8.