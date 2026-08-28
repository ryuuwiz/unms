<?php

use App\Enums\UserStatus;
use App\Livewire\Settings\PengaturanGateway;
use App\Models\PengaturanGateway as PengaturanGatewayModel;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');
});

test('pengaturan gateway memiliki nilai default fee non-nol sesuai riset pasar', function () {
    $setting = PengaturanGatewayModel::getXenditSetting();

    expect((float) $setting->fee_va_nominal)->toBe(4000.0)
        ->and((float) $setting->fee_qris_persen)->toBe(0.70)
        ->and((float) $setting->fee_qris_nominal)->toBe(0.0)
        ->and($setting->bebankan_ke_pelanggan)->toBeTrue();
});

test('super admin dapat mengakses dan menyimpan konfigurasi fee gateway', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(PengaturanGateway::class)
        ->assertOk()
        ->set('fee_va_nominal', 4500)
        ->set('fee_qris_persen', 0.75)
        ->set('bebankan_ke_pelanggan', true)
        ->call('save')
        ->assertHasNoErrors();

    $setting = PengaturanGatewayModel::getXenditSetting();
    expect((float) $setting->fee_va_nominal)->toBe(4500.0)
        ->and((float) $setting->fee_qris_persen)->toBe(0.75)
        ->and($setting->bebankan_ke_pelanggan)->toBeTrue();
});

test('super admin dapat melakukan ping test koneksi gateway via Livewire', function () {
    $gateway = PengaturanGatewayModel::create([
        'provider' => 'xendit',
        'nama' => 'Xendit Test Connection',
        'credentials' => [
            'secret_key' => 'xnd_development_dummy_key',
        ],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => true,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(PengaturanGateway::class)
        ->assertOk()
        ->call('openPingModal', $gateway->id)
        ->assertSet('showPingModal', true)
        ->assertSee('Uji Koneksi API Gateway');
});
