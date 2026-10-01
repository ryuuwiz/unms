<?php

use App\Models\Pelanggan;
use App\Models\PengaturanPrefixRegistrasi;
use App\Models\Perusahaan;
use App\Support\BrandPelanggan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Perusahaan::create(['nama_perusahaan' => 'PT Nusa Jaringan', 'nama_brand' => 'NUSANET', 'is_default' => true, 'warna_utama' => '#0ea5e9']);
    $this->wifigo = PengaturanPrefixRegistrasi::factory()->create([
        'kode' => 'WIFI', 'nama' => 'WIFIGO HOME INTERNET', 'nama_pendek' => 'WIFIGO', 'warna_utama' => '#ff6600',
    ]);
    PengaturanPrefixRegistrasi::factory()->create(['kode' => 'MIIX', 'nama' => 'MyArsyila Fiber Nusantara']);
});

test('manifest mengikuti brand pelanggan yang login', function () {
    $akun = Pelanggan::factory()->create(['no_reg' => 'WIFI0110202601'])->akunPelanggan;

    $manifest = $this->actingAs($akun, 'pelanggan')
        ->get(route('portal.aplikasi.manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->json();

    expect($manifest)->toMatchArray([
        'name' => 'WIFIGO HOME INTERNET',
        'short_name' => 'WIFIGO',
        'theme_color' => '#ff6600',
        'background_color' => '#ff6600',
        'display' => 'standalone',
        'start_url' => '/dashboard',
        'scope' => '/',
    ])->and(collect($manifest['icons'])->pluck('sizes')->unique()->values()->all())->toBe(['192x192', '512x512'])
        ->and($manifest['icons'][0]['src'])->toContain('/aplikasi/ikon/WIFI/192.png');
});

test('manifest tanpa sesi memakai Petunjuk Brand, lalu brand Perusahaan', function () {
    $this->withCookie(BrandPelanggan::COOKIE_PETUNJUK, 'WIFI')
        ->get(route('portal.aplikasi.manifest'))
        ->assertJson(['short_name' => 'WIFIGO']);

    $this->withCookie(BrandPelanggan::COOKIE_PETUNJUK, '')
        ->get(route('portal.aplikasi.manifest'))
        ->assertJson(['name' => 'NUSANET', 'theme_color' => '#0ea5e9']);
});

test('atribut kosong diturunkan dari brand itu sendiri, bukan dari Perusahaan', function () {
    $akun = Pelanggan::factory()->create(['no_reg' => 'MIIX0110202601'])->akunPelanggan;

    $this->actingAs($akun, 'pelanggan')
        ->get(route('portal.aplikasi.manifest'))
        ->assertJson(['short_name' => 'MyArsyila', 'theme_color' => BrandPelanggan::WARNA_DEFAULT]);
});

test('manifest di mount lama memakai start_url dan scope /portal', function () {
    $this->get('/portal/manifest.webmanifest')
        ->assertOk()
        ->assertJson(['start_url' => '/portal/dashboard', 'scope' => '/portal/', 'id' => '/portal/']);
});

test('ikon aplikasi berupa PNG persegi dari ikon unggahan, logo, atau inisial', function (Closure $siapkan) {
    $siapkan($this->wifigo);

    foreach ([180, 192, 512] as $ukuran) {
        $respons = $this->get(route('portal.aplikasi.ikon', ['brand' => 'WIFI', 'ukuran' => $ukuran]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $gambar = imagecreatefromstring($respons->getContent());
        expect([imagesx($gambar), imagesy($gambar)])->toBe([$ukuran, $ukuran]);
    }
})->with([
    'ikon diunggah' => fn () => fn ($prefix) => $prefix->addMedia(UploadedFile::fake()->image('ikon.png', 512, 512))->toMediaCollection('ikon_aplikasi'),
    'dari logo lebar' => fn () => fn ($prefix) => $prefix->addMedia(UploadedFile::fake()->image('logo.png', 400, 120))->toMediaCollection('logo'),
    'dari inisial' => fn () => fn ($prefix) => null,
]);

test('ikon brand Perusahaan tersedia di pengenal default, brand atau ukuran tidak dikenal 404', function () {
    $this->get(route('portal.aplikasi.ikon', ['brand' => BrandPelanggan::KODE_PERUSAHAAN, 'ukuran' => 192]))->assertOk();
    $this->get(route('portal.aplikasi.ikon', ['brand' => 'XXXX', 'ukuran' => 192]))->assertNotFound();
    $this->get(route('portal.aplikasi.ikon', ['brand' => 'WIFI', 'ukuran' => 64]))->assertNotFound();
});

test('ikon aplikasi juga tersedia di mount lama', function () {
    $this->get('/portal/aplikasi/ikon/WIFI/192.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('mengganti nama brand atau ikon menghasilkan URL ikon baru', function () {
    $awal = BrandPelanggan::untukNoReg('WIFI01')->urlIkon(192);

    $this->wifigo->update(['nama' => 'WIFIGO FIBER']);
    $setelahGantiNama = BrandPelanggan::untukNoReg('WIFI01')->urlIkon(192);

    expect($setelahGantiNama)->not->toBe($awal);

    $this->wifigo->addMedia(UploadedFile::fake()->image('ikon.png', 512, 512))->toMediaCollection(BrandPelanggan::KOLEKSI_IKON);

    expect(BrandPelanggan::untukNoReg('WIFI01')->urlIkon(192))->not->toBe($setelahGantiNama);
});

test('service worker tersedia tanpa cache offline', function () {
    $this->get(route('portal.aplikasi.service-worker'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript')
        ->assertDontSee('caches.');
});

test('tombol Pasang Aplikasi hanya untuk pelanggan yang login', function () {
    $akun = Pelanggan::factory()->create(['no_reg' => 'WIFI0110202601'])->akunPelanggan;

    $this->get(route('portal.login'))->assertDontSee('data-pasang-aplikasi', false);
    $this->actingAs($akun, 'pelanggan')->get(route('portal.dashboard'))->assertSee('data-pasang-aplikasi', false);
});
