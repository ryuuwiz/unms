<?php

use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use Database\Seeders\PengaturanPrefixRegistrasiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PengaturanPrefixRegistrasiSeeder::class);
});

test('nomor invoice dan site id berawalan kode prefix pelanggan, tanpa kode bila no_reg tak dikenal', function () {
    $layananBf = LayananPelanggan::factory()->create(['pelanggan_id' => Pelanggan::factory()->create(['no_reg' => 'BF2309202603'])->id]);
    $layananCustom = LayananPelanggan::factory()->create(['pelanggan_id' => Pelanggan::factory()->create(['no_reg' => 'CUSTOM-001'])->id]);

    $invoiceBf = Invoice::factory()->create(['pelanggan_id' => $layananBf->pelanggan_id, 'layanan_pelanggan_id' => $layananBf->id, 'tanggal_terbit' => '2026-09-27']);
    $invoiceCustom = Invoice::factory()->create(['pelanggan_id' => $layananCustom->pelanggan_id, 'layanan_pelanggan_id' => $layananCustom->id, 'tanggal_terbit' => '2026-09-27']);

    expect($invoiceBf->no_invoice)->toMatch('/^BFINV-20260927\d{7}$/')
        ->and($invoiceCustom->no_invoice)->toMatch('/^INV-20260927\d{7}$/')
        ->and($layananBf->site_id)->toMatch('/^BFAPP\d{8}$/')
        ->and($layananCustom->site_id)->toMatch('/^APP\d{8}$/');
});

test('backfill mengganti hanya site id format lama dan mencatat penggantian di activity log', function () {
    $lama = LayananPelanggan::factory()->create(['pelanggan_id' => Pelanggan::factory()->create(['no_reg' => 'ARS2309202601'])->id]);
    $baru = LayananPelanggan::factory()->create();
    DB::table('layanan_pelanggan')->where('id', $lama->id)->update(['site_id' => 'SITE-ABCD1234']);
    $siteIdBaruSebelum = $baru->site_id;

    (require database_path('migrations/2026_09_27_091003_backfill_site_id_format_brand_app.php'))->up();

    expect($lama->fresh()->site_id)->toMatch('/^ARSAPP\d{8}$/')
        ->and($baru->fresh()->site_id)->toBe($siteIdBaruSebelum)
        ->and(Activity::forSubject($lama)->latest('id')->first()->getProperty('site_id_lama'))->toBe('SITE-ABCD1234');
});
