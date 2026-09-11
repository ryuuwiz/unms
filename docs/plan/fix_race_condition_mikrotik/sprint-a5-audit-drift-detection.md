Sprint A5 — Audit & Fix Drift Detection di autoRecoverPppSecrets
Peringatan tingkat risiko
Sprint ini menyentuh logic yang menentukan apakah sebuah PPP secret dianggap "menyimpang" (drift) dari konfigurasi yang seharusnya, dan berpotensi memicu disable/enable ulang secret tersebut. Kalau logic perbandingan salah, ini bisa memutus koneksi pelanggan aktif secara tidak perlu di produksi. Sprint ini tidak disarankan dikerjakan penuh-otomatis oleh agent tanpa review manual — gunakan sebagai pair-programming (agent menulis, manusia review tiap perubahan sebelum merge), dan jangan deploy ke produksi tanpa dry-run.
Dependensi: Independen secara teknis dari sprint lain, tapi disarankan dikerjakan paling akhir dari Track A karena risikonya paling tinggi dan butuh data produksi nyata.
Langkah 0 — Kumpulkan data nyata SEBELUM menyentuh kode
Ini adalah bagian terpenting dari sprint ini. Jangan mulai edit apapun sebelum langkah ini selesai.
Ambil contoh response asli dari /ppp/secret/print pada router produksi (bukan dari dokumentasi umum RouterOS, tapi output nyata dari router yang dipakai aplikasi ini) — minta output ini disertakan dalam prompt ke agent, dalam bentuk array/JSON. Perhatikan khusus field remote-address dan local-address — apakah formatnya mengandung suffix CIDR (/32) atau tidak, apakah field kosong direpresentasikan sebagai string kosong atau tidak ada key sama sekali.
Ambil juga cara aplikasi ini menghasilkan nilai yang seharusnya (dari resolveRemoteAddress()/resolveLocalAddress() atau nama method setara — cari nama pasti di MikrotikService.php) untuk kasus yang sama, supaya bisa dibandingkan format keduanya berdampingan.
Kalau memungkinkan, ambil juga log historis (Sentry atau log lain) dari kejadian autoRecoverPppSecrets yang pernah men-trigger disable/enable — untuk melihat pola drift yang selama ini terdeteksi, apakah polanya masuk akal (benar-benar drift) atau mencurigakan (kemungkinan false-positive akibat format mismatch).
Jangan lanjut ke langkah berikutnya sampai data di atas tersedia. Kalau tidak ada akses ke data produksi, laporkan ini dan tunda sprint sampai data tersedia — mengerjakan sprint ini dengan asumsi/tebakan format data adalah risiko yang tidak sepadan mengingat dampaknya ke pelanggan aktif.
Scope file (setelah Langkah 0 selesai)
app/Services/Mikrotik/MikrotikService.php (method autoRecoverPppSecrets(), resolveRemoteAddress(), resolveLocalAddress(), atau nama setara — konfirmasi nama pasti dari kode)
tests/Unit/Services/Mikrotik/PppSecretDriftDetectionTest.php (buat baru)
Pendekatan implementasi — TDD, test dulu baru fix
1. Tulis test yang menangkap masalah SEBELUM memperbaiki apapun
Menggunakan data nyata dari Langkah 0, tulis test yang gagal terlebih dahulu (membuktikan bug false-positive drift benar-benar ada dengan data nyata):
PHPit('tidak menganggap drift ketika format alamat berbeda representasi tapi secara logis sama', function () {
    // Data ini HARUS dari contoh nyata Langkah 0, bukan data karangan.
    $responseDariRouter = [
        'remote-address' => '10.20.30.5/32', // contoh — ganti dengan data nyata
        // ...
    ];

    $nilaiYangDiharapkanAplikasi = '10.20.30.5'; // contoh — ganti dengan hasil resolveRemoteAddress() nyata

    $service = app(MikrotikService::class);

    expect($service->isAddressDrifted($responseDariRouter['remote-address'], $nilaiYangDiharapkanAplikasi))
        ->toBeFalse(); // Harusnya dianggap SAMA, bukan drift
});

it('tetap mendeteksi drift ketika alamat benar-benar berbeda', function () {
    expect($service->isAddressDrifted('10.20.30.5', '10.20.30.99'))
        ->toBeTrue(); // Ini kasus drift yang sesungguhnya, harus tetap terdeteksi
});

it('memperlakukan null dan string kosong sebagai setara untuk field alamat', function () {
    expect($service->isAddressDrifted(null, ''))
        ->toBeFalse();
});





Catatan: nama method isAddressDrifted() di atas contoh — sesuaikan dengan nama method comparison yang benar-benar ada/akan diekstrak dari autoRecoverPppSecrets(). Kalau logic comparison saat ini inline di dalam autoRecoverPppSecrets() (bukan method terpisah), ekstrak dulu jadi method terpisah yang bisa ditest secara unit sebelum menulis test di atas — jangan test lewat method besar autoRecoverPppSecrets() langsung kalau itu butuh setup koneksi router penuh untuk dites.



