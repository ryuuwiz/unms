Sprint B5 — Monitoring & Logging Mikrotik untuk Superadmin
Konteks
Sprint B1 memindahkan pemanggilan getPppStatus() dari mount() ke wire:init, dan mengonfirmasi bahwa MikrotikService::getPppStatus() sudah mengisolasi setiap kegagalan koneksi per router/layanan di dalam dirinya sendiri (catch (Throwable $e), selalu return array terstruktur dengan router_online dan error_message). Itu menyelesaikan sisi tampilan (UI tidak pernah crash / satu layanan gagal tidak mengganggu layanan lain).

Tapi ada gap observability yang baru terlihat jelas dari investigasi Sprint B1: saat sebuah router gagal dihubungi, KEGAGALAN ITU TIDAK PERNAH TERCATAT DI MANA PUN — tidak ada baris log, tidak ada baris database. Satu-satunya jejaknya adalah $statusPpp['error_message'] yang muncul sesaat di layar staf yang kebetulan sedang membuka halaman pelanggan itu, lalu hilang begitu request selesai. Tidak ada cara bagi siapa pun untuk menjawab pertanyaan seperti "router mana yang paling sering gagal minggu ini?" atau "sejak kapan Router-Core-02 mulai timeout?".

Sprint ini muncul dari permintaan eksplisit: superadmin butuh fitur debug untuk monitor & logging detail atas panggilan Mikrotik ini.

Dependensi: Kerjakan setelah Sprint B1 selesai dan merge (supaya lazy-loading yang jadi sumber utama traffic ke getPppStatus() sudah stabil duluan, dan supaya kalau ada masalah performa setelah sprint ini, jelas sprint mana penyebabnya).

PENTING — status dokumen ini: Ini adalah dokumen perencanaan awal, BUKAN prompt siap-eksekusi seperti sprint-b1/b2/b3. Beberapa keputusan desain (lihat bagian "Keputusan yang masih perlu digrill" di bawah) sengaja belum diputuskan — belum melalui sesi grilling seperti Sprint B1. Jangan mulai coding dari dokumen ini langsung; buka sesi grilling baru dulu untuk menuntaskan keputusan-keputusan itu.

Tujuan sprint ini
Setiap panggilan getPppStatus() (minimal yang gagal — lihat poin 2 di "Keputusan yang masih perlu digrill") tercatat secara persisten: router, layanan terkait, waktu, hasil, pesan error, durasi percobaan koneksi.
Superadmin punya satu halaman khusus untuk melihat riwayat & pola kegagalan Mikrotik lintas router/pelanggan — bukan cuma status "saat ini" seperti yang ditampilkan di Pelanggan/Show.

Scope area (perkiraan awal — konfirmasi ulang di sesi implementasi setelah grilling)
app/Services/Mikrotik/MikrotikService.php — getPppStatus() perlu menulis satu entri log di setiap pemanggilan (di dalam try, dan di dalam catch).
Skema penyimpanan baru. Kandidat: tabel baru mikrotik_status_checks (bukan reuse mikrotik_job_logs langsung — job log itu didesain untuk operasi tulis/provisioning berbasis job queue dengan attempt_count dan finished_at; status check bersifat read-only dan berpotensi jauh lebih sering dipanggil, karakteristik volume & retensinya beda). Ini salah satu keputusan yang belum digrill — lihat di bawah.
Model baru (mis. MikrotikStatusCheckLog) kalau tabel baru yang dipilih.
Livewire page baru khusus superadmin (mis. app/Livewire/Admin/MikrotikMonitor.php + blade view), didaftarkan di kelompok navigasi "Administrasi" (istilah baku sesuai CONTEXT.md).
Otorisasi: reuse pola hasRole('super_admin') / gate yang sudah otomatis all-access untuk super_admin (AppServiceProvider.php:84) — kemungkinan tidak perlu Policy baru, tinggal middleware/route gate check di halaman ini.
Migration baru.
Test baru (unit untuk logging di MikrotikService, feature untuk halaman monitor + otorisasi).

Keputusan yang masih perlu digrill sebelum mulai coding
1. Reuse mikrotik_job_logs (tambah job_type baru semacam check_ppp_status) vs tabel terpisah — trade-off retensi & volume data (status check berpotensi jauh lebih sering dipanggil daripada job provisioning, terutama kalau nanti Sprint B2/B3 menambah polling berkala).
2. Log SETIAP pemanggilan getPppStatus() (termasuk yang sukses) atau HANYA yang gagal? Semua panggilan = data besar tapi bisa hitung success-rate/uptime % per router; hanya-gagal = data ringan tapi tidak bisa menghitung tingkat keberhasilan, hanya daftar insiden.
3. Retensi/pruning — perlu command Artisan terjadwal (mis. mikrotik:prune-status-checks)? Berapa lama data disimpan sebelum dihapus?
4. Bentuk halaman monitor — daftar dengan filter (router, layanan, rentang waktu, status) + pagination biasa, atau perlu tampilan realtime/live-tail?
5. Alerting — apakah superadmin perlu dinotifikasi otomatis (mis. lewat WhatsApp/WAHA seperti pola notifikasi Mikrotik lain di app/Jobs/Mikrotik/*Job.php yang mengirim ke role super_admin+noc) saat sebuah router gagal N kali berturut-turut, atau cukup pasif (staf membuka halaman saat curiga ada masalah)?
6. Sinkron atau async — apakah penulisan log dilakukan langsung di dalam getPppStatus() (risiko: kalau DB lambat/down bersamaan, ikut memperlambat request yang mungkin sudah lambat karena router down) atau didorong ke queue job terpisah (risiko: kompleksitas tambahan, delay antara kejadian dan tercatatnya log)?

Acceptance criteria (awal — akan disempurnakan saat sesi grilling/implementasi)
[ ] Superadmin bisa melihat riwayat percobaan status PPP per router/layanan, dengan filter minimal status (berhasil/gagal) dan rentang waktu.
[ ] Kegagalan router tercatat dengan pesan error asli dari MikrotikService (bukan pesan generik).
[ ] Halaman monitor hanya bisa diakses oleh peran super_admin (ada test yang membuktikan staf non-superadmin ditolak).
[ ] Penulisan log tidak menambah latency signifikan ke alur Pelanggan/Show yang sudah dioptimasi di Sprint B1 (perlu dibuktikan lewat test/benchmark, bukan asumsi).
[ ] Semua test baru dan existing lulus.

Yang TIDAK boleh dilakukan di sprint ini
Jangan ubah kontrak $pppStatuses atau blade Pelanggan/Show yang sudah stabil dari Sprint B1 — sprint ini murni menambah observability di sisi lain, bukan mengubah UI status realtime yang sudah ada.
Jangan implementasikan alerting WhatsApp (poin 5 di atas) kecuali sudah dikonfirmasi eksplisit di sesi grilling — anggap out of scope sampai diputuskan.
Jangan mulai coding sebelum "Keputusan yang masih perlu digrill" di atas dikonfirmasi user.

Setelah selesai
Laporkan:
Tabel/skema final yang dipakai (baru atau extend mikrotik_job_logs) dan alasannya.
Cakupan logging final (semua panggilan atau hanya kegagalan) dan alasannya.
Hasil pengukuran dampak latency terhadap Pelanggan/Show.
Hasil test run.
