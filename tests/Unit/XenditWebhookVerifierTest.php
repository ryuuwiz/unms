<?php

use App\Services\Xendit\XenditWebhookVerifier;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

test('verifikasi mengembalikan true jika x-callback-token cocok dengan config', function () {
    config(['services.xendit.callback_token' => 'valid_secret_token_123']);

    $verifier = new XenditWebhookVerifier;

    $request = Request::create('/webhook/xendit', 'POST', [], [], [], [
        'HTTP_X_CALLBACK_TOKEN' => 'valid_secret_token_123',
    ]);

    expect($verifier->verifikasi($request))->toBeTrue();
});

test('verifikasi mengembalikan false jika token salah atau kosong', function () {
    config(['services.xendit.callback_token' => 'valid_secret_token_123']);

    $verifier = new XenditWebhookVerifier;

    $requestWrong = Request::create('/webhook/xendit', 'POST', [], [], [], [
        'HTTP_X_CALLBACK_TOKEN' => 'wrong_token',
    ]);

    $requestEmpty = Request::create('/webhook/xendit', 'POST');

    expect($verifier->verifikasi($requestWrong))->toBeFalse()
        ->and($verifier->verifikasi($requestEmpty))->toBeFalse();
});
