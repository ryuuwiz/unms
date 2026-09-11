<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('migration backfill menurunkan limit_per_menit & delay_detik yang masih memakai default lama, tanpa menyentuh baris yang sudah dikustomisasi admin', function () {
    $legacyId = DB::table('sysblas')->insertGetId([
        'nama' => 'Legacy Default 25/300',
        'provider' => 'waha',
        'url_api' => 'https://waha.gobilling.id',
        'api_token' => 'token-legacy',
        'limit_per_menit' => 25,
        'delay_detik' => 300,
        'jitter_detik' => 2,
        'is_typing_simulation' => true,
        'is_default' => false,
        'is_aktif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $legacySeedId = DB::table('sysblas')->insertGetId([
        'nama' => 'Legacy Seeder Default 60',
        'provider' => 'waha',
        'url_api' => 'https://waha.gobilling.id',
        'api_token' => 'token-legacy-seed',
        'limit_per_menit' => 60,
        'delay_detik' => 3,
        'jitter_detik' => 2,
        'is_typing_simulation' => true,
        'is_default' => false,
        'is_aktif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $customizedId = DB::table('sysblas')->insertGetId([
        'nama' => 'Sudah Dikustomisasi Admin',
        'provider' => 'waha',
        'url_api' => 'https://waha.gobilling.id',
        'api_token' => 'token-custom',
        'limit_per_menit' => 10,
        'delay_detik' => 45,
        'jitter_detik' => 2,
        'is_typing_simulation' => true,
        'is_default' => false,
        'is_aktif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (require base_path('database/migrations/2026_09_11_203305_update_sysblas_rate_limit_defaults_for_anti_ban_safety.php'))->up();

    $legacy = DB::table('sysblas')->find($legacyId);
    expect($legacy->limit_per_menit)->toBe(4)
        ->and($legacy->delay_detik)->toBe(15);

    $legacySeed = DB::table('sysblas')->find($legacySeedId);
    expect($legacySeed->limit_per_menit)->toBe(4)
        ->and($legacySeed->delay_detik)->toBe(15);

    $customized = DB::table('sysblas')->find($customizedId);
    expect($customized->limit_per_menit)->toBe(10)
        ->and($customized->delay_detik)->toBe(45);
});
