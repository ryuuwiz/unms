<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\PengaturanSiklusTagihan;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->paket = PaketLayanan::factory()->create([
        'harga' => 250000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);

    $this->buatLayanan = fn (StatusLayanan $status, string $expired): LayananPelanggan => LayananPelanggan::factory()->create([
        'paket_layanan_id' => $this->paket->id,
        'status' => $status,
        'tanggal_expired' => $expired,
    ]);
});

test('menerbitkan invoice siklus berikutnya tepat pada Hari Terbit dan tidak sebelumnya', function () {
    $layanan = ($this->buatLayanan)(StatusLayanan::Aktif, '2026-09-10');

    $this->travelTo(now()->setDate(2026, 8, 23));
    $this->artisan('invoice:generate')->assertSuccessful();
    expect($layanan->invoices()->count())->toBe(0);

    $this->travelTo(now()->setDate(2026, 8, 24));
    $this->artisan('invoice:generate')->assertSuccessful();
    $this->artisan('invoice:generate')->assertSuccessful();

    $invoice = $layanan->invoices()->sole();
    expect($invoice->periode_tagihan)->toBe('2026-09')
        ->and($invoice->tanggal_jatuh_tempo->toDateString())->toBe('2026-09-10')
        ->and((float) $invoice->jumlah_setelah_promo)->toBe(250000.0);
});

test('layanan suspend menerima invoice siklus baru yang menyerap tunggakan', function () {
    $layanan = ($this->buatLayanan)(StatusLayanan::Suspend, '2026-08-10');
    $lama = Invoice::factory()->create([
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => '2026-08',
        'jumlah_setelah_promo' => 250000,
        'status' => StatusInvoice::Kadaluarsa,
    ]);

    $this->travelTo(now()->setDate(2026, 8, 24));
    $this->artisan('invoice:generate')->assertSuccessful();

    $baru = $layanan->invoices()->where('periode_tagihan', '2026-09')->sole();
    expect($baru->tanggal_jatuh_tempo->toDateString())->toBe('2026-09-10')
        ->and((float) $baru->jumlah_setelah_promo)->toBe(500000.0)
        ->and($lama->fresh()->status)->toBe(StatusInvoice::Digabung);
});

test('tidak menerbitkan invoice untuk layanan berhenti atau masih dalam proses pemasangan', function () {
    ($this->buatLayanan)(StatusLayanan::Berhenti, '2026-09-10');
    ($this->buatLayanan)(StatusLayanan::Proses, '2026-09-10');

    $this->travelTo(now()->setDate(2026, 8, 24));
    $this->artisan('invoice:generate')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
});

test('mengikuti Hari Terbit Invoice pada pengaturan', function () {
    PengaturanSiklusTagihan::ambil()->update(['hari_terbit_invoice' => 5]);
    $layanan = ($this->buatLayanan)(StatusLayanan::Aktif, '2026-09-10');

    $this->travelTo(now()->setDate(2026, 9, 4));
    $this->artisan('invoice:generate')->assertSuccessful();
    expect($layanan->invoices()->count())->toBe(0);

    $this->travelTo(now()->setDate(2026, 9, 5));
    $this->artisan('invoice:generate')->assertSuccessful();
    expect($layanan->invoices()->count())->toBe(1);
});

test('layanan yang tertinggal beberapa siklus terkejar satu siklus per eksekusi', function () {
    $layanan = ($this->buatLayanan)(StatusLayanan::Suspend, '2026-06-10');
    Invoice::factory()->create([
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => '2026-06',
        'jumlah_setelah_promo' => 250000,
        'status' => StatusInvoice::Kadaluarsa,
    ]);

    $this->travelTo(now()->setDate(2026, 8, 24));
    $this->artisan('invoice:generate')->assertSuccessful();
    $this->artisan('invoice:generate')->assertSuccessful();
    $this->artisan('invoice:generate')->assertSuccessful();

    $terbuka = $layanan->invoices()->whereIn('status', StatusInvoice::terbuka())->sole();
    expect($terbuka->periode_tagihan)->toBe('2026-09')
        ->and($terbuka->tanggal_jatuh_tempo->toDateString())->toBe('2026-09-10')
        ->and((float) $terbuka->jumlah_setelah_promo)->toBe(1000000.0);
});
