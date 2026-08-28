<?php

use App\Enums\Wa\StatusAntrianWa;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Livewire\Sysblas\Antrian\Index;
use App\Models\AntrianWaBlast;
use App\Models\Sysblas;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SysblasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        SysblasSeeder::class,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

test('halaman monitoring antrian blast dapat diakses staf berwenang', function () {
    $this->actingAs($this->admin)
        ->get(route('sysblas.antrian.index'))
        ->assertOk()
        ->assertSee('SysBlast - Monitoring Antrian Blast');
});

test('staf dapat memfilter antrian berdasarkan status dan kata kunci', function () {
    $sysblas = Sysblas::first();

    $antrian1 = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan Tagihan Pelanggan A',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Menunggu,
    ]);

    $antrian2 = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6289876543210',
        'pesan' => 'Pesan Tiket Pelanggan B',
        'jenis' => 'tiket',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Terkirim,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('status', StatusAntrianWa::Menunggu->value)
        ->assertSee('6281234567890')
        ->assertDontSee('6289876543210')
        ->set('status', '')
        ->set('search', 'Tiket')
        ->assertSee('6289876543210')
        ->assertDontSee('6281234567890');
});

test('staf dapat melakukan retry pengiriman antrean yang gagal', function () {
    Queue::fake([KirimWaBlastJob::class]);

    $sysblas = Sysblas::first();
    $antrianGagal = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan gagal terkirim sebelumnya',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Gagal,
        'pesan_error' => 'Connection timeout',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('retry', $antrianGagal->id);

    expect($antrianGagal->fresh()->status)->toBe(StatusAntrianWa::Menunggu)
        ->and($antrianGagal->fresh()->pesan_error)->toBeNull();

    Queue::assertPushed(KirimWaBlastJob::class, function ($job) use ($antrianGagal) {
        return $job->antrian->id === $antrianGagal->id;
    });
});

test('staf dapat membatalkan antrean yang masih berstatus menunggu', function () {
    $sysblas = Sysblas::first();
    $antrianMenunggu = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan belum dikirim',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Menunggu,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('batalkan', $antrianMenunggu->id);

    expect($antrianMenunggu->fresh()->status)->toBe(StatusAntrianWa::Gagal)
        ->and($antrianMenunggu->fresh()->pesan_error)->toContain('Dibatalkan secara manual');
});

test('staf dapat menghapus data antrean', function () {
    $sysblas = Sysblas::first();
    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan yang akan dihapus',
        'jenis' => 'chat',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Terkirim,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('hapus', $antrian->id);

    expect(AntrianWaBlast::find($antrian->id))->toBeNull();
});

test('staf dapat membuka modal detail untuk melihat payload dan response log', function () {
    $sysblas = Sysblas::first();
    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan lengkap untuk pelanggan',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Terkirim,
        'response_log' => ['status' => true, 'message' => 'Message queued'],
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('openDetailModal', $antrian->id)
        ->assertSet('showDetailModal', true)
        ->assertSet('selectedAntrian.id', $antrian->id)
        ->assertSee('Detail Pesan Antrean Blast')
        ->assertSee('Pesan lengkap untuk pelanggan')
        ->assertSee('Message queued')
        ->assertSee('WhatsApp Destination');
});

test('retry dan batalkan memperbarui state selectedAntrian ketika modal terbuka', function () {
    Queue::fake([KirimWaBlastJob::class]);

    $sysblas = Sysblas::first();
    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan uji coba',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Menunggu,
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('openDetailModal', $antrian->id)
        ->call('batalkan', $antrian->id);

    expect($component->get('selectedAntrian')->status)->toBe(StatusAntrianWa::Gagal);

    $component->call('retry', $antrian->id);

    expect($component->get('selectedAntrian')->status)->toBe(StatusAntrianWa::Menunggu);
});
