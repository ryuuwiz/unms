<?php

use App\Services\Wablas\WablasClient;
use Illuminate\Support\Facades\Http;

test('normalisasi nomor hp berhasil untuk berbagai format indonesia', function () {
    expect(WablasClient::normalizePhoneNumber('081234567890'))->toBe('6281234567890')
        ->and(WablasClient::normalizePhoneNumber('+6281234567890'))->toBe('6281234567890')
        ->and(WablasClient::normalizePhoneNumber('6281234567890'))->toBe('6281234567890')
        ->and(WablasClient::normalizePhoneNumber('81234567890'))->toBe('6281234567890')
        ->and(WablasClient::normalizePhoneNumber('0812-3456-7890'))->toBe('6281234567890')
        ->and(WablasClient::normalizePhoneNumber('0812 3456 7890'))->toBe('6281234567890');
});

test('normalisasi nomor hp mengembalikan null untuk nomor tidak valid', function () {
    expect(WablasClient::normalizePhoneNumber(''))->toBeNull()
        ->and(WablasClient::normalizePhoneNumber('12345'))->toBeNull()
        ->and(WablasClient::normalizePhoneNumber('abcdefghij'))->toBeNull()
        ->and(WablasClient::normalizePhoneNumber('0215551234'))->toBeNull(); // Nomor telepon rumah, bukan seluler
});

test('sendMessage berhasil memanggil endpoint wablas v2 api', function () {
    Http::fake([
        'https://tegal.wablas.com/api/v2/send-message' => Http::response([
            'status' => true,
            'message' => 'Message has been queued',
            'data' => [
                'messages' => [
                    ['phone' => '6281234567890', 'status' => 'pending'],
                ],
            ],
        ], 200),
    ]);

    $client = new WablasClient(
        host: 'https://tegal.wablas.com',
        number: '08970919525',
        token: 'test_token',
        secret: 'test_secret'
    );

    $result = $client->sendMessage('081234567890', 'Halo ini pesan tes');

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('success');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://tegal.wablas.com/api/v2/send-message'
            && $request->header('Authorization')[0] === 'test_token.test_secret'
            && $request['data'][0]['phone'] === '6281234567890';
    });
});

test('getDeviceInfo mengembalikan informasi status device yang terhubung', function () {
    Http::fake([
        'https://tegal.wablas.com/api/device/info' => Http::response([
            'status' => true,
            'message' => 'Success',
            'data' => [
                'phone' => '628970919525',
                'status' => 'connected',
                'quota' => 1500,
                'active_period' => '2026-12-31',
            ],
        ], 200),
    ]);

    $client = new WablasClient(
        host: 'https://tegal.wablas.com',
        number: '08970919525',
        token: 'test_token',
        secret: 'test_secret'
    );

    $info = $client->getDeviceInfo();

    expect($info['connected'])->toBeTrue()
        ->and($info['phone'])->toBe('628970919525')
        ->and($info['quota'])->toBe(1500);
});
