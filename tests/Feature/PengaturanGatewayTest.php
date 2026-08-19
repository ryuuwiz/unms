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
