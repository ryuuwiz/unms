Sprint A1 — ShouldBeUnique pada Job PPP Mikrotik
Konteks
Aplikasi ini adalah sistem billing ISP berbasis Laravel dengan integrasi Mikrotik RouterOS untuk provisioning PPPoE. Saat ini ada race condition: beberapa job yang memodifikasi PPP secret/profile pada router yang sama bisa berjalan bersamaan (misalnya admin klik "provision" dua kali, atau job retry menumpuk dengan job baru), menyebabkan duplicate secret/profile di RouterOS akibat pola find-then-create yang tidak atomik di level router.
Tujuan sprint ini: Mencegah dua job yang menargetkan router_id + layanan_id yang sama berjalan bersamaan, dengan menerapkan ShouldBeUnique pada job-job terkait.
Scope — HANYA file berikut
Jangan menyentuh file di luar daftar ini kecuali diminta secara eksplisit.
app/Jobs/Mikrotik/ProvisionPppoeAccountJob.php
app/Jobs/Mikrotik/UpdatePppoeProfileJob.php
app/Jobs/Mikrotik/EnablePppoeAccountJob.php
app/Jobs/Mikrotik/DisablePppoeAccountJob.php
app/Jobs/Mikrotik/CleanupPppSecretOnOldRouterJob.php
tests/Feature/Jobs/PppoeJobUniquenessTest.php (buat baru)

Catatan: jika salah satu nama file di atas tidak persis sama di codebase, cari dulu file job yang setara (cari di app/Jobs/Mikrotik/ untuk job yang memanggil method MikrotikService terkait create/update/enable/disable secret), lalu konfirmasi nama file sebelum lanjut mengedit.

Langkah implementasi
1. Cek constructor tiap job
Sebelum mengubah apapun, baca constructor masing-masing job untuk memastikan property yang tersedia (kemungkinan besar berupa LayananPelanggan $layanan atau int $layananId + int $routerId terpisah). Sesuaikan uniqueId() di bawah dengan property yang benar-benar ada — jangan asumsikan nama property tanpa verifikasi.
2. Tambahkan ShouldBeUnique ke tiap job
Untuk masing-masing dari 5 job di atas:
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProvisionPppoeAccountJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Berapa lama (detik) job dianggap "unique" sejak di-dispatch.
     * Harus lebih besar dari estimasi durasi eksekusi terpanjang job ini.
     */
    public int $uniqueFor = 300;

    // ... constructor & properties existing tetap dipertahankan ...

    /**
     * Key unik: satu job aktif per kombinasi router + layanan.
     */
    public function uniqueId(): string
    {
        return "router:{$this->layanan->router_id}:layanan:{$this->layanan->id}";
    }
}

Sesuaikan uniqueId() per job:
Kalau job menerima LayananPelanggan $layanan di constructor → pakai $this->layanan->router_id dan $this->layanan->id.
Kalau job menerima ID mentah (int $layananId, int $routerId) → pakai property tersebut langsung, jangan query ulang ke database di dalam uniqueId() (method ini dipanggil sebelum job masuk queue, harus ringan).
3. Nilai $uniqueFor per job
Sesuaikan nilai default berikut dengan observasi durasi job aktual di Horizon jika tersedia; kalau belum ada datanya, gunakan angka default ini dulu:
Job	$uniqueFor (detik)
ProvisionPppoeAccountJob	300
UpdatePppoeProfileJob	300
EnablePppoeAccountJob	120
DisablePppoeAccountJob	120
CleanupPppSecretOnOldRouterJob	300
4. Pastikan driver cache mendukung locking
ShouldBeUnique butuh cache driver yang mendukung atomic lock (Redis, Memcached, DynamoDB — bukan file atau array di production). Cek config/cache.php dan .env untuk CACHE_DRIVER/CACHE_STORE — kalau aplikasi sudah pakai Redis untuk Horizon (kemungkinan besar iya, karena laravel/horizon sudah terpasang), ini seharusnya sudah otomatis kompatibel. Laporkan di akhir jika ternyata driver cache saat ini tidak mendukung locking, jangan diam-diam mengubah .env.
Test yang harus dibuat
File: tests/Feature/Jobs/PppoeJobUniquenessTest.php
Gunakan Queue::fake() tidak cukup untuk menguji ShouldBeUnique secara nyata (fake queue tidak menjalankan mekanisme unique lock). Pendekatan yang benar:
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Bus;

