<?php

use App\Services\Whatsapp\Drivers\GowaDriver;
use App\Services\Whatsapp\WhatsappClient;
use Illuminate\Support\Facades\Http;

test('normalisasi nomor hp berhasil untuk berbagai format indonesia', function () {
    expect(WhatsappClient::normalizePhoneNumber('081234567890'))->toBe('6281234567890')
        ->and(WhatsappClient::normalizePhoneNumber('+6281234567890'))->toBe('6281234567890')
        ->and(WhatsappClient::normalizePhoneNumber('6281234567890'))->toBe('6281234567890')
        ->and(WhatsappClient::normalizePhoneNumber('81234567890'))->toBe('6281234567890')
        ->and(WhatsappClient::normalizePhoneNumber('0812-3456-7890'))->toBe('6281234567890')
        ->and(WhatsappClient::normalizePhoneNumber('0812 3456 7890'))->toBe('6281234567890');
});

test('normalisasi nomor hp mengembalikan null untuk nomor tidak valid', function () {
    expect(WhatsappClient::normalizePhoneNumber(''))->toBeNull()
        ->and(WhatsappClient::normalizePhoneNumber('12345'))->toBeNull()
        ->and(WhatsappClient::normalizePhoneNumber('abcdefghij'))->toBeNull()
        ->and(WhatsappClient::normalizePhoneNumber('0215551234'))->toBeNull(); // Nomor telepon rumah, bukan seluler
});

test('sendMessage berhasil memanggil endpoint gowa dengan basic auth dan x-device-id', function () {
    Http::fake([
        'http://localhost:3000/send/message' => Http::response([
            'code' => 'SUCCESS',
            'message' => 'Success',
            'results' => ['message_id' => '3EB0B430B6F8F1D0E053AC120E0A9E5C', 'status' => 'message success'],
        ], 200),
    ]);

    $driver = new GowaDriver(
        host: 'http://localhost:3000',
        username: 'admin',
        password: 'secret',
        deviceId: 'org_2',
        number: '08970919525'
    );

    $result = $driver->sendMessage('081234567890', 'Halo ini pesan tes');

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('success');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:3000/send/message'
            && $request->header('X-Device-Id')[0] === 'org_2'
            && $request['phone'] === '6281234567890@s.whatsapp.net';
    });
});

test('sendMessage menandai gagal jika nomor telepon tidak valid', function () {
    $driver = new GowaDriver(host: 'http://localhost:3000', username: 'admin', password: 'secret', deviceId: 'org_2');

    $result = $driver->sendMessage('invalid', 'Halo');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('tidak valid');
});

test('getDeviceInfo mengembalikan status device yang terhubung', function () {
    Http::fake([
        'http://localhost:3000/devices/org_2' => Http::response([
            'code' => 'SUCCESS',
            'message' => 'Device info',
            'status' => 200,
            'results' => [
                'id' => 'org_2',
                'phone_number' => '628970919525',
                'display_name' => 'CS Utama',
                'state' => 'logged_in',
                'jid' => '628970919525@s.whatsapp.net',
            ],
        ], 200),
    ]);

    $driver = new GowaDriver(host: 'http://localhost:3000', username: 'admin', password: 'secret', deviceId: 'org_2');
    $info = $driver->getDeviceInfo();

    expect($info['connected'])->toBeTrue()
        ->and($info['phone'])->toBe('628970919525');
});

test('getDeviceInfo menandai tidak terhubung untuk device berstatus disconnected', function () {
    Http::fake([
        'http://localhost:3000/devices/org_3' => Http::response([
            'code' => 'SUCCESS',
            'message' => 'Device info',
            'status' => 200,
            'results' => [
                'id' => 'org_3',
                'phone_number' => '',
                'display_name' => 'CS Cadangan',
                'state' => 'disconnected',
            ],
        ], 200),
    ]);

    $driver = new GowaDriver(host: 'http://localhost:3000', username: 'admin', password: 'secret', deviceId: 'org_3');
    $info = $driver->getDeviceInfo();

    expect($info['connected'])->toBeFalse();
});

test('checkNumberStatus mengembalikan status terdaftar di whatsapp', function () {
    Http::fake([
        'http://localhost:3000/user/check*' => Http::response([
            'code' => 'SUCCESS',
            'message' => 'Success check user',
            'results' => ['is_on_whatsapp' => true],
        ], 200),
    ]);

    $driver = new GowaDriver(host: 'http://localhost:3000', username: 'admin', password: 'secret', deviceId: 'org_2');
    $result = $driver->checkNumberStatus('081234567890');

    expect($result['success'])->toBeTrue()
        ->and($result['exists'])->toBeTrue();
});

test('listSessions mengembalikan daftar device dari server gowa', function () {
    Http::fake([
        'http://localhost:3000/devices' => Http::response([
            'code' => 'SUCCESS',
            'message' => 'List devices',
            'status' => 200,
            'results' => [
                ['id' => 'org_1', 'phone_number' => '6281234567890', 'display_name' => 'CS Utama', 'state' => 'logged_in'],
                ['id' => 'org_2', 'phone_number' => '', 'display_name' => 'CS Cadangan', 'state' => 'disconnected'],
            ],
        ], 200),
    ]);

    $driver = new GowaDriver(host: 'http://localhost:3000', username: 'admin', password: 'secret', deviceId: 'default');
    $devices = $driver->listSessions();

    expect($devices)->toHaveCount(2)
        ->and($devices[0]['name'])->toBe('org_1')
        ->and($devices[0]['connected'])->toBeTrue()
        ->and($devices[1]['connected'])->toBeFalse();
});

test('getQrCode dan siklus hidup session gowa di-stub karena pairing dilakukan lewat dashboard gowa', function () {
    $driver = new GowaDriver(host: 'http://localhost:3000', username: 'admin', password: 'secret', deviceId: 'org_2');

    expect($driver->getQrCode()['success'])->toBeFalse()
        ->and($driver->startSession()['success'])->toBeTrue()
        ->and($driver->stopSession()['success'])->toBeTrue()
        ->and($driver->restartSession()['success'])->toBeTrue()
        ->and($driver->logoutSession()['success'])->toBeTrue();
});

test('whatsappclient merutekan provider gowa ke gowadriver', function () {
    $client = new WhatsappClient(
        host: 'http://localhost:3000',
        username: 'admin',
        password: 'secret',
        sessionName: 'org_2',
        provider: 'gowa'
    );

    expect($client->getDriver())->toBeInstanceOf(GowaDriver::class);
});
