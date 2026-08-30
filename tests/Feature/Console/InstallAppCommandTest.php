<?php

use App\Console\Commands\InstallAppCommand;
use App\Enums\UserStatus;
use App\Models\AturanPengingatTagihan;
use App\Models\Pelanggan;
use App\Models\Perusahaan;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WaTemplate;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('app:install non-interaktif berhasil membuat superadmin dan melakukan seed master data', function () {
    $this->artisan(InstallAppCommand::class, [
        '--name' => 'Owner UNMS',
        '--email' => 'owner@gobilling.id',
        '--password' => 'SuperSecret123!',
        '--phone' => '081234567890',
        '--force' => true,
        '--skip-migrate' => true,
        '--skip-storage-link' => true,
    ])
        ->expectsOutputToContain('GOBILLING Production Setup Completed Successfully!')
        ->assertExitCode(0);

    // Assert superadmin user created
    $user = User::where('email', 'owner@gobilling.id')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Owner UNMS')
        ->and($user->phone)->toBe('081234567890')
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('SuperSecret123!', $user->password))->toBeTrue()
        ->and($user->hasRole('super_admin'))->toBeTrue();

    // Assert roles and permissions exist
    expect(Role::where('name', 'super_admin')->exists())->toBeTrue()
        ->and(Role::where('name', 'admin')->exists())->toBeTrue()
        ->and(Permission::where('name', 'pelanggan.lihat')->exists())->toBeTrue();

    // Assert operational master data defaults exist
    expect(Perusahaan::where('is_default', true)->exists())->toBeTrue()
        ->and(WaTemplate::where('kode', 'pengingat_tagihan_h3')->exists())->toBeTrue()
        ->and(AturanPengingatTagihan::where('nama_aturan', 'Pengingat H-3 Tagihan Baru')->exists())->toBeTrue();

    // Assert no fake data was seeded
    expect(Pelanggan::count())->toBe(0)
        ->and(Ticket::count())->toBe(0);
});

test('app:install non-interaktif tanpa opsi password menghasilkan password acak aman', function () {
    $this->artisan(InstallAppCommand::class, [
        '--name' => 'Auto Admin',
        '--email' => 'autoadmin@gobilling.id',
        '--force' => true,
        '--skip-migrate' => true,
        '--skip-storage-link' => true,
    ])
        ->expectsOutputToContain('IMPORTANT: Please store the generated password safely')
        ->assertExitCode(0);

    $user = User::where('email', 'autoadmin@gobilling.id')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('super_admin'))->toBeTrue()
        ->and($user->password)->not->toBeEmpty();
});

test('app:install bersifat idempoten terhadap user yang sudah terdaftar', function () {
    // Buat user biasa sebelumnya
    $existing = User::create([
        'name' => 'Staff Biasa',
        'email' => 'existing@gobilling.id',
        'password' => Hash::make('OldPassword123!'),
        'status' => UserStatus::Inactive,
    ]);

    $this->artisan(InstallAppCommand::class, [
        '--name' => 'Promoted Superadmin',
        '--email' => 'existing@gobilling.id',
        '--password' => 'NewSecurePassword123!',
        '--force' => true,
        '--skip-migrate' => true,
        '--skip-storage-link' => true,
    ])->assertExitCode(0);

    $user = $existing->fresh();
    expect($user->name)->toBe('Promoted Superadmin')
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->hasRole('super_admin'))->toBeTrue()
        ->and(Hash::check('NewSecurePassword123!', $user->password))->toBeTrue();
});

test('ProductionSeeder dapat dijalankan mandiri dan hanya mengisi master data operasional', function () {
    $this->seed(ProductionSeeder::class);

    // Roles and Permissions terisi
    expect(Role::where('name', 'super_admin')->exists())->toBeTrue()
        ->and(Role::where('name', 'noc')->exists())->toBeTrue();

    // Data operasional terisi
    expect(Perusahaan::where('is_default', true)->exists())->toBeTrue()
        ->and(WaTemplate::count())->toBeGreaterThan(0)
        ->and(AturanPengingatTagihan::count())->toBeGreaterThan(0);

    // Tidak ada dummy user dari RolesAndPermissionsSeeder
    expect(User::where('email', 'superadmin@example.com')->exists())->toBeFalse()
        ->and(User::where('email', 'sales@example.com')->exists())->toBeFalse();
});
