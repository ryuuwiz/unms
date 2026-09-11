Sprint B2 — Cache Layer untuk getPppStatus() + Configurable Timeout
Konteks
Setiap kali halaman Pelanggan/Show dibuka (oleh admin manapun), getPppStatus() membuka koneksi live baru ke RouterOS. Kalau beberapa admin membuka halaman pelanggan yang sama dalam waktu berdekatan, ini membuka banyak koneksi mubazir ke router yang sama, dan RouterOS API punya batas jumlah sesi bersamaan yang cukup rendah.
Dependensi: Kerjakan setelah Sprint B1 selesai dan merge (supaya lazy-loading dan caching bekerja di atas fondasi yang sama, dan supaya kalau ada masalah lebih mudah diisolasi sprint mana penyebabnya).
Tujuan sprint ini:
Cache hasil getPppStatus() dengan TTL singkat, supaya request berdekatan tidak membuka koneksi baru.
Sediakan method refresh yang bypass cache untuk kebutuhan "refresh manual" oleh user.
Timeout koneksi RouterOS (saat ini hardcoded 3 detik) jadi configurable.
Scope file
app/Services/Mikrotik/MikrotikService.php (method getPppStatus(), tambahan method refreshPppStatus() kalau belum ada)
config/mikrotik.php (buat baru kalau belum ada — cek dulu apakah sudah ada file config Mikrotik sebelum membuat baru)
tests/Unit/Services/Mikrotik/MikrotikServiceCacheTest.php (buat baru)
Implementasi
1. Buat/update config/mikrotik.php
Kalau belum ada file config khusus Mikrotik, buat baru:
PHP<?php

return [
    'status_cache_ttl' => env('MIKROTIK_STATUS_CACHE_TTL', 20), // detik
    'status_timeout' => env('MIKROTIK_STATUS_TIMEOUT', 3), // detik
];




Kalau sudah ada file config serupa, tambahkan key ini ke file yang sudah ada, jangan duplikasi file config.
2. Refactor getPppStatus()
PHPpublic function getPppStatus(Router $router, string $username): array
{
    $cacheKey = "ppp-status:{$router->id}:{$username}";

    return Cache::remember(
        $cacheKey,
        now()->addSeconds(config('mikrotik.status_cache_ttl', 20)),
        fn () => $this->fetchLivePppStatus($router, $username)
    );
}

public function refreshPppStatus(Router $router, string $username): array
{
    $cacheKey = "ppp-status:{$router->id}:{$username}";

    Cache::forget($cacheKey);

    return $this->getPppStatus($router, $username);
}

private function fetchLivePppStatus(Router $router, string $username): array
{
    // Pindahkan logic asli getPppStatus() ke sini TANPA mengubah isinya,
    // hanya rename dan jadikan private.
    // ...
}




Penting: Logic di dalam fetchLivePppStatus() harus identik dengan logic getPppStatus() yang lama — sprint ini hanya menambah lapisan cache di luarnya, bukan mengubah cara fetch data dari router. Jangan refactor logic internal fetch di sprint yang sama dengan menambah cache, supaya kalau ada bug setelah sprint ini, jelas apakah penyebabnya di lapisan cache atau logic fetch.
3. Ganti timeout hardcoded
Cari semua tempat di MikrotikService.php yang memanggil getClient($router, 3) (atau angka literal timeout lain terkait status check) dan ganti:
PHP$client = $this->getClient($router, config('mikrotik.status_timeout', 3));




Catatan: kalau ada pemanggilan getClient() lain di method yang bukan terkait status check (misalnya provisioning, yang mungkin butuh timeout berbeda/lebih panjang), jangan ikut diubah di sprint ini kecuali sudah dikonfirmasi itu juga timeout yang sama. Cek satu per satu, jangan search-replace membabi buta.
4. Update Livewire component untuk pakai refreshPppStatus()
Kalau dari Sprint B1 belum ada tombol "refresh manual" per layanan, tambahkan di sprint ini:
PHP// di Show.php
public function refreshSatuStatus(int $layananId): void
{
    $layanan = $this->pelanggan->layanans->firstWhere('id', $layananId);

    if (! $layanan) {
        return;
    }

    try {
        $this->pppStatuses[$layananId] = app(MikrotikService::class)
            ->refreshPppStatus($layanan->router, $layanan->ppp_username);
    } catch (MikrotikConnectionException $e) {
        $this->pppStatuses[$layananId] = [
            'error' => true,
            'message' => 'Router tidak dapat dihubungi',
            'online' => null,
        ];
    }
}




