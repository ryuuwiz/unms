<?php

use App\Enums\JenisTagihanPertama;
use App\Enums\StatusLayanan;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Promo;
use App\Services\Billing\BillingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->billing = app(BillingService::class);

    $this->paket = PaketLayanan::factory()->create([
        'harga' => 300000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
});

test('hitungRincianTagihanPertama mengembalikan harga penuh untuk SatuBulanFull', function () {
    $rincian = $this->billing->hitungRincianTagihanPertama(
        $this->paket,
        now(),
        JenisTagihanPertama::SatuBulanFull,
    );

    expect($rincian['jumlah'])->toBe(300000.0)
        ->and($rincian['diskon'])->toBe(0.0)
        ->and($rincian['jumlah_setelah_promo'])->toBe(300000.0)
        ->and($rincian['hari_ditagih'])->toBeNull()
        ->and($rincian['hari_total_periode'])->toBeNull();
});

test('hitungRincianTagihanPertama proporsional terhadap sisa hari bulan kalender', function () {
    // Bulan 30 hari, mulai tanggal 21 -> sisa 10 hari (21..30).
    $tanggalMulai = \Illuminate\Support\Carbon::create(2026, 9, 21);

    $rincian = $this->billing->hitungRincianTagihanPertama(
        $this->paket,
        $tanggalMulai,
        JenisTagihanPertama::ProporsionalSisaHari,
    );

    expect($rincian['hari_total_periode'])->toBe(30)
        ->and($rincian['hari_ditagih'])->toBe(10)
        ->and($rincian['jumlah'])->toBe(round(300000 / 30 * 10, 2))
        ->and($rincian['jumlah_setelah_promo'])->toBe(round(300000 / 30 * 10, 2));
});

test('hitungRincianTagihanPertama memotong harga penuh dengan diskon promo', function () {
    $promo = Promo::factory()->create([
        'diskon_nilai' => 50000,
        'minimal_nominal_invoice' => 100000,
    ]);

    $rincian = $this->billing->hitungRincianTagihanPertama(
        $this->paket,
        now(),
        JenisTagihanPertama::Promo,
        $promo,
    );

    expect($rincian['jumlah'])->toBe(300000.0)
        ->and($rincian['diskon'])->toBe(50000.0)
        ->and($rincian['jumlah_setelah_promo'])->toBe(250000.0);
});

test('generateFirstInvoice menerbitkan invoice dengan periode_tagihan NULL', function () {
    $layanan = LayananPelanggan::factory()->create([
        'paket_layanan_id' => $this->paket->id,
        'status' => StatusLayanan::Proses,
        'tanggal_mulai' => now()->toDateString(),
        'tanggal_expired' => now()->addMonth()->toDateString(),
    ]);

    $invoice = $this->billing->generateFirstInvoice($layanan, JenisTagihanPertama::SatuBulanFull);

    expect($invoice->periode_tagihan)->toBeNull()
        ->and((float) $invoice->jumlah_setelah_promo)->toBe(300000.0)
        ->and($invoice->layanan_pelanggan_id)->toBe($layanan->id);
});

test('tagihan pertama dari generateFirstInvoice tidak menghalangi tagihan siklus berikutnya yang sesungguhnya', function () {
    // Regresi: generateFirstInvoice() HARUS lewat generateManualInvoice() (periode_tagihan NULL),
    // bukan generateInvoice() -- jika tidak, idempotency guard generateInvoice() akan menemukan
    // tagihan pertama ini saat GenerateInvoicesCommand berjalan pada bulan tanggal_expired, dan
    // mengembalikannya begitu saja alih-alih menerbitkan tagihan perpanjangan yang sesungguhnya.
    $tanggalMulai = now()->startOfMonth()->addDays(10);
    $tanggalExpired = $tanggalMulai->copy()->addMonthNoOverflow();

    $layanan = LayananPelanggan::factory()->create([
        'paket_layanan_id' => $this->paket->id,
        'status' => StatusLayanan::Aktif,
        'tanggal_mulai' => $tanggalMulai->toDateString(),
        'tanggal_expired' => $tanggalExpired->toDateString(),
    ]);

    $tagihanPertama = $this->billing->generateFirstInvoice($layanan, JenisTagihanPertama::ProporsionalSisaHari);

    $this->artisan('invoice:generate', ['--force' => true])->assertSuccessful();

    $invoices = Invoice::where('layanan_pelanggan_id', $layanan->id)->get();

    expect($invoices)->toHaveCount(2);

    $tagihanSiklus = $invoices->firstWhere('id', '!=', $tagihanPertama->id);
    expect($tagihanSiklus)->not->toBeNull()
        ->and($tagihanSiklus->periode_tagihan)->toBe($tanggalExpired->format('Y-m'))
        ->and((float) $tagihanSiklus->jumlah_setelah_promo)->toBe(300000.0);
});
