# Rencana: Alur Ticket Pemasangan Lengkap + Penyederhanaan Tambah Data Registrasi Billing

Hasil sesi grilling+domain-modeling (bukan spekulasi) — setiap keputusan di bawah sudah dikonfirmasi eksplisit oleh user. Dokumen ini gabungan/penerus dari `docs/plan/refactor-layanan-pelanggan.md`: field yang plan itu bilang "diatur nanti melalui ticket pemasangan" (Router, IP Pool, Username PPP) sekarang benar-benar dipindah ke sini.

## Latar belakang & keputusan kunci

Router/IP Pool/Username PPP **tidak lagi diisi saat Tambah Data Registrasi Billing**. Layanan dibuat murni komersial (paket, harga, alamat, tagihan pertama), status awal `PROSES`, `router_id`/`ppp_username` kosong. Field-field itu baru diisi lewat aksi **"Aktivasi"** oleh NOC di dalam Ticket Pemasangan, setelah teknisi menyelesaikan sebagian pekerjaan lapangan.

Ini keputusan yang membatalkan sebagian implementasi sesi sebelumnya (yang sempat meng-auto-resolve router/pool/ppp saat `LayananPelanggan/Create.php::save()`) — bagian itu harus di-revert.

## Bagian A — Penyederhanaan Tambah Data Registrasi Billing ✅ SELESAI

1. **`Create.php` (LayananPelanggan)**: hapus total properti & validasi `router_id`, `ip_pool_id`, `ip_static`, `ppp_username`, `jenis_koneksi`-picker beserta blok panggilan `MikrotikService`/`MikrotikJobLog` di `save()`. Form ini menyimpan: paket, `price_mode`/`price_custom`, alamat (sumber utama/custom, perumahan, peta — sudah ada dari sesi sebelumnya), tagihan pertama. `jenis_koneksi` layanan baru **selalu PPPoE** (IP Static tidak didukung di form ini — lihat poin 4).
2. **`DaftarkanLayananAction`**: hapus parameter/logic yang berhubungan dengan router/ip_pool/ip_static/ppp_username. Tidak ada lagi pemanggilan Mikrotik di action ini.
3. **Migration**: `layanan_pelanggan.router_id` dan `ppp_username` jadi **nullable** (sekarang NOT NULL) — **sudah dieksekusi**. Audit semua pemakai `$layanan->router`/`$layanan->ppp_username` yang mengasumsikan selalu ada (Observer, `resolveRemoteAddress()`, listing Index, dsb.) — tambahkan null-guard di mana perlu, jangan asumsikan lagi.
4. **PPP Username override & IP Static**: kemampuan staf mengubah `ppp_username` manual dan mengubah `jenis_koneksi` ke IP Static **tetap ada, tapi hanya di `Edit.php`** (tidak diubah), tidak lagi di `Create.php`.
5. ~~`PaketLayanan.ip_pool_id` otomatis~~ **DIBATALKAN** (koreksi arsitektur): IP Pool terikat ke satu router spesifik, sedangkan Paket Layanan lintas-router — satu `ip_pool_id` tetap per paket akan salah begitu NOC memilih router yang berbeda dari yang "dianggap" paket itu. Sebagai gantinya: **NOC memilih IP Pool secara manual saat Aktivasi**, difilter ke pool milik router yang baru saja dipilih (persis pola `updatedRouterId()`/auto-select-jika-cuma-1-pool yang dulu ada di `Create.php`, sekarang dipindah ke Aktivasi). Bukan sepenuhnya otomatis, tapi jujur secara arsitektur — lihat B3 yang sudah direvisi.
6. **Duplikat check** (`assertBelumAdaDuplikat`, sekarang butuh `router_id`) pindah ke titik Aktivasi, bukan lagi saat create (karena router baru diketahui di situ).

## Bagian B — Alur Ticket Pemasangan ✅ SELESAI

### B1. Skema baru

