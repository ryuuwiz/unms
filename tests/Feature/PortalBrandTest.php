<?php

use App\Models\AkunPelanggan;
use App\Models\Invoice;
use App\Models\Pelanggan;
use App\Models\PengaturanPrefixRegistrasi;
use App\Models\Perusahaan;
use App\Models\User;
use App\Support\BrandPelanggan;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    Perusahaan::create(['nama_perusahaan' => 'PT Nusa Jaringan', 'nama_brand' => 'NUSANET', 'is_default' => true]);
    PengaturanPrefixRegistrasi::factory()->create(['kode' => 'WIFI', 'nama' => 'WIFIGO', 'warna_utama' => '#ff6600']);
    PengaturanPrefixRegistrasi::factory()->create(['kode' => 'BEST', 'nama' => 'BESTFIBER', 'is_active' => false]);
});

function akunPortal(string $noReg): AkunPelanggan
{
    return Pelanggan::factory()->create(['no_reg' => $noReg])->akunPelanggan;
}

test('portal yang login tampil dengan Brand Pelanggan, termasuk prefix nonaktif, tanpa nama GOBILLING', function (string $noReg, string $brand) {
    $this->actingAs(akunPortal($noReg), 'pelanggan')
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee("Dashboard Portal Pelanggan - {$brand}")
        ->assertSee("Selamat datang di Portal Pelanggan {$brand}")
        ->assertDontSee('GOBILLING');
})->with([
    'prefix aktif' => ['WIFI0110202601', 'WIFIGO'],
    'prefix nonaktif' => ['BEST0110202601', 'BESTFIBER'],
    'tanpa prefix cocok jatuh ke Perusahaan' => ['CUSTOM-001', 'NUSANET'],
]);

test('warna utama brand menjadi aksen dan theme-color Portal, kosong memakai warna default', function () {
    $this->actingAs(akunPortal('WIFI0110202601'), 'pelanggan')
        ->get(route('portal.dashboard'))
        ->assertSee('<meta name="theme-color" content="#ff6600">', false)
        ->assertSee('--color-accent: #ff6600;', false);

    $this->actingAs(akunPortal('BEST0110202602'), 'pelanggan')
        ->get(route('portal.dashboard'))
        ->assertSee('<meta name="theme-color" content="'.BrandPelanggan::WARNA_DEFAULT.'">', false);
});

test('mount lama /portal juga ber-brand', function () {
    $this->actingAs(akunPortal('WIFI0110202601'), 'pelanggan')
        ->get('/portal/dashboard')
        ->assertOk()
        ->assertSee('Selamat datang di Portal Pelanggan WIFIGO')
        ->assertSee('<link rel="manifest" href="/portal/manifest.webmanifest" crossorigin="use-credentials">', false);
});

test('impersonasi staf menampilkan brand pelanggan target tanpa menulis Petunjuk Brand', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $akun = akunPortal('WIFI0110202601');

    $this->actingAs($admin);
    app('impersonate')->take($admin, $akun, 'pelanggan');

    $this->get(route('portal.dashboard'))
        ->assertSee('Selamat datang di Portal Pelanggan WIFIGO')
        ->assertCookieMissing(BrandPelanggan::COOKIE_PETUNJUK);
});

test('membuka Portal saat login menulis Petunjuk Brand pelanggan itu', function () {
    $this->actingAs(akunPortal('WIFI0110202601'), 'pelanggan')
        ->get(route('portal.dashboard'))
        ->assertCookie(BrandPelanggan::COOKIE_PETUNJUK, 'WIFI');
});

test('halaman login dan klaim akun memakai Petunjuk Brand, atau brand Perusahaan bila tidak ada/tidak valid', function (?string $petunjuk, string $brand) {
    $judul = ['portal.login' => 'Login Portal Pelanggan', 'portal.klaim-akun' => 'Aktivasi & Klaim Akun Portal'];

    foreach ($judul as $rute => $halaman) {
        $permintaan = $petunjuk ? $this->withCookie(BrandPelanggan::COOKIE_PETUNJUK, $petunjuk) : $this;

        $permintaan->get(route($rute))
            ->assertOk()
            ->assertSee("{$halaman} - {$brand}")
            ->assertDontSee('GOBILLING');
    }
})->with([
    'petunjuk WIFIGO' => ['WIFI', 'WIFIGO'],
    'perangkat baru' => [null, 'NUSANET'],
    'petunjuk tidak dikenal' => ['XXXX', 'NUSANET'],
]);

test('setelah login brand selalu dari No. Registrasi meski Petunjuk Brand berisi brand lain', function () {
    $this->withCookie(BrandPelanggan::COOKIE_PETUNJUK, 'WIFI')
        ->actingAs(akunPortal('BEST0110202601'), 'pelanggan')
        ->get(route('portal.dashboard'))
        ->assertSee('Selamat datang di Portal Pelanggan BESTFIBER')
        ->assertCookie(BrandPelanggan::COOKIE_PETUNJUK, 'BEST');
});

test('Halaman Tagihan Mandiri memakai brand pemilik invoice di kedua mount, mengabaikan Petunjuk Brand', function (string $noReg, string $brand, string $namaRute) {
    $invoice = Invoice::factory()->create([
        'pelanggan_id' => Pelanggan::factory()->create(['no_reg' => $noReg])->id,
    ]);

    $this->withCookie(BrandPelanggan::COOKIE_PETUNJUK, 'WIFI')
        ->get(URL::signedRoute($namaRute, ['invoice' => $invoice->id]))
        ->assertOk()
        ->assertSee("Rincian Invoice - {$brand}")
        ->assertDontSee('GOBILLING');
})->with([
    'BESTFIBER' => ['BEST0110202609', 'BESTFIBER'],
    'tanpa prefix cocok jatuh ke Perusahaan' => ['CUSTOM-009', 'NUSANET'],
])->with(['portal.invoice.show', 'portal-legacy.invoice.show']);

test('pelanggan login yang membuka invoice-nya sendiri melihat brand dari invoice', function () {
    $pelanggan = Pelanggan::factory()->create(['no_reg' => 'WIFI0110202605']);
    $invoice = Invoice::factory()->create(['pelanggan_id' => $pelanggan->id]);

    $this->actingAs($pelanggan->akunPelanggan, 'pelanggan')
        ->get(route('portal.invoice.show', $invoice))
        ->assertOk()
        ->assertSee('Rincian Invoice - WIFIGO');
});

test('halaman login di mount lama juga memakai Petunjuk Brand', function () {
    $this->withCookie(BrandPelanggan::COOKIE_PETUNJUK, 'WIFI')
        ->get('/portal/login')
        ->assertOk()
        ->assertSee('Login Portal Pelanggan - WIFIGO');
});