it('mencegah dua ProvisionPppoeAccountJob berjalan bersamaan untuk layanan yang sama', function () {
    $layanan = LayananPelanggan::factory()->create();

    $job1 = new ProvisionPppoeAccountJob($layanan);
    $job2 = new ProvisionPppoeAccountJob($layanan);

    expect($job1->uniqueId())->toBe($job2->uniqueId());

    // Simulasikan job pertama sudah "locked" (seperti yang dilakukan Laravel saat dispatch)
    $lockKey = 'laravel_unique_job:' . get_class($job1) . ':' . $job1->uniqueId();

    dispatch($job1);
    // Verifikasi lock benar-benar tercipta di cache
    expect(Cache::has($lockKey))->toBeTrue();

    // Dispatch job kedua dengan uniqueId sama seharusnya tidak menambah entry baru ke queue
    // (Laravel akan skip dispatch kedua secara silent jika lock masih aktif)
    dispatch($job2);

    // Assert hanya ada 1 job di queue/table jobs untuk kombinasi ini
    // (sesuaikan assertion dengan queue driver yang dipakai — database atau redis)
});


Sesuaikan detail assertion di atas dengan queue driver aktual aplikasi (cek QUEUE_CONNECTION di .env). Kalau pakai database driver, assert langsung ke tabel jobs. Kalau pakai redis, assert ke cache lock key seperti contoh, atau gunakan helper testing bawaan Laravel untuk unique jobs jika tersedia di versi framework yang dipakai.

Tambahkan juga test terpisah untuk memverifikasi uniqueId() berbeda untuk layanan yang berbeda (memastikan tidak over-blocking job yang seharusnya independen):
it('mengizinkan job berjalan bersamaan untuk layanan yang berbeda', function () {
    $layananA = LayananPelanggan::factory()->create();
    $layananB = LayananPelanggan::factory()->create();

    $jobA = new ProvisionPppoeAccountJob($layananA);
    $jobB = new ProvisionPppoeAccountJob($layananB);

    expect($jobA->uniqueId())->not->toBe($jobB->uniqueId());
});

Acceptance criteria
[ ] Kelima job mengimplementasikan ShouldBeUnique.
[ ] uniqueId() menghasilkan key yang sama untuk job dengan router_id + layanan_id yang sama, dan berbeda untuk kombinasi lain.
[ ] $uniqueFor diset sesuai tabel di atas (atau nilai lain dengan justifikasi di PR description).
[ ] Test baru di PppoeJobUniquenessTest.php lulus.
[ ] Test existing yang sudah ada untuk job-job ini (jika ada) tetap lulus — jalankan full test suite untuk folder tests/Feature/Jobs/ dan tests/Unit/ yang menyinggung job-job ini, bukan cuma test baru.
[ ] Tidak ada perubahan pada app/Services/Mikrotik/MikrotikService.php di sprint ini (di luar scope, akan ditangani di sprint lain).
Yang TIDAK boleh dilakukan di sprint ini
Jangan ubah logic bisnis di dalam handle() masing-masing job — sprint ini murni menambahkan lapisan uniqueness, bukan mengubah apa yang dikerjakan job.
Jangan ubah MikrotikService.php atau file terkait autoRecoverPppSecrets()/provisionRouterFull() — itu scope Sprint A2.
Jangan ubah config/cache.php atau .env tanpa melaporkan dulu jika ditemukan driver cache tidak kompatibel.
Setelah selesai
Laporkan ringkasan berikut di akhir:
File apa saja yang benar-benar diubah (harus persis sesuai scope atau jelaskan kalau ada penyesuaian nama file).
Nilai $uniqueFor final yang dipakai per job dan alasannya kalau berbeda dari tabel rekomendasi.
Driver cache yang terdeteksi dan apakah kompatibel dengan ShouldBeUnique.
Hasil test run (pass/fail, termasuk test existing yang mungkin terpengaruh).
