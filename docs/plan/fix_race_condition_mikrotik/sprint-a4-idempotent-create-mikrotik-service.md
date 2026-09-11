Sprint A4 — Idempotent Create/Update di MikrotikService
Konteks
MikrotikService::createOrUpdatePppoeSecret() dan ensurePppProfile() memakai pola find-then-create terhadap RouterOS. Meskipun Sprint A1-A2 sudah mengurangi race condition lewat locking di level job, masih mungkin ada kondisi tepi (misalnya retry job setelah timeout padahal create sebelumnya sebenarnya berhasil di sisi router) yang membuat RouterOS menolak create dengan error "entry sudah ada". Saat ini kemungkinan besar error ini menyebabkan job gagal total, padahal secara logis kondisi akhir yang diinginkan (secret/profile ada dengan konfigurasi benar) sudah tercapai.
Dependensi: Sebaiknya dikerjakan setelah Sprint A1 merge (locking sudah mengurangi frekuensi kasus ini, jadi fix ini jadi safety net, bukan fix utama).
Tujuan sprint ini: Kalau create gagal karena entry sudah ada, treat sebagai kondisi normal — lanjutkan ke query-existing + update, bukan lempar exception ke job.
Langkah 0 — Investigasi WAJIB: kumpulkan bukti nyata sebelum ubah kode
Ini bagian paling penting dari sprint ini. Jangan menebak pesan error atau exception class yang dilempar RouterOS API saat create gagal karena duplikat.
Cari di codebase penggunaan package evilfreelancer/routeros-api-php — lihat exception class apa yang dilempar saat command API gagal (biasanya sesuatu seperti RouterOS\Exceptions\...).
Cari di log aplikasi (Sentry, log file, atau storage/logs/laravel.log kalau tersedia contoh historis) untuk pesan error asli RouterOS saat mencoba membuat secret/profile yang namanya sudah ada. Pesan RouterOS untuk kasus ini biasanya mengandung frasa seperti "already have such entry" atau "failure: already have..." — tapi ini harus diverifikasi dari data nyata aplikasi ini, bukan diasumsikan dari pengetahuan umum RouterOS, karena versi RouterOS/package API bisa mempengaruhi format pesan persis.
Kalau tidak ada log/contoh nyata yang tersedia, laporkan ini secara eksplisit sebelum melanjutkan, dan sarankan opsi: (a) tunda sprint ini sampai ada contoh nyata dari staging/produksi, atau (b) lanjutkan dengan pendekatan generik yang catch semua exception dari API call tersebut lalu re-query untuk verifikasi state aktual (lebih robust tapi sedikit lebih lambat karena selalu ada extra query), sebagai fallback.
Scope file
app/Services/Mikrotik/MikrotikService.php (method createOrUpdatePppoeSecret(), ensurePppProfile())
tests/Unit/Services/Mikrotik/MikrotikServiceIdempotencyTest.php (buat baru)
Implementasi
Pendekatan A — kalau pesan error spesifik berhasil diidentifikasi (Langkah 0.2 berhasil)
PHPpublic function createOrUpdatePppoeSecret(LayananPelanggan $layanan, /* ...params lain... */): array
{
    try {
        return $this->doCreateSecret($layanan, /* ... */);
    } catch (SpecificRouterOsException $e) {
        if (str_contains(strtolower($e->getMessage()), 'already have such entry')) {
            // Kondisi akhir yang diinginkan sudah tercapai (mungkin) — konvergensi, bukan gagal.
            Log::info('PPP secret sudah ada di router, melanjutkan ke update.', [
                'layanan_id' => $layanan->id,
                'router_id' => $layanan->router_id,
            ]);

            return $this->updateExistingSecret($layanan, /* ... */);
        }

        throw $e; // Error lain tetap harus dilempar, jangan ditelan semua.
    }
}




