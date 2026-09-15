<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusWebhookLog;
use App\Enums\Sysblas\SysblasProvider;
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
        'id' => 'gw_msg_123',
        'phone' => '081234567890',
        'status' => 'delivered',
        'note' => 'Pesan berhasil diterima penerima',
        'sender' => '08970919525',
    ];

    $response = $this->postJson(route('webhook.whatsapp'), $payload);

    // Pemrosesan kini via ProcessWhatsappWebhookJob (antrean) -- respons langsung hanya
    // mengonfirmasi diterima & diantrikan; efek samping tetap terjadi karena
    // QUEUE_CONNECTION=sync di phpunit.xml menjalankannya inline saat dispatch.
    $response->assertOk()
        ->assertJson([
            'status' => true,
            'type' => 'queued',
        ]);

    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Terkirim)
        ->and($antrian->fresh()->response_log)->toHaveKey('tracking_webhook');

    expect(WebhookLog::where('event_type', 'whatsapp.tracking')->exists())->toBeTrue();
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
        'id' => 'gw_msg_124',
        'phone' => '6281234567890',
        'status' => 'failed',
        'note' => 'Nomor WhatsApp tidak aktif',
    ];

    $response = $this->postJson(route('webhook.whatsapp'), $payload);

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

    $response = $this->postJson(route('webhook.whatsapp'), $payload);

    $response->assertOk()
        ->assertJson([
            'status' => true,
            'type' => 'queued',
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

    $response = $this->postJson(route('webhook.whatsapp'), $payload);

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
            'type' => 'queued',
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
            'type' => 'queued',
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
            'type' => 'queued',
        ]);

    $autoReply = AntrianWaBlast::where('no_hp_tujuan', '6281388887777')
        ->where('jenis', 'webhook_autoreply')
        ->first();

    expect($autoReply)->not->toBeNull()
        ->and($autoReply->pesan)->toContain('Joko Widodo')
        ->and($autoReply->pesan)->toContain('INV-2026-WAHA-01')
        ->and($autoReply->pesan)->toContain('175.000');
});

test('webhook gowa message.ack (shape sama dengan waha) berhasil memperbarui status antrian_wa_blast', function () {
    $gowaSysblas = Sysblas::factory()->create([
        'provider' => SysblasProvider::Gowa,
        'session_name' => 'org_gowa_1',
        'is_default' => false,
    ]);

    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $gowaSysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan Tagihan GOWA',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Diproses,
    ]);

    $payload = [
        'id' => 'evt_gowa_ack',
        'event' => 'message.ack',
        'session' => 'org_gowa_1',
        'payload' => [
            'id' => '3EB0B430B6F8F1D0E053AC120E0A9E5C',
            'to' => '6281234567890@s.whatsapp.net',
            'ack' => 2,
            'ackName' => 'DEVICE',
        ],
    ];

    $response = $this->postJson(route('webhook.whatsapp'), $payload);

    $response->assertOk()
        ->assertJson(['status' => true, 'type' => 'queued']);

    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Terkirim);
});

test('webhook gowa dengan signature hmac valid diproses normal', function () {
    $gowaSysblas = Sysblas::factory()->create([
        'provider' => SysblasProvider::Gowa,
        'session_name' => 'org_gowa_secure',
        'api_secret' => 'super-secret-webhook-key',
        'is_default' => false,
    ]);

    $payload = [
        'id' => 'evt_gowa_secure',
        'event' => 'session.status',
        'session' => 'org_gowa_secure',
        'payload' => ['status' => 'WORKING'],
    ];

    $rawBody = json_encode($payload);
    $signature = hash_hmac('sha256', $rawBody, 'super-secret-webhook-key');

    $response = $this->call('POST', route('webhook.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Gowa-Signature' => $signature,
    ], $rawBody);

    $response->assertOk()->assertJson(['status' => true, 'type' => 'queued']);
    expect($gowaSysblas->fresh()->is_aktif)->toBeTrue();
});

test('webhook gowa dengan signature hmac tidak valid ditolak 401', function () {
    Sysblas::factory()->create([
        'provider' => SysblasProvider::Gowa,
        'session_name' => 'org_gowa_secure2',
        'api_secret' => 'super-secret-webhook-key',
        'is_default' => false,
    ]);

    $payload = [
        'id' => 'evt_gowa_bad_sig',
        'event' => 'session.status',
        'session' => 'org_gowa_secure2',
        'payload' => ['status' => 'WORKING'],
    ];

    $rawBody = json_encode($payload);

    $response = $this->call('POST', route('webhook.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Gowa-Signature' => 'ini-signature-salah',
    ], $rawBody);

    $response->assertStatus(401)
        ->assertJson(['status' => false, 'type' => 'unauthorized']);
});

