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

test('super admin dapat melakukan ping test koneksi gateway via Livewire', function () {
    $gateway = PengaturanGatewayModel::create([
        'provider' => 'ipaymu',
        'nama' => 'iPaymu Test Connection',
        'credentials' => ['va' => '1179000899', 'api_key' => 'SANDBOX-KEY'],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => true,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(PengaturanGateway::class)
        ->assertOk()
        ->call('openPingModal', $gateway->id)
        ->assertSet('showPingModal', true)
        ->assertSee('Uji Koneksi API Gateway')
        ->assertSet('pingResult.success', true);
});
