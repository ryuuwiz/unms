<?php

use App\Enums\ProvisioningStatus;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Jobs\Mikrotik\DisablePppoeAccountJob;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->router = Router::factory()->online()->create();
    $this->pelanggan = Pelanggan::factory()->create();
    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);

    $this->buatLayanan = fn (): LayananPelanggan => LayananPelanggan::factory()->create([
        'router_id' => $this->router->id,
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'status' => StatusLayanan::Aktif,
        'provisioning_status' => ProvisioningStatus::Success,
        'terprovisi_pada' => Carbon::now()->subDays(2),
    ]);
});

test('mengisolir layanan aktif yang invoice pertamanya belum dibayar melewati tenggat H+1', function () {
    Queue::fake([DisablePppoeAccountJob::class]);

    $layanan = ($this->buatLayanan)();
    Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => null,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => Carbon::yesterday(),
    ]);

    $this->artisan('layanan:cek-tunggakan-pertama')->assertSuccessful();

    expect($layanan->fresh()->status)->toBe(StatusLayanan::Suspend);
    Queue::assertPushed(DisablePppoeAccountJob::class);
});

test('mengisolir juga saat invoice pertama sudah ditandai kadaluarsa oleh invoice:cek-kadaluarsa', function () {
    Queue::fake([DisablePppoeAccountJob::class]);

    $layanan = ($this->buatLayanan)();
    Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => null,
        'status' => StatusInvoice::Kadaluarsa,
        'tanggal_jatuh_tempo' => Carbon::yesterday(),
    ]);

    $this->artisan('layanan:cek-tunggakan-pertama')->assertSuccessful();

    expect($layanan->fresh()->status)->toBe(StatusLayanan::Suspend);
});

test('tidak mengisolir layanan yang invoice pertamanya masih dalam tenggat H+1', function () {
    $layanan = ($this->buatLayanan)();
    Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => null,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => Carbon::today(),
    ]);

    $this->artisan('layanan:cek-tunggakan-pertama')->assertSuccessful();

    expect($layanan->fresh()->status)->toBe(StatusLayanan::Aktif);
});

test('tidak mengisolir layanan yang invoice pertamanya sudah lunas', function () {
    $layanan = ($this->buatLayanan)();
    Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => null,
        'status' => StatusInvoice::Lunas,
        'tanggal_jatuh_tempo' => Carbon::yesterday(),
    ]);

    $this->artisan('layanan:cek-tunggakan-pertama')->assertSuccessful();

    expect($layanan->fresh()->status)->toBe(StatusLayanan::Aktif);
});

test('tidak mengisolir layanan yang sudah masuk siklus bulanan meskipun ada invoice ad-hoc lain yang telat', function () {
    $layanan = ($this->buatLayanan)();

    // Sudah lunas invoice pertama dan lanjut ke siklus bulanan (periode_tagihan terisi).
    Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => '2026-09',
        'status' => StatusInvoice::Lunas,
        'tanggal_jatuh_tempo' => Carbon::today()->subMonth(),
    ]);

    // Invoice ad-hoc lain (mis. biaya pindah alamat) yang telat -- tidak boleh memicu
    // isolir lewat command ini, itu bukan tanggung jawabnya.
    Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => null,
        'status' => StatusInvoice::Kadaluarsa,
        'tanggal_jatuh_tempo' => Carbon::yesterday(),
    ]);

    $this->artisan('layanan:cek-tunggakan-pertama')->assertSuccessful();

    expect($layanan->fresh()->status)->toBe(StatusLayanan::Aktif);
});

test('tidak menyentuh layanan yang belum berstatus aktif', function () {
    $layanan = ($this->buatLayanan)();
    $layanan->update(['status' => StatusLayanan::Proses]);

    Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => null,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => Carbon::yesterday(),
    ]);

    $this->artisan('layanan:cek-tunggakan-pertama')->assertSuccessful();

    expect($layanan->fresh()->status)->toBe(StatusLayanan::Proses);
});
