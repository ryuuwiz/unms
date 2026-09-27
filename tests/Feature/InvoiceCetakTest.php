<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Models\Invoice;
use App\Models\IpPublik;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Billing\InvoiceCetak;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->layanan = LayananPelanggan::factory()->create([
        'paket_layanan_id' => PaketLayanan::factory()->create(['nama_paket' => 'Paket Family 20 Mbps', 'masa_aktif_nilai' => 1, 'masa_aktif_satuan' => 'bulan'])->id,
        'status' => StatusLayanan::Aktif,
        'site_id' => 'SITE-0001',
        'tanggal_expired' => '2026-10-10',
    ]);
});

test('baris per komponen menjumlah ke subtotal, diskon menutup selisih ke total, tanpa alamat IP', function () {
    IpPublik::factory()->create(['layanan_pelanggan_id' => $this->layanan->id, 'alamat_ip' => '203.0.113.77', 'harga_ditagih' => 50000]);
    $invoice = Invoice::factory()->create([
        'layanan_pelanggan_id' => $this->layanan->id,
        'pelanggan_id' => $this->layanan->pelanggan_id,
        'periode_tagihan' => '2026-10',
        'status' => StatusInvoice::MenungguPembayaran,
        'jumlah' => 200000,
        'jumlah_tunggakan' => 150000,
        'jumlah_setelah_promo' => 330000,
    ]);

    $cetak = InvoiceCetak::dari($invoice);

    expect(array_column($cetak->baris, 'harga'))->toBe([150000.0, 50000.0, 150000.0])
        ->and($cetak->subtotal)->toBe(350000.0)
        ->and($cetak->diskon)->toBe(20000.0)
        ->and($cetak->total)->toBe(330000.0)
        ->and($cetak->keterangan)->toBe('(SITE-0001) Pembayaran Internet Periode Oktober 2026 Paket Family 20 Mbps hingga 2026-11-10')
        ->and(json_encode($cetak->baris))->not->toContain('203.0.113.77');
});

test('invoice lunas memakai snapshot masa aktif, bukan menghitung ulang dari expired yang sudah diperpanjang', function () {
    $invoice = Invoice::factory()->create([
        'layanan_pelanggan_id' => $this->layanan->id,
        'pelanggan_id' => $this->layanan->pelanggan_id,
        'periode_tagihan' => '2026-10',
        'status' => StatusInvoice::MenungguPembayaran,
        'jumlah' => 150000,
        'jumlah_tunggakan' => 0,
        'jumlah_setelah_promo' => 150000,
    ]);
    $kasir = User::factory()->create(['name' => 'Kasir Satu']);

    app(BillingService::class)->prosesPembayaranManual($invoice, ['metode' => MetodePembayaran::Transfer, 'jumlah_dibayar' => 150000], $kasir);

    $cetak = InvoiceCetak::dari($invoice->fresh());

    expect($invoice->fresh()->masa_aktif_hingga->toDateString())->toBe($this->layanan->fresh()->tanggal_expired->toDateString())
        ->and($cetak->keterangan)->toEndWith('hingga '.$this->layanan->fresh()->tanggal_expired->toDateString())
        ->and($cetak->metode)->toBe(MetodePembayaran::Transfer->label())
        ->and($cetak->teller)->toBe('Kasir Satu');
});

test('invoice tanpa periode memakai keterangan invoice tanpa periode dan hingga', function () {
    $invoice = Invoice::factory()->create([
        'layanan_pelanggan_id' => $this->layanan->id,
        'pelanggan_id' => $this->layanan->pelanggan_id,
        'periode_tagihan' => null,
        'keterangan' => 'Biaya instalasi',
    ]);

    expect(InvoiceCetak::dari($invoice)->keterangan)->toBe('(SITE-0001) Biaya instalasi');
});
