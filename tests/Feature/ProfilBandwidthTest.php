<?php

use App\Enums\UserStatus;
use App\Livewire\ProfilBandwidth\Create;
use App\Livewire\ProfilBandwidth\Index;
use App\Models\ProfilBandwidth;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->nocUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->nocUser->assignRole('noc');
});

test('noc user can create profil bandwidth with burst configuration', function () {
    Livewire::actingAs($this->nocUser)
        ->test(Create::class)
        ->set('nama_bandwidth', '50Mbps-Burst')
        ->set('max_limit_tx', 50)
        ->set('max_limit_rx', 50)
        ->set('priority', 6)
        ->set('useBurst', true)
        ->set('burst_rate_tx', 75)
        ->set('burst_rate_rx', 75)
        ->set('burst_threshold_tx', 40)
        ->set('burst_threshold_rx', 40)
        ->set('burst_time_tx', 16)
        ->set('burst_time_rx', 16)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('profil-bandwidth.index'));

    $profil = ProfilBandwidth::where('nama_bandwidth', '50Mbps-Burst')->first();
    expect($profil)->not->toBeNull()
        ->and($profil->max_limit_tx)->toBe(50)
        ->and($profil->hasBurst())->toBeTrue()
        ->and($profil->burst_rate_tx)->toBe(75)
        ->and($profil->labelKecepatan())->toBe('50 Mbps (1:1)')
        ->and($profil->routerOsMaxLimit())->toBe('50M/50M')
        ->and($profil->routerOsRateLimit())->toBe('50M/50M 75M/75M 40M/40M 16/16 6 50M/50M');
});

test('burst validation fails when burst rate is lower than max limit', function () {
    Livewire::actingAs($this->nocUser)
        ->test(Create::class)
        ->set('nama_bandwidth', 'Invalid-Burst')
        ->set('max_limit_tx', 50)
        ->set('max_limit_rx', 50)
        ->set('useBurst', true)
        ->set('burst_rate_tx', 30) // Less than max limit 50
        ->set('burst_rate_rx', 30)
        ->set('burst_threshold_tx', 20)
        ->set('burst_threshold_rx', 20)
        ->set('burst_time_tx', 16)
        ->set('burst_time_rx', 16)
        ->call('save')
        ->assertHasErrors(['burst_rate_tx', 'burst_rate_rx']);
});

test('model helper methods return correct string for standard profile', function () {
    $profil = ProfilBandwidth::factory()->create([
        'nama_bandwidth' => '20M-Standard',
        'max_limit_tx' => 10,
        'max_limit_rx' => 20,
    ]);

    expect($profil->labelKecepatan())->toBe('10/20 Mbps')
        ->and($profil->routerOsMaxLimit())->toBe('10M/20M')
        ->and($profil->routerOsRateLimit())->toBe('10M/20M');
});

test('can list and delete unused profil bandwidth', function () {
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => '100Mbps-Dedicated']);

    Livewire::actingAs($this->nocUser)
        ->test(Index::class)
        ->assertSee('100Mbps-Dedicated')
        ->call('confirmDelete', $profil->id)
        ->call('deleteProfilBandwidth')
        ->assertHasNoErrors();

    expect(ProfilBandwidth::find($profil->id))->toBeNull();
});