Pendekatan B — fallback generik (kalau Langkah 0 tidak menghasilkan pesan error pasti)
Query dulu sebelum create (bukan hanya create-lalu-catch), untuk mengurangi ketergantungan pada matching pesan error string yang rapuh:
PHPpublic function createOrUpdatePppoeSecret(LayananPelanggan $layanan, /* ... */): array
{
    $existing = $this->findSecretByUsername($layanan->ppp_username);

    if ($existing !== null) {
        return $this->updateExistingSecret($layanan, $existing, /* ... */);
    }

    try {
        return $this->doCreateSecret($layanan, /* ... */);
    } catch (\Throwable $e) {
        // Race sempit: entry dibuat oleh proses lain di antara query dan create di atas.
        // Re-query sekali untuk verifikasi, baru menyerah kalau memang benar-benar gagal.
        $existing = $this->findSecretByUsername($layanan->ppp_username);

        if ($existing !== null) {
            Log::warning('Create gagal tapi entry ternyata sudah ada (race sempit), melanjutkan ke update.', [
                'layanan_id' => $layanan->id,
            ]);

            return $this->updateExistingSecret($layanan, $existing, /* ... */);
        }

        throw $e;
    }
}




Catatan: Pendekatan B lebih robust terhadap perubahan format pesan error API, tapi menambah 1 query ekstra di jalur normal (query-dulu, bukan cuma saat gagal). Untuk ensurePppProfile(), terapkan pola yang sama (query dulu sebelum create juga merupakan praktik yang lebih aman dibanding pure find-then-create-catch).
Pilih salah satu pendekatan berdasarkan hasil Langkah 0 — laporkan pendekatan mana yang dipakai dan kenapa.
Penting: jangan menelan semua exception secara membabi buta
Perubahan ini harus tetap melempar exception untuk kasus error lain (router unreachable, auth gagal, dll) — hanya kasus "entry sudah ada" yang di-treat sebagai sukses. Kalau pendekatan yang dipilih menangkap \Throwable generik (Pendekatan B), pastikan re-throw tetap terjadi kalau re-query juga tidak menemukan entry (artinya error asli memang bukan soal duplikat).
Test yang harus dibuat
File: tests/Unit/Services/Mikrotik/MikrotikServiceIdempotencyTest.php
Gunakan mock/fake client RouterOS (cek bagaimana test existing untuk MikrotikService sudah memock client — ikuti pola yang sama, jangan buat pendekatan mocking baru yang beda gaya).
PHPit('treats duplicate-entry error sebagai sukses dan lanjut update', function () {
    $mockClient = /* ... setup mock yang melempar exception "already have such entry" saat create dipanggil ... */;
    $mockClient->shouldReceive(/* method query untuk existing entry */)
        ->andReturn(/* data entry yang sudah ada */);

    $service = new MikrotikService($mockClient /* atau cara inject sesuai constructor asli */);

    $result = $service->createOrUpdatePppoeSecret($layanan, /* ... */);

    expect($result)->not->toBeNull();
    // assert tidak ada exception yang dilempar keluar
});

it('tetap melempar exception untuk error yang bukan soal duplikat', function () {
    $mockClient = /* ... setup mock yang melempar exception connection timeout, bukan duplikat ... */;

    $service = new MikrotikService($mockClient);

    expect(fn () => $service->createOrUpdatePppoeSecret($layanan, /* ... */))
        ->toThrow(/* exception class yang sesuai */);
});




Acceptance criteria

[ ] Langkah 0 selesai dan dilaporkan (bukti error message asli ditemukan, atau alasan kenapa fallback generik dipakai).

[ ] createOrUpdatePppoeSecret() dan ensurePppProfile() tidak lagi gagal total saat entry sudah ada — melanjutkan ke update.

[ ] Error lain (bukan soal duplikat) tetap dilempar sebagai exception, tidak ditelan.

[ ] Test baru lulus, mengcover kedua kasus (duplikat → sukses lanjut update; error lain → tetap throw).

[ ] Test existing untuk MikrotikService tetap lulus.
Yang TIDAK boleh dilakukan di sprint ini

Jangan ubah autoRecoverPppSecrets() — itu Sprint A5.

Jangan ubah job-job di app/Jobs/Mikrotik/ — sprint ini hanya menyentuh MikrotikService.php.

Jangan menelan (silent catch) semua jenis exception tanpa filtering — ini akan menyembunyikan bug nyata (misalnya router benar-benar unreachable) dan membuat debugging jadi lebih sulit di masa depan.
Setelah selesai
Laporkan:
Hasil Langkah 0 — pesan error/exception class yang ditemukan (atau konfirmasi kalau memakai fallback generik Pendekatan B).
Pendekatan mana (A atau B) yang akhirnya dipakai dan alasannya.
Hasil test run.



