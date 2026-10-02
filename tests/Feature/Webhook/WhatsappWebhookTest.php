<?php

use App\Enums\StatusWebhookLog;
use App\Enums\Sysblas\SysblasProvider;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Wa\StatusAntrianWa;
use App\Models\AntrianWaBlast;
use App\Models\Pelanggan;
use App\Models\Sysblas;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WebhookLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SysblasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        SysblasSeeder::class,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->gowa = Sysblas::factory()->create([
        'provider' => SysblasProvider::Gowa,
        'session_name' => 'org_gowa_1',
        'api_secret' => 'super-secret-webhook-key',
        'is_default' => false,
    ]);
});

/**
 * @param  array<string, mixed>  $payload
 */
function kirimWebhookGowa(array $payload, string $secret = 'super-secret-webhook-key'): TestResponse
{
    $rawBody = (string) json_encode($payload);

    return test()->call('POST', route('webhook.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Gowa-Signature' => hash_hmac('sha256', $rawBody, $secret),
    ], $rawBody);
}

test('webhook gowa message.ack bertanda tangan memperbarui status antrian menjadi terkirim', function () {
    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $this->gowa->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan Tagihan GOWA',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Diproses,
    ]);

    kirimWebhookGowa([
        'id' => 'evt_gowa_ack',
        'event' => 'message.ack',
        'session' => 'org_gowa_1',
        'payload' => [
            'id' => '3EB0B430B6F8F1D0E053AC120E0A9E5C',
            'to' => '6281234567890@s.whatsapp.net',
            'ack' => 2,
            'ackName' => 'DEVICE',
        ],
    ])->assertOk()->assertJson(['status' => true, 'type' => 'queued']);

    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Terkirim);
});

test('webhook gowa message.ack gagal menandai antrian gagal', function () {
    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $this->gowa->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan Tagihan GOWA',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Diproses,
    ]);

    kirimWebhookGowa([
        'id' => 'evt_gowa_ack_fail',
        'event' => 'message.ack',
        'session' => 'org_gowa_1',
        'payload' => ['id' => 'msg_x', 'to' => '6281234567890@s.whatsapp.net', 'ack' => -1, 'ackName' => 'ERROR'],
    ])->assertOk();

    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Gagal);
});

test('event berbeda untuk pesan yang sama tanpa id event tidak bentrok di webhook log', function () {
    foreach (['message', 'message.revoked'] as $event) {
        kirimWebhookGowa([
            'event' => $event,
            'session' => 'org_gowa_1',
            'payload' => ['id' => 'ACED76A3EFBDD9123A3F6DF3BBAABE88'],
        ])->assertOk();
    }

    expect(WebhookLog::where('provider_event_id', 'like', 'org_gowa_1:%:ACED76A3EFBDD9123A3F6DF3BBAABE88')->count())->toBe(2);
});

test('redelivery event yang sama dibalas 200 duplicate tanpa mencatat ulang', function () {
    $payload = [
        'event' => 'message.revoked',
        'session' => 'org_gowa_1',
        'payload' => ['id' => 'ACED76A3EFBDD9123A3F6DF3BBAABE88'],
    ];

    kirimWebhookGowa($payload)->assertOk()->assertJson(['type' => 'queued']);
    kirimWebhookGowa($payload)->assertOk()->assertJson(['status' => true, 'type' => 'duplicate']);

    expect(WebhookLog::where('provider_event_id', 'org_gowa_1:message.revoked:ACED76A3EFBDD9123A3F6DF3BBAABE88')->count())->toBe(1);
});

test('webhook gowa pesan masuk dicatat ke histori tiket aktif tanpa auto-reply', function () {
    $pelanggan = Pelanggan::factory()->create(['no_hp' => '087711223344']);

    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'jenis' => JenisTicket::Gangguan,
        'prioritas' => PrioritasTicket::Tinggi,
        'status' => StatusTicket::Diproses,
        'dibuat_oleh' => $this->admin->id,
    ]);

    kirimWebhookGowa([
        'id' => 'evt_gowa_msg',
        'event' => 'message',
        'session' => 'org_gowa_1',
        'payload' => [
            'id' => 'msg_gowa_123',
            'from' => '6287711223344@s.whatsapp.net',
            'body' => 'Kabel FO di depan rumah sudah dibersihkan ya pak teknisi',
            'fromMe' => false,
        ],
    ])->assertOk();

    expect($ticket->histori()->latest('id')->first()?->catatan)->toContain('Kabel FO di depan rumah')
        ->and(AntrianWaBlast::where('jenis', 'webhook_autoreply')->exists())->toBeFalse();
});