Tambahkan tombol kecil (ikon refresh) di sebelah status tiap layanan di blade view yang memanggil wire:click="refreshSatuStatus({{ $layanan->id }})".
Test yang harus dibuat
File: tests/Unit/Services/Mikrotik/MikrotikServiceCacheTest.php
PHPit('getPppStatus hanya memanggil client sekali untuk dua request dalam TTL yang sama', function () {
    $router = Router::factory()->create();

    $mockClient = /* setup spy/mock yang menghitung berapa kali dipanggil, ikuti pola mocking existing di test MikrotikService lain */;

    $service = new MikrotikService(/* inject sesuai constructor */);

    $result1 = $service->getPppStatus($router, 'user123');
    $result2 = $service->getPppStatus($router, 'user123');

    expect($result1)->toBe($result2);
    // assert client hanya dipanggil 1x, bukan 2x
});

it('refreshPppStatus bypass cache dan memanggil client lagi', function () {
    $router = Router::factory()->create();

    $service = new MikrotikService(/* ... */);

    $service->getPppStatus($router, 'user123'); // populate cache
    $service->refreshPppStatus($router, 'user123'); // harus fetch ulang

    // assert client dipanggil 2x total (1 dari getPppStatus, 1 dari refreshPppStatus)
});

it('cache key berbeda untuk router atau username berbeda', function () {
    $routerA = Router::factory()->create();
    $routerB = Router::factory()->create();

    $service = new MikrotikService(/* ... */);

    $service->getPppStatus($routerA, 'user123');
    $service->getPppStatus($routerB, 'user123');

    // assert client dipanggil 2x (tidak collide walau username sama, karena router beda)
});

it('status_timeout dari config dipakai saat membuka koneksi', function () {
    config(['mikrotik.status_timeout' => 7]);

    // assert getClient dipanggil dengan timeout 7, bukan hardcoded 3
    // (sesuaikan cara assert dengan bagaimana getClient() bisa di-spy)
});




Acceptance criteria

[ ] config/mikrotik.php ada dengan key status_cache_ttl dan status_timeout, bisa dioverride lewat .env.

[ ] getPppStatus() memakai Cache::remember(), tidak lagi selalu fetch live.

[ ] refreshPppStatus() tersedia dan benar-benar bypass cache.

[ ] Timeout tidak lagi hardcoded, memakai config('mikrotik.status_timeout').

[ ] Tombol refresh manual per layanan tersedia di UI (kalau belum ada dari sprint sebelumnya).

[ ] Semua test baru lulus, test existing tidak regresi.

[ ] .env.example diupdate dengan MIKROTIK_STATUS_CACHE_TTL dan MIKROTIK_STATUS_TIMEOUT (kalau file ini ada di repo).
Yang TIDAK boleh dilakukan di sprint ini

Jangan ubah logic internal fetchLivePppStatus() (dulu getPppStatus()) — pindahkan apa adanya.

Jangan ubah timeout untuk operasi Mikrotik lain (provisioning, ping, dll) kecuali dikonfirmasi memang memakai konstanta yang sama.

Jangan implementasikan Sprint B3 (batch query) di sprint ini — tetap terpisah.
Setelah selesai
Laporkan:
Apakah config/mikrotik.php baru dibuat atau ditambahkan ke file existing.
Nilai TTL default yang dipakai dan alasan (20 detik sesuai rekomendasi, atau nilai lain).
Konfirmasi logic internal fetch tidak berubah (hanya dipindah/direname).
Hasil test run.



