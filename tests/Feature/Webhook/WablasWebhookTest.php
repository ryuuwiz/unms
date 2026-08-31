<?php

use App\Enums\StatusInvoice;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Wa\StatusAntrianWa;
use App\Models\AntrianWaBlast;
use App\Models\Invoice;
use App\Models\Pelanggan;
use App\Models\Sysblas;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WebhookLog;
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
});

test('webhook tracking delivered berhasil memperbarui status antrian_wa_blast menjadi terkirim', function () {
    $sysblas = Sysblas::first();

    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan Tagihan Pengujian',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Diproses,
    ]);

    $payload = [
        'id' => 'wablas_msg_123',
        'phone' => '081234567890',
        'status' => 'delivered',
        'note' => 'Pesan berhasil diterima penerima',
        'sender' => '08970919525',
    ];

    $response = $this->postJson(route('webhook.wablas'), $payload);

    $response->assertOk()
        ->assertJson([
            'status' => true,
            'type' => 'tracking_status',
        ]);

    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Terkirim)
        ->and($antrian->fresh()->response_log)->toHaveKey('tracking_webhook');

    expect(WebhookLog::where('event_type', 'wablas.tracking')->exists())->toBeTrue();
});

test('webhook tracking failed berhasil menandai antrian gagal dengan pesan error', function () {
    $sysblas = Sysblas::first();

    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan Tagihan Pengujian',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Diproses,
    ]);

    $payload = [
        'id' => 'wablas_msg_124',
        'phone' => '6281234567890',
        'status' => 'failed',
        'note' => 'Nomor WhatsApp tidak aktif',
    ];

    $response = $this->postJson(route('webhook.wablas.tracking'), $payload);

    $response->assertOk();

    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Gagal)
        ->and($antrian->fresh()->pesan_error)->toBe('Nomor WhatsApp tidak aktif');
});

test('webhook incoming message keyword TAGIHAN membalas rincian invoice pelanggan', function () {
    $pelanggan = Pelanggan::factory()->create([
        'nama_depan' => 'Budi',
        'nama_belakang' => 'Santoso',
        'no_hp' => '081299998888',
    ]);

    Invoice::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'no_invoice' => 'INV-2026-9901',
        'jumlah' => 250000,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => Carbon::tomorrow(),
    ]);

    $payload = [
        'id' => 'inbound_001',
        'phone' => '081299998888',
        'sender' => '081299998888',
        'message' => 'Halo min, tolong info TAGIHAN saya bulan ini',
        'pushName' => 'Budi',
    ];

    $response = $this->postJson(route('webhook.wablas'), $payload);

    $response->assertOk()
        ->assertJson([
            'status' => true,
            'type' => 'incoming_message',
        ]);

    $autoReply = AntrianWaBlast::where('no_hp_tujuan', '6281299998888')
        ->where('jenis', 'webhook_autoreply')
        ->first();

    expect($autoReply)->not->toBeNull()
        ->and($autoReply->pesan)->toContain('Budi Santoso')
        ->and($autoReply->pesan)->toContain('INV-2026-9901')
        ->and($autoReply->pesan)->toContain('250.000');
});

test('webhook incoming message pelanggan mencatat pesan masuk ke histori tiket aktif', function () {
    $pelanggan = Pelanggan::factory()->create([
        'nama_depan' => 'Siti',
        'nama_belakang' => 'Rahma',
        'no_hp' => '087711223344',
    ]);

    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'jenis' => JenisTicket::Gangguan,
        'prioritas' => PrioritasTicket::Tinggi,
        'status' => StatusTicket::Diproses,
        'dibuat_oleh' => $this->admin->id,
    ]);

    $payload = [
        'id' => 'inbound_002',
        'phone' => '087711223344',
        'message' => 'Kabel FO di depan rumah sudah dibersihkan ya pak teknisi',
    ];

    $response = $this->postJson(route('webhook.wablas.message'), $payload);

    $response->assertOk();

    $histori = $ticket->histori()->latest('id')->first();
    expect($histori)->not->toBeNull()
        ->and($histori->catatan)->toContain('Kabel FO di depan rumah');
});

test('webhook waha message.ack berhasil memperbarui status antrian_wa_blast', function () {
    $sysblas = Sysblas::first();

    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan Tagihan WAHA',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Diproses,
    ]);

    $payload = [
        'id' => 'evt_01acktest',
        'event' => 'message.ack',
        'session' => 'default',
        'payload' => [
            'id' => 'false_6281234567890@c.us_ABCDEF',
            'to' => '6281234567890@c.us',
            'ack' => 2,
            'ackName' => 'DEVICE',
        ],
    ];

    $response = $this->postJson(route('webhook.whatsapp'), $payload);

    $response->assertOk()
        ->assertJson([
            'status' => true,
            'type' => 'message_ack',
        ]);

    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Terkirim)
        ->and($antrian->fresh()->response_log)->toHaveKey('waha_ack');
});

test('webhook waha session.status working mengaktifkan sysblas connection', function () {
    $sysblas = Sysblas::first();
    $sysblas->update(['is_aktif' => false, 'session_name' => 'default']);

    $payload = [
        'id' => 'evt_01status',
        'event' => 'session.status',
        'session' => 'default',
        'payload' => [
            'status' => 'WORKING',
        ],
    ];

    $response = $this->postJson(route('webhook.whatsapp'), $payload);

    $response->assertOk()
        ->assertJson([
            'status' => true,
            'type' => 'session_status',
        ]);

    expect($sysblas->fresh()->is_aktif)->toBeTrue();
});

test('webhook waha message inbound membalas info tagihan pelanggan', function () {
    $pelanggan = Pelanggan::factory()->create([
        'nama_depan' => 'Joko',
        'nama_belakang' => 'Widodo',
        'no_hp' => '081388887777',
    ]);

    Invoice::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'no_invoice' => 'INV-2026-WAHA-01',
        'jumlah' => 175000,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => Carbon::tomorrow(),
    ]);

    $payload = [
        'id' => 'evt_01msg',
        'event' => 'message',
        'session' => 'default',
        'payload' => [
            'id' => 'msg_waha_123',
            'from' => '6281388887777@c.us',
            'body' => 'Tolong cek TAGIHAN saya min',
            'fromMe' => false,
        ],
    ];

    $response = $this->postJson(route('webhook.whatsapp'), $payload);

    $response->assertOk()
        ->assertJson([
            'status' => true,
            'type' => 'incoming_message',
        ]);

    $autoReply = AntrianWaBlast::where('no_hp_tujuan', '6281388887777')
        ->where('jenis', 'webhook_autoreply')
        ->first();

    expect($autoReply)->not->toBeNull()
        ->and($autoReply->pesan)->toContain('Joko Widodo')
        ->and($autoReply->pesan)->toContain('INV-2026-WAHA-01')
        ->and($autoReply->pesan)->toContain('175.000');
});