- **`ticket_divisi`** (pivot yang sudah ada, `ticket_id` + `divisi`): tambah kolom `status` — enum seragam `belum` / `progress` / `selesai` untuk semua divisi (NOC/CS/Admin praktiknya cuma pakai `belum`→`selesai`, tidak pernah `progress`; hanya Teknisi yang benar-benar pakai tiga-tiganya).
- **`ticket_pemasangan`** (tabel detail baru, 1:1 dengan `ticket`, hanya relevan untuk `jenis=pemasangan`): `ticket_id`, `odp_port_id` (FK ke `odp_port`, diisi teknisi), kolom timestamp/actor untuk audit Aktivasi (`diaktivasi_pada`, `diaktivasi_oleh`). Tidak menyimpan ulang router/ppp — begitu Aktivasi jalan, nilai itu sudah ada di `layanan_pelanggan` sendiri (single source of truth).
- **Media collections** (Spatie MediaLibrary, `Ticket` sudah `implements HasMedia`, tinggal definisikan `registerMediaCollections()`): `foto_pemasangan` (multiple), `foto_speedtest` (single/multiple), `foto_tanda_tangan_mou` (single), `foto_bersama_pelanggan_teknisi` (single/multiple).
- **`User`**: tambah MediaLibrary collection `foto_profil` (single) — berlaku untuk semua user semua divisi, bukan cuma teknisi.
- **Role `customer_service`**: `DivisiTicket::CustomerService` sudah ada di enum tapi role Spatie-nya belum pernah di-seed (cuma ada `super_admin`, `admin`, `sales`, `noc`, `teknisi`). Tambahkan role ini di `RolesAndPermissionsSeeder` supaya CS punya identitas RBAC sendiri, bukan menumpang `admin`.

### B2. Assignment teknisi

Reuse `Ticket.pic_id` yang sudah ada sebagai "teknisi yang ditugaskan" (tidak ada kolom assignee baru). "Foto teknisi di detail ticket" = `$ticket->pic->getFirstMediaUrl('foto_profil')`. Assignee untuk NOC/CS/Admin tidak disimpan sebagai field — siapa yang menandai selesai sudah cukup tercatat lewat `TicketHistori`/Activitylog.

### B3. Aksi "Aktivasi" (NOC)

Aksi baru (bukan tombol "Provisi" yang sudah ada di `Index.php` — itu untuk retry setelah router/ppp ter-assign, beda tujuan), muncul di halaman detail ticket, permission NOC:

1. **Gate**: disabled sampai status divisi Teknisi minimal `progress` **dan** `ticket_pemasangan.odp_port_id` sudah terisi **dan** minimal 1 foto di `foto_pemasangan`.
2. NOC pilih **Router** dari dropdown nyata (hanya `status_koneksi = Online`) — satu dari dua pilihan manual asli di alur ini, karena topologi jaringan/lokasi customer memang butuh keputusan manusia.
3. NOC pilih **IP Pool**, difilter ke pool milik router yang baru dipilih di langkah 2 — auto-select kalau router itu cuma punya 1 pool (reuse pola `updatedRouterId()` yang dulu ada di `Create.php`), tampil sebagai dropdown kalau lebih dari satu (lihat koreksi di A5 — bukan diambil dari Paket, karena Paket lintas-router).
4. Sistem otomatis generate `ppp_username` (`LayananPelanggan::generatePppUsername()`, tidak berubah dari sekarang); `odp_port_id` disalin dari `ticket_pemasangan`.
5. Profil Bandwidth ditampilkan **read-only** (sudah tetap mengikuti paket sejak pendaftaran, tidak bisa diganti di sini).
6. Cek duplikat (`assertBelumAdaDuplikat`) dengan router yang baru dipilih.
7. Simpan field-field di atas ke `layanan_pelanggan`, lalu panggil `MikrotikService::createOrUpdatePppoeSecret()` + set status `Aktif` — persis logic yang dulu ada di `Create.php::save()`, sekarang di sini. Gagal di langkah Mikrotik → field sudah tersimpan, jadi tombol "Provisi" yang sudah ada di `Index.php` otomatis jadi jalur retry-nya.

### B4. Urutan & gating status per-divisi