2. Perbaiki logic normalisasi sampai test hijau
Kemungkinan perbaikan yang dibutuhkan (sesuaikan dengan temuan nyata dari Langkah 0, jangan asumsikan semua ini berlaku tanpa verifikasi):
PHPprivate function normalizeAddressForComparison(?string $address): ?string
{
    if ($address === null || $address === '') {
        return null;
    }

    // Strip suffix CIDR kalau ada (misal "/32") sebelum dibandingkan
    return str_contains($address, '/')
        ? strtok($address, '/')
        : $address;
}

private function isAddressDrifted(?string $fromRouter, ?string $expected): bool
{
    return $this->normalizeAddressForComparison($fromRouter)
        !== $this->normalizeAddressForComparison($expected);
}




Sesuaikan detail normalisasi berdasarkan temuan nyata di Langkah 0 — bisa jadi ada kasus lain selain CIDR suffix (misalnya leading/trailing whitespace, case sensitivity kalau ada hostname, dll) yang baru ketahuan dari data produksi nyata.
3. Refactor autoRecoverPppSecrets() untuk memakai method comparison yang sudah ditest
Ganti comparison inline (kalau ada) dengan pemanggilan method isAddressDrifted() yang baru, supaya logic yang sudah diverifikasi lewat unit test benar-benar dipakai di jalur eksekusi asli.
Langkah tambahan — Dry-run mode (sangat disarankan, bagian dari sprint ini)
Sebelum perbaikan ini dipakai di produksi untuk memutuskan disable/enable sungguhan, tambahkan mode dry-run pada command/job yang menjalankan autoRecoverPppSecrets():
PHPpublic function autoRecoverPppSecrets(Router $router, bool $dryRun = false): array
{
    // ... deteksi drift seperti biasa ...

    if ($isDrifted) {
        if ($dryRun) {
            Log::info('DRY RUN: akan memperbaiki drift pada secret', [
                'router_id' => $router->id,
                'username' => $secret['name'],
                'from_router' => $fromRouter,
                'expected' => $expected,
            ]);

            continue; // Tidak benar-benar disable/enable saat dry run
        }

        // ... logic disable/enable asli ...
    }
}




Tambahkan opsi --dry-run pada Artisan command terkait (kalau dijalankan via command) supaya tim bisa menjalankan ini di produksi dulu untuk melihat berapa banyak "drift" yang terdeteksi dengan logic baru, tanpa benar-benar mengubah apapun di router — verifikasi manual dulu bahwa jumlah dan jenis drift yang terdeteksi masuk akal sebelum mengaktifkan mode nyata.
Acceptance criteria

[ ] Langkah 0 selesai — data nyata dari router produksi didokumentasikan dalam PR description (bisa disamarkan/anonimkan sebagian kalau perlu, tapi format struktur data tetap harus asli).

[ ] Test ditulis dengan data nyata tersebut, dan terbukti gagal sebelum fix (dokumentasikan ini di PR — commit terpisah "test: capture false-positive drift bug" sebelum commit fix, kalau workflow git memungkinkan).

[ ] Logic normalisasi diperbaiki, semua test lulus.

[ ] autoRecoverPppSecrets() di-refactor untuk memakai method comparison yang sudah ditest secara terpisah.

[ ] Mode --dry-run ditambahkan dan berfungsi (tidak melakukan perubahan nyata ke router saat diaktifkan).

[ ] Test existing yang menyinggung autoRecoverPppSecrets() (kalau ada) tetap lulus.
Yang TIDAK boleh dilakukan di sprint ini

Jangan deploy perubahan ini langsung ke job terjadwal produksi tanpa menjalankan --dry-run dulu selama minimal beberapa siklus dan review manual hasilnya.

Jangan mengubah frekuensi jadwal autoRecoverPppSecrets() di sprint ini (di luar scope).

Jangan gabungkan sprint ini dengan Sprint A4 dalam satu PR — keduanya menyentuh MikrotikService.php tapi risikonya beda kelas, pisahkan agar review lebih mudah dan rollback lebih presisi kalau salah satu bermasalah.
Setelah selesai
Laporkan:
Ringkasan format data nyata yang ditemukan di Langkah 0 (apa saja perbedaan format yang menyebabkan false-positive).
Semua kasus normalisasi yang akhirnya ditangani (CIDR suffix, null/empty, dan kasus lain kalau ditemukan).
Hasil test run, termasuk bukti test yang tadinya gagal sebelum fix.
Konfirmasi mode --dry-run sudah tersedia dan cara menjalankannya.
Rekomendasi: apakah aman langsung dipakai produksi, atau perlu masa observasi dry-run dulu (dan berapa lama menurut agent berdasarkan frekuensi jadwal job ini).



