<?php

use App\Models\Pelanggan;
use App\Models\PengaturanPrefixRegistrasi;
use App\Models\Perusahaan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Kontak Dukungan memakai kontak Perusahaan dengan pesan WhatsApp berisi brand dan No. Registrasi', function (string $noReg, string $brand) {
    Perusahaan::create([
        'nama_perusahaan' => 'PT Nusa Jaringan', 'nama_brand' => 'NUSANET', 'is_default' => true,
        'whatsapp' => '0812-1111-2222', 'telepon' => '(021) 555-0101', 'email' => 'cs@nusanet.id',
    ]);
    PengaturanPrefixRegistrasi::factory()->create(['kode' => 'WIFI', 'nama' => 'WIFIGO']);
    PengaturanPrefixRegistrasi::factory()->create(['kode' => 'BEST', 'nama' => 'BESTFIBER']);
    $akun = Pelanggan::factory()->create(['no_reg' => $noReg])->akunPelanggan;

    $pesan = rawurlencode("Halo CS {$brand}, saya pelanggan dengan No. Registrasi {$noReg}. Saya butuh bantuan terkait layanan internet saya.");

    $this->actingAs($akun, 'pelanggan')
        ->get(route('portal.dashboard'))
        ->assertSee('href="https://wa.me/6281211112222?text='.$pesan.'"', false)
        ->assertSee('href="tel:0215550101"', false)
        ->assertSee('href="mailto:cs@nusanet.id"', false);
})->with([
    ['WIFI0110202601', 'WIFIGO'],
    ['BEST0110202601', 'BESTFIBER'],
]);

test('tombol kontak yang datanya kosong disembunyikan', function () {
    Perusahaan::create([
        'nama_perusahaan' => 'PT Nusa Jaringan', 'nama_brand' => 'NUSANET', 'is_default' => true,
        'whatsapp' => null, 'telepon' => null, 'email' => 'cs@nusanet.id',
    ]);
    $akun = Pelanggan::factory()->create()->akunPelanggan;

    $this->actingAs($akun, 'pelanggan')
        ->get(route('portal.dashboard'))
        ->assertSee('data-kontak="email"', false)
        ->assertDontSee('data-kontak="whatsapp"', false)
        ->assertDontSee('data-kontak="telepon"', false);
});
