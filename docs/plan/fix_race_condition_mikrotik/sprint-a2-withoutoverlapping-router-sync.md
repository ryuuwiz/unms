Sprint A2 — WithoutOverlapping pada Job Sinkronisasi/Recovery Router
Konteks
Sprint A1 sudah menambahkan ShouldBeUnique pada job per-secret (provision/update/enable/disable satu layanan). Sprint ini menangani level yang lebih luas: job yang melakukan full-router sync/recovery (memproses semua layanan aktif di satu router sekaligus, misalnya lewat autoRecoverPppSecrets() atau provisionRouterFull() di MikrotikService). Job ini bisa overlap dengan job per-secret dari A1 kalau keduanya menyentuh router yang sama di waktu bersamaan, menyebabkan race yang sama di level yang lebih besar.
Dependensi: Kerjakan setelah Sprint A1 selesai dan merge.
Tujuan sprint ini: Cegah job full-router-sync berjalan bersamaan dengan job lain (baik sesama full-sync maupun job per-secret) untuk router yang sama.
Langkah 0 — Investigasi (WAJIB sebelum mengedit apapun)
Nama file pasti untuk job ini belum dikonfirmasi. Sebelum membuat perubahan:
Cari di app/Jobs/Mikrotik/ dan app/Console/Commands/ untuk kode yang memanggil method provisionRouterFull() atau autoRecoverPppSecrets() di app/Services/Mikrotik/MikrotikService.php.
Cek juga app/Console/Kernel.php atau routes/console.php (tergantung versi Laravel) untuk melihat apakah job/command ini dijadwalkan (schedule()), dan seberapa sering.
Laporkan nama file yang ditemukan sebelum lanjut ke langkah berikutnya — jangan menebak nama file dan langsung mengedit.
Scope file (setelah investigasi di atas mengonfirmasi nama file)
Job/Command hasil investigasi Langkah 0 (kemungkinan app/Jobs/Mikrotik/ProvisionRouterJob.php atau sejenisnya)
tests/Feature/Jobs/RouterSyncOverlapTest.php (buat baru)
Jangan menyentuh MikrotikService.php di sprint ini — sprint ini hanya menambah middleware locking di level job, bukan mengubah logic sinkronisasi itu sendiri.
Implementasi
1. Tambahkan middleware WithoutOverlapping
PHPuse Illuminate\Queue\Middleware\WithoutOverlapping;

class ProvisionRouterJob implements ShouldQueue
{
    // ... existing code ...

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("mikrotik-router-{$this->router->id}"))
                ->releaseAfter(180)   // job yang gagal dapat lock akan dicoba lagi setelah 180 detik
                ->expireAfter(600),   // safety net: lock otomatis lepas setelah 600 detik walau job hang
        ];
    }
}




Sesuaikan $this->router->id dengan property yang benar-benar ada di job (verifikasi dari constructor, sama seperti catatan di Sprint A1).
2. Konsistensi cache key dengan Sprint A1
Prefix mikrotik-router-{id} di sini sengaja mirip dengan pola uniqueId() dari Sprint A1 (router:{router_id}:layanan:{layanan_id}), tapi ini dua mekanisme locking yang berbeda (ShouldBeUnique vs WithoutOverlapping) dan tidak otomatis saling memblokir satu sama lain. Untuk sprint ini, cukup pastikan job full-sync tidak overlap dengan sesama dirinya sendiri (dua full-sync job untuk router yang sama). Jangan mencoba membuat job per-secret dan job full-sync saling memblokir di sprint ini — itu memerlukan desain locking terpadu yang sengaja dipisah ke sprint lanjutan supaya tidak memperbesar scope sprint ini.
3. Pastikan driver cache mendukung WithoutOverlapping
Sama seperti A1 — butuh cache driver yang mendukung atomic lock (Redis/Memcached). Kalau Sprint A1 sudah memverifikasi ini, tidak perlu diulang, cukup catat di laporan akhir bahwa asumsi ini dibawa dari A1.
Test yang harus dibuat
File: tests/Feature/Jobs/RouterSyncOverlapTest.php
PHPit('mencegah dua job full-router-sync berjalan bersamaan untuk router yang sama', function () {
    $router = Router::factory()->create();

    // Dispatch job pertama, jangan langsung dijalankan sampai selesai
    // (gunakan approach yang sesuai dengan queue driver aplikasi:
    // database queue -> assert baris kedua tetap 'pending'/di-release,
    // redis queue -> assert lock key middleware WithoutOverlapping aktif)

    dispatch(new ProvisionRouterJob($router));
    dispatch(new ProvisionRouterJob($router));

    // Assert: hanya satu instance yang benar-benar berjalan pada satu waktu.
    // Sesuaikan assertion dengan mekanisme testing WithoutOverlapping yang didukung
    // versi Laravel yang dipakai (cek apakah ada helper resmi seperti
    // Illuminate\Support\Testing\Fakes atau assertion queue bawaan).
});

it('mengizinkan job full-router-sync untuk router berbeda berjalan bersamaan', function () {
    $routerA = Router::factory()->create();
    $routerB = Router::factory()->create();

    dispatch(new ProvisionRouterJob($routerA));
    dispatch(new ProvisionRouterJob($routerB));

    // Assert keduanya bisa diproses tanpa saling menunggu
});





Catatan untuk agent: WithoutOverlapping middleware relatif sulit diuji secara terisolasi tanpa benar-benar menjalankan queue worker. Kalau approach unit test murni sulit meyakinkan, buat minimal 1 test yang memverifikasi middleware() method mengembalikan instance WithoutOverlapping dengan key yang benar (test structural), ditambah catatan di PR description bahwa verifikasi perilaku overlap sebenarnya perlu diuji manual di staging dengan menjalankan 2 worker bersamaan.



Acceptance criteria

[ ] Nama file job/command sudah dikonfirmasi lewat investigasi (Langkah 0), dilaporkan sebelum mengedit.

[ ] middleware() method ditambahkan dengan WithoutOverlapping keyed by router_id.

[ ] releaseAfter dan expireAfter diset sesuai rekomendasi (atau nilai lain dengan justifikasi).

[ ] Test baru dibuat, minimal test structural untuk middleware() kalau test perilaku penuh tidak feasible.

[ ] Tidak ada perubahan pada MikrotikService.php.
Yang TIDAK boleh dilakukan di sprint ini

Jangan ubah logic autoRecoverPppSecrets()/provisionRouterFull() — itu Sprint A5.

Jangan coba membuat locking terpadu antara job per-secret (A1) dan job full-sync (A2) — di luar scope, catat sebagai potential follow-up di PR description saja.
Setelah selesai
Laporkan:
Nama file job/command yang dikonfirmasi dan hasil investigasi jadwal (apakah job ini dijalankan terjadwal, dan seberapa sering).
Nilai releaseAfter/expireAfter final.
Batasan testing yang ditemukan (kalau perilaku overlap sebenarnya tidak bisa diuji otomatis, jelaskan kenapa).



