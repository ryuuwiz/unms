# ADR 0018: Pipeline Provisi dan Sinkronisasi Otomatis Router MikroTik

## Konteks
Sistem UNMS mengelola infrastruktur jaringan berbasis MikroTik RouterOS yang mencakup berbagai entitas data yang saling bergantung:
1. **IP Pool & Simple Queue**: Blok alokasi subnet IP dan target queue untuk pelanggan.
2. **Profil Bandwidth**: Konfigurasi PPP Profile dengan rate-limit berbasis bps biner ($1\text{ Mbps} = 1.048.576\text{ bps}$).
3. **Layanan Pelanggan**: Akun PPP Secret (`ppp_username` berformat `{no_reg}_{NNNNN}`, password terenkripsi, remote-address untuk IP statik, dan status aktif/suspend).

Sebelumnya, perintah sinkronisasi tersebar di beberapa job terpisah (`MikrotikProvisionAllCommand`, `MikrotikSyncProfilesCommand`, dll.) tanpa satu pipeline orkestrasi terpusat. Hal ini berpotensi menimbulkan *race condition* atau error dependensi (misal PPP Secret gagal dibuat karena PPP Profile atau IP Pool belum tersinkronisasi di RouterOS). Selain itu, belum ada master scheduled job di `routes/console.php` untuk menjamin rekonsiliasi berkala terhadap *configuration drift*.

## Keputusan yang Diambil

1. **Strict Dependency Pipeline (Lengkap & Terurut)**:
   Proses provisi menyeluruh suatu router mengeksekusi pipeline berurutan secara terstruktur:
   - **Langkah 1: Resource & Connectivity Check** (`/system/resource/print`) untuk memvalidasi router online dan memperbarui metrik beban CPU/RAM/uptime.
   - **Langkah 2: Sinkronisasi IP Pool & Queue** (`/ip/pool`, `/queue/simple`) untuk seluruh pool yang terikat ke router tersebut.
   - **Langkah 3: Sinkronisasi Profil Bandwidth** (`/ppp/profile`) untuk seluruh profil di UNMS dengan rate-limit bps biner.
   - **Langkah 4: Provisi PPP Secret & IP Statik** (`/ppp/secret`) untuk seluruh layanan pelanggan aktif/suspend/proses yang terhubung ke router.
   - **Langkah 5: Penyelarasan Status & Pemutusan Sesi Aktif** (`disabled=yes` untuk status `suspend`/`berhenti`, dan `/ppp/active/remove` jika diperlukan).

2. **Mekanisme Tri-Trigger (Jadwal Terjadwal + Event-Driven + On-Demand)**:
   - **Scheduled Periodic Reconciliation** di `routes/console.php`: Menjalankan sinkronisasi dan rekonsiliasi otomatis harian pukul `03:00` subuh (`Schedule::command('mikrotik:provisi-router')->dailyAt('03:00');`) dan auto-recovery ringan pada interval ping router.
   - **Event-Driven Auto-Provisioning**: Perubahan pada Model (`IpPoolObserver`, `ProfilBandwidthObserver`, `HandleLayananStatusChangedListener`) otomatis mendispatch Queue Job spesifik ke antrean `mikrotik`.
   - **On-Demand (CLI & UI Dashboard)**: Menyediakan Artisan command terpadu `php artisan mikrotik:provisi-router {--router=} {--force} {--clean-orphans}` dan tombol aksi "Sinkronkan Semua" pada halaman manajemen router Livewire.

3. **Kebijakan Orphaned Secrets (Audit-Safe Default + Flag Opsional)**:
   - Secara default, akun PPP Secret di RouterOS yang tidak terdaftar di database UNMS hanya dicatat dalam laporan audit/warning log (*Safe Audit*).
   - Opsi pembersihan eksplisit (`--clean-orphans`) disediakan untuk menghapus secret tak terdaftar dengan perlindungan whitelist terhadap akun default/manajemen router.

4. **Resilience & Penanganan Router Offline**:
   - Jika router dalam status offline atau tidak merespons, proses sinkronisasi melakukan *graceful skip* dan mencatat kegagalan ke `MikrotikJobLog`.
   - Kegagalan satu router tidak menghentikan sinkronisasi router lainnya.
   - Saat job `mikrotik:ping` mendeteksi router kembali *Online*, sistem secara otomatis memicu job rekonsiliasi untuk router tersebut.

## Konsekuensi
- Mencegah error dependensi konfigurasi di RouterOS (`profile not found` atau `pool missing`).
- Menjamin konsistensi konfigurasi router (*drift-free*) meskipun terjadi restart atau perubahan manual di RouterOS.
- Audit trail dan status provisi setiap entitas terpantau rapi di `mikrotik_job_logs`.
- Pekerjaan sinkronisasi berjalan asynchronous di antrean (*queue*) sehingga tidak membebani antarmuka pengguna web.
