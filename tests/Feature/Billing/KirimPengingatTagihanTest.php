<?php

use App\Enums\StatusInvoice;
use App\Models\AntrianWaBlast;
use App\Models\Invoice;
use App\Models\Pelanggan;
use App\Services\Wablas\WablasClient;
use Database\Seeders\AturanPengingatTagihanSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        WaTemplateSeeder::class,
        AturanPengingatTagihanSeeder::class,
    ]);
});

test('command invoice:kirim-pengingat berhasil memasukkan tagihan ke antrian sesuai tanggal jatuh tempo', function () {
    Queue::fake();

    $pelangganH3 = Pelanggan::factory()->create(['no_hp' => '081111111111']);
    $pelangganH1 = Pelanggan::factory()->create(['no_hp' => '082222222222']);
    $pelangganH0 = Pelanggan::factory()->create(['no_hp' => '083333333333']);
    $pelangganTunggakan = Pelanggan::factory()->create(['no_hp' => '084444444444']);
    $pelangganAman = Pelanggan::factory()->create(['no_hp' => '085555555555']); // Jatuh tempo H+10 (tidak ada aturan)

    $today = Carbon::today();

    // Invoice H-3 (Jatuh tempo 3 hari lagi)
    Invoice::factory()->create([
        'pelanggan_id' => $pelangganH3->id,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => $today->copy()->addDays(3),
    ]);

    // Invoice H-1 (Jatuh tempo besok)
    Invoice::factory()->create([
        'pelanggan_id' => $pelangganH1->id,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => $today->copy()->addDays(1),
    ]);

    // Invoice H-0 (Jatuh tempo hari ini)
    Invoice::factory()->create([
        'pelanggan_id' => $pelangganH0->id,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => $today->copy(),
    ]);

    // Invoice Tunggakan (Jatuh tempo 3 hari lalu)
    Invoice::factory()->create([
        'pelanggan_id' => $pelangganTunggakan->id,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => $today->copy()->subDays(3),
    ]);

    // Invoice H-10
    Invoice::factory()->create([
        'pelanggan_id' => $pelangganAman->id,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => $today->copy()->addDays(10),
    ]);

    $this->artisan('invoice:kirim-pengingat', ['--force' => true])
        ->assertSuccessful();

    // Pastikan 4 invoice terjadwal terdaftar di antrian_wa_blast
    expect(AntrianWaBlast::count())->toBe(4);

    expect(AntrianWaBlast::where('no_hp_tujuan', WablasClient::normalizePhoneNumber($pelangganH3->no_hp))->exists())->toBeTrue()
        ->and(AntrianWaBlast::where('no_hp_tujuan', WablasClient::normalizePhoneNumber($pelangganH1->no_hp))->exists())->toBeTrue()
        ->and(AntrianWaBlast::where('no_hp_tujuan', WablasClient::normalizePhoneNumber($pelangganH0->no_hp))->exists())->toBeTrue()
        ->and(AntrianWaBlast::where('no_hp_tujuan', WablasClient::normalizePhoneNumber($pelangganTunggakan->no_hp))->exists())->toBeTrue()
        ->and(AntrianWaBlast::where('no_hp_tujuan', WablasClient::normalizePhoneNumber($pelangganAman->no_hp))->exists())->toBeFalse();

    // Jalankan ulang pada hari yang sama (harus idempotent / tidak ada penambahan baru)
    $this->artisan('invoice:kirim-pengingat', ['--force' => true])
        ->assertSuccessful();

    expect(AntrianWaBlast::count())->toBe(4);
});