- **Teknisi**: `belum` → `progress` (setelah pilih ODP+port & upload ≥1 foto pemasangan) → `selesai` (setelah Aktivasi NOC selesai, upload foto speedtest + foto tanda tangan MOU + foto bersama).
- **NOC**: `belum` → `selesai`. Tidak digating oleh status Teknisi selain gate Aktivasi di B3 — begitu Aktivasi berhasil dijalankan, NOC boleh langsung tandai selesai kapan saja (tidak dipaksa urutan lebih lanjut, sesuai keputusan: hanya 2 gate yang benar-benar dipaksa: Aktivasi butuh progres Teknisi, Admin butuh pembayaran — bukan rantai NOC→CS→Admin).
- **CS**: `belum` → `selesai`. Tidak digating oleh divisi lain.
- **Admin**: `belum` → `selesai`, **hard block** sampai invoice pertama layanan berstatus `Lunas`.

### B5. Status keseluruhan tiket (master)

`StatusTicket` (state machine yang sudah ada, dengan efek samping `UbahStatusTicketAction`) **diturunkan otomatis**: begitu status keempat divisi (Teknisi, NOC, CS, Admin) semua `selesai`, sistem otomatis menjalankan transisi ke `StatusTicket::Selesai` lewat `UbahStatusTicketAction` yang sudah ada (supaya efek samping seperti perubahan `StatusPelanggan` tetap jalan). Staf tidak lagi klik "Selesaikan Ticket" manual terpisah untuk tiket jenis Pemasangan.

## Yang sengaja TIDAK dikerjakan (YAGNI, sesuai diskusi)

- Tidak ada UI picker IP Pool manual di mana pun dalam alur ini — sepenuhnya dari `paket->ip_pool_id`.
- Tidak ada rantai gating NOC→CS→Admin selain dua gate eksplisit di atas.
- Tidak ada perubahan pada alur Gangguan/Pencabutan/Pindah Alamat — kolom `ticket_divisi.status` baru cuma dipakai/di-gate untuk `jenis=Pemasangan`.
- Profil Bandwidth tidak bisa di-override saat Aktivasi.

## Urutan implementasi (selesai dikerjakan, urutan asli)

1. ✅ Bagian A (sederhanakan Create, migration nullable, koreksi IP Pool jadi manual saat Aktivasi bukan dari Paket).
2. ✅ Skema Bagian B1 (migration `ticket_divisi.status`, tabel `ticket_pemasangan`, role `customer_service`, permission `layanan_pelanggan.aktivasi`, foto profil User).
3. ✅ UI teknisi tahap 1 (upload foto, pilih ODP+port, status → progress) — `Ticket/Show.php::simpanProgressLapangan()`.
4. ✅ Aksi Aktivasi NOC (B3) — `Ticket/Show.php::prosesAktivasi()`, termasuk mengikat OdpPort ke layanan.
5. ✅ UI teknisi tahap 2 + tandai selesai; NOC/CS/Admin tandai selesai; auto-derive status master (B5) — `UbahStatusDivisiTicketAction`.
6. ✅ Tombol "Buat Ticket Pemasangan" di `Pelanggan/Show.php` untuk layanan `PROSES` tanpa router (entry point alur baru).

**Catatan implementasi yang menyimpang dari draft awal, dengan alasan:**
- `UbahStatusTicketAction::execute()` mendapat parameter baru `bool $otomatis = false` — auto-derive status master (langkah 5) melewati state-machine `transisiValid()` dan Policy `ubahStatus` biasa, karena otorisasi sesungguhnya sudah dicek di level per-divisi (`ubahStatusDivisi`), dan urutan divisi yang bebas (bukan NOC→CS→Admin berurutan) berarti status keseluruhan tiket bisa perlu lompat langsung ke Selesai dari status apa pun.
- Flag `perlu_aktivasi_manual` (mekanisme lama) sekarang hanya di-set kalau tiket **belum** merujuk `layanan_pelanggan_id` saat dibuat -- supaya alur lama (tiket dulu, baru billing) dan alur baru (billing dulu, baru tiket dengan Aktivasi) tidak saling tabrak.
- Percobaan awal membuat foto profil User meng-cleanup otomatis lewat `singleFile()` Spatie MediaLibrary tidak konsisten lewat siklus request Livewire (`Auth::user()` + `FileAdder` internal); diganti `clearMediaCollection()` eksplisit sebelum unggah -- lebih sederhana dan pasti benar daripada terus menelusuri internal vendor.
