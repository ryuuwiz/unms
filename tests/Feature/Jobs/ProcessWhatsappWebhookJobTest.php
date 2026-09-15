<?php

use App\Enums\StatusWebhookLog;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Wa\StatusAntrianWa;
use App\Jobs\Whatsapp\ProcessWhatsappWebhookJob;
use App\Models\AntrianWaBlast;
use App\Models\Pelanggan;
use App\Models\Sysblas;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\Whatsapp\WhatsappWebhookService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SysblasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        SysblasSeeder::class,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->service = app(WhatsappWebhookService::class);
});

test('ProcessWhatsappWebhookJob memperbarui status antrian_wa_blast dari WebhookLog tracking', function () {
    $sysblas = Sysblas::first();

    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan Tagihan Pengujian Job',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Diproses,
    ]);

    $webhookLog = WebhookLog::create([
        'provider' => 'whatsapp',
        'event_type' => 'whatsapp.tracking',
        'provider_event_id' => 'job_test_tracking_1',
        'payload' => [
            'id' => 'job_test_tracking_1',
            'phone' => '6281234567890',
            'status' => 'delivered',
        ],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now(),
    ]);

    (new ProcessWhatsappWebhookJob($webhookLog->id))->handle($this->service);

    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Terkirim)
        ->and($webhookLog->fresh()->status_proses)->toBe(StatusWebhookLog::Diproses);
});

test('ProcessWhatsappWebhookJob mencatat pesan masuk pelanggan ke histori tiket aktif', function () {
    $pelanggan = Pelanggan::factory()->create([
        'nama_depan' => 'Rina',
        'nama_belakang' => 'Wati',
        'no_hp' => '087700001111',
    ]);

    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'jenis' => JenisTicket::Gangguan,
        'prioritas' => PrioritasTicket::Tinggi,
        'status' => StatusTicket::Diproses,
        'dibuat_oleh' => $this->admin->id,
    ]);

    $webhookLog = WebhookLog::create([
        'provider' => 'whatsapp',
        'event_type' => 'whatsapp.incoming_message',
        'provider_event_id' => 'job_test_message_1',
        'payload' => [
            'phone' => '087700001111',
            'message' => 'Sinyal masih naik turun pak',
        ],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now(),
    ]);

    (new ProcessWhatsappWebhookJob($webhookLog->id))->handle($this->service);

    $histori = $ticket->histori()->latest('id')->first();
    expect($histori)->not->toBeNull()
        ->and($histori->catatan)->toContain('Sinyal masih naik turun pak')
        ->and($webhookLog->fresh()->status_proses)->toBe(StatusWebhookLog::Diproses);
});

test('ProcessWhatsappWebhookJob diabaikan dengan aman jika WebhookLog tidak ditemukan', function () {
    expect(fn () => (new ProcessWhatsappWebhookJob(999999))->handle($this->service))
        ->not->toThrow(Throwable::class);
});

test('ProcessWhatsappWebhookJob tidak memproses ulang WebhookLog yang sudah berstatus diproses', function () {
    $sysblas = Sysblas::first();

    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567891',
        'pesan' => 'Pesan Tagihan Idempotensi',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Gagal,
        'pesan_error' => 'Kegagalan awal, seharusnya tidak ditimpa',
    ]);

    $webhookLog = WebhookLog::create([
        'provider' => 'whatsapp',
        'event_type' => 'whatsapp.tracking',
        'provider_event_id' => 'job_test_idempotent_1',
        'payload' => [
            'id' => 'job_test_idempotent_1',
            'phone' => '6281234567891',
            'status' => 'delivered',
        ],
        'status_proses' => StatusWebhookLog::Diproses,
        'diterima_pada' => now(),
    ]);

    (new ProcessWhatsappWebhookJob($webhookLog->id))->handle($this->service);

    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Gagal)
        ->and($antrian->fresh()->pesan_error)->toBe('Kegagalan awal, seharusnya tidak ditimpa');
});
