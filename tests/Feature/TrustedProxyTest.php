<?php

use Illuminate\Support\Facades\Route;

test('Livewire assets use HTTPS behind a TLS-terminating proxy', function () {
    Route::get('/testing/secure-livewire-asset', fn () => response()->json([
        'url' => asset('vendor/livewire/livewire.js'),
    ]));

    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
    ])->getJson('/testing/secure-livewire-asset');

    $response->assertOk();

    expect($response->json('url'))->toStartWith('https://');
});