test('webhook gowa tanpa signature header ditolak 401 saat secret dikonfigurasi', function () {
    Sysblas::factory()->create([
        'provider' => SysblasProvider::Gowa,
        'session_name' => 'org_gowa_secure3',
        'api_secret' => 'super-secret-webhook-key',
        'is_default' => false,
    ]);

    $payload = [
        'id' => 'evt_gowa_no_sig',
        'event' => 'session.status',
        'session' => 'org_gowa_secure3',
        'payload' => ['status' => 'WORKING'],
    ];

    $response = $this->postJson(route('webhook.whatsapp'), $payload);

    $response->assertStatus(401);
});

test('webhook gowa tanpa secret dikonfigurasi melewati verifikasi signature', function () {
    $gowaSysblas = Sysblas::factory()->create([
        'provider' => SysblasProvider::Gowa,
        'session_name' => 'org_gowa_nosecret',
        'api_secret' => null,
        'is_default' => false,
    ]);

    $payload = [
        'id' => 'evt_gowa_nosecret',
        'event' => 'session.status',
        'session' => 'org_gowa_nosecret',
        'payload' => ['status' => 'WORKING'],
    ];

    $response = $this->postJson(route('webhook.whatsapp'), $payload);

    $response->assertOk();
    expect($gowaSysblas->fresh()->is_aktif)->toBeTrue();
});

test('webhook gowa dengan signature invalid tetap membuat WebhookLog berstatus gagal', function () {
    Sysblas::factory()->create([
        'provider' => SysblasProvider::Gowa,
        'session_name' => 'org_gowa_audit_reject',
        'api_secret' => 'super-secret-webhook-key',
        'is_default' => false,
    ]);

    $payload = [
        'id' => 'evt_gowa_audit_reject',
        'event' => 'session.status',
        'session' => 'org_gowa_audit_reject',
        'payload' => ['status' => 'WORKING'],
    ];

    $rawBody = json_encode($payload);

    $this->call('POST', route('webhook.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Gowa-Signature' => 'signature-salah',
    ], $rawBody)->assertStatus(401);

    $log = WebhookLog::where('provider_event_id', 'evt_gowa_audit_reject')->first();
    expect($log)->not->toBeNull()
        ->and($log->status_proses)->toBe(StatusWebhookLog::Gagal)
        ->and($log->catatan_error)->toBe('Signature webhook tidak valid.');
});

test('webhook log whatsapp event terlabel gowa saat cocok dengan koneksi gowa', function () {
    Sysblas::factory()->create([
        'provider' => SysblasProvider::Gowa,
        'session_name' => 'org_gowa_label_test',
        'is_default' => false,
    ]);

    $payload = [
        'id' => 'evt_label_gowa',
        'event' => 'session.status',
        'session' => 'org_gowa_label_test',
        'payload' => ['status' => 'WORKING'],
    ];

    $this->postJson(route('webhook.whatsapp'), $payload)->assertOk();

    $log = WebhookLog::where('provider_event_id', 'evt_label_gowa')->first();
    expect($log)->not->toBeNull()
        ->and($log->provider)->toBe('gowa');
});

test('webhook log whatsapp event terlabel waha saat session tidak cocok koneksi manapun', function () {
    $payload = [
        'id' => 'evt_label_waha',
        'event' => 'session.status',
        'session' => 'session_tanpa_koneksi_terdaftar',
        'payload' => ['status' => 'WORKING'],
    ];

    $this->postJson(route('webhook.whatsapp'), $payload)->assertOk();

    $log = WebhookLog::where('provider_event_id', 'evt_label_waha')->first();
    expect($log)->not->toBeNull()
        ->and($log->provider)->toBe('waha');
});

test('webhook whatsapp payload flat legacy tanpa id mendapat provider_event_id sintetis', function () {
    $payload = [
        'phone' => '6281234569999',
        'message' => 'Pesan tanpa id unik dari gateway lama',
    ];

    $this->postJson(route('webhook.whatsapp'), $payload)->assertOk();

    $log = WebhookLog::where('provider', 'whatsapp')
        ->where('event_type', 'whatsapp.incoming_message')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->provider_event_id)->not->toBeNull()
        ->and($log->provider_event_id)->toContain(':');
});

test('permintaan webhook whatsapp melebihi batas rate limit menerima 429', function () {
    for ($i = 0; $i < 120; $i++) {
        $this->postJson(route('webhook.whatsapp'), [
            'id' => "rl_test_{$i}",
            'phone' => '6281234567890',
            'status' => 'delivered',
        ])->assertOk();
    }

    $this->postJson(route('webhook.whatsapp'), [
        'id' => 'rl_test_over',
        'phone' => '6281234567890',
        'status' => 'delivered',
    ])->assertStatus(429);
});

test('webhook whatsapp dengan body melebihi batas ukuran ditolak 413', function () {
    $response = $this->call('POST', route('webhook.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'CONTENT_LENGTH' => (string) (2 * 1024 * 1024), // 2MB > batas 1MB, header saja cukup
    ], '{}');

    $response->assertStatus(413);
});