test('webhook gowa session.status working bertanda tangan mengaktifkan koneksi', function () {
    $this->gowa->update(['is_aktif' => false]);

    kirimWebhookGowa([
        'id' => 'evt_gowa_secure',
        'event' => 'session.status',
        'session' => 'org_gowa_1',
        'payload' => ['status' => 'WORKING'],
    ])->assertOk()->assertJson(['status' => true, 'type' => 'queued']);

    expect($this->gowa->fresh()->is_aktif)->toBeTrue();
});

test('webhook gowa dengan signature salah ditolak 401 dan tercatat gagal', function () {
    $this->gowa->update(['is_aktif' => false]);

    kirimWebhookGowa([
        'id' => 'evt_gowa_bad_sig',
        'event' => 'session.status',
        'session' => 'org_gowa_1',
        'payload' => ['status' => 'WORKING'],
    ], 'secret-palsu')->assertStatus(401)->assertJson(['status' => false, 'type' => 'unauthorized']);

    $log = WebhookLog::where('provider_event_id', 'evt_gowa_bad_sig')->first();
    expect($log->status_proses)->toBe(StatusWebhookLog::Gagal)
        ->and($log->catatan_error)->toBe('Signature webhook tidak valid.')
        ->and($this->gowa->fresh()->is_aktif)->toBeFalse();
});

test('webhook gowa tanpa header signature ditolak 401', function () {
    $this->postJson(route('webhook.whatsapp'), [
        'id' => 'evt_gowa_no_sig',
        'event' => 'session.status',
        'session' => 'org_gowa_1',
        'payload' => ['status' => 'WORKING'],
    ])->assertStatus(401);
});

test('webhook dari koneksi gowa tanpa secret ditolak 401', function () {
    $tanpaSecret = Sysblas::factory()->create([
        'provider' => SysblasProvider::Gowa,
        'session_name' => 'org_gowa_nosecret',
        'api_secret' => null,
        'is_aktif' => false,
        'is_default' => false,
    ]);

    kirimWebhookGowa([
        'id' => 'evt_gowa_nosecret',
        'event' => 'session.status',
        'session' => 'org_gowa_nosecret',
        'payload' => ['status' => 'WORKING'],
    ], '')->assertStatus(401);

    expect($tanpaSecret->fresh()->is_aktif)->toBeFalse();
});

test('webhook waha dengan session yang tidak terdaftar ditolak 401', function () {
    $this->postJson(route('webhook.whatsapp'), [
        'id' => 'evt_label_waha',
        'event' => 'session.status',
        'session' => 'session_tanpa_koneksi_terdaftar',
        'payload' => ['status' => 'WORKING'],
    ])->assertStatus(401);

    $log = WebhookLog::where('provider_event_id', 'evt_label_waha')->first();
    expect($log->provider)->toBe('waha')
        ->and($log->status_proses)->toBe(StatusWebhookLog::Gagal);
});

test('webhook flat legacy palsu tidak dapat menulis histori tiket maupun status antrian', function () {
    $pelanggan = Pelanggan::factory()->create(['no_hp' => '087711223344']);
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'jenis' => JenisTicket::Gangguan,
        'prioritas' => PrioritasTicket::Tinggi,
        'status' => StatusTicket::Diproses,
        'dibuat_oleh' => $this->admin->id,
    ]);
    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $this->gowa->id,
        'no_hp_tujuan' => '6287711223344',
        'pesan' => 'Pesan',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Diproses,
    ]);
    $jumlahHistori = $ticket->histori()->count();

    $this->postJson(route('webhook.whatsapp'), ['phone' => '087711223344', 'message' => 'Pesan palsu'])
        ->assertStatus(401);
    $this->postJson(route('webhook.whatsapp'), ['phone' => '087711223344', 'status' => 'failed'])
        ->assertStatus(401);

    expect($ticket->histori()->count())->toBe($jumlahHistori)
        ->and($antrian->fresh()->status)->toBe(StatusAntrianWa::Diproses);
});

test('webhook log whatsapp event terlabel gowa saat cocok dengan koneksi gowa', function () {
    kirimWebhookGowa([
        'id' => 'evt_label_gowa',
        'event' => 'session.status',
        'session' => 'org_gowa_1',
        'payload' => ['status' => 'WORKING'],
    ])->assertOk();

    expect(WebhookLog::where('provider_event_id', 'evt_label_gowa')->value('provider'))->toBe('gowa');
});

test('permintaan webhook whatsapp melebihi batas rate limit menerima 429', function () {
    for ($i = 0; $i < 120; $i++) {
        $this->postJson(route('webhook.whatsapp'), [
            'id' => "rl_test_{$i}",
            'phone' => '6281234567890',
            'status' => 'delivered',
        ])->assertStatus(401);
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
