<?php

use App\Services\Xendit\XenditWebhookVerifier;
use App\Models\PengaturanGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('verifikasi mengembalikan true jika x-callback-token cocok dengan pengaturan gateway', function () {
    PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Test',
        'credentials' => ['callback_token' => 'valid_secret_token_123'],
        'is_default' => true,
        'is_active' => true,
    ]);

    $verifier = new XenditWebhookVerifier;

    $request = Request::create('/webhook/payment/xendit', 'POST', [], [], [], [
        'HTTP_X_CALLBACK_TOKEN' => 'valid_secret_token_123',
    ]);

    expect($verifier->verifikasi($request))->toBeTrue();
});

test('verifikasi mengembalikan false jika token salah atau kosong', function () {
    PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Test',
        'credentials' => ['callback_token' => 'valid_secret_token_123'],
        'is_default' => true,
        'is_active' => true,
    ]);

    $verifier = new XenditWebhookVerifier;

    $requestWrong = Request::create('/webhook/payment/xendit', 'POST', [], [], [], [
        'HTTP_X_CALLBACK_TOKEN' => 'wrong_token',
    ]);

    $requestEmpty = Request::create('/webhook/payment/xendit', 'POST');

    expect($verifier->verifikasi($requestWrong))->toBeFalse()
        ->and($verifier->verifikasi($requestEmpty))->toBeFalse();
});
