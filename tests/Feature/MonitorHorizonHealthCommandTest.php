<?php

use App\Console\Commands\MonitorHorizonHealthCommand;
use App\Models\User;
use App\Notifications\HorizonUnhealthyNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('sehat saat horizon aktif dan job terbaru masih dalam ambang batas', function () {
    $master = (object) ['status' => 'running'];

    $masters = Mockery::mock(MasterSupervisorRepository::class);
    $masters->shouldReceive('all')->andReturn([$master]);
    $this->app->instance(MasterSupervisorRepository::class, $masters);

    $jobs = Mockery::mock(JobRepository::class);
    $jobs->shouldReceive('getCompleted')->andReturn([
        (object) ['completed_at' => (string) now()->subMinutes(2)->timestamp],
    ]);
    $this->app->instance(JobRepository::class, $jobs);

    Notification::fake();

    $this->artisan(MonitorHorizonHealthCommand::class)
        ->expectsOutputToContain('Horizon is healthy')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

test('mengirim notifikasi ke super_admin dan noc saat horizon tidak aktif', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $masters = Mockery::mock(MasterSupervisorRepository::class);
    $masters->shouldReceive('all')->andReturn([]);
    $this->app->instance(MasterSupervisorRepository::class, $masters);

    Notification::fake();

    $this->artisan(MonitorHorizonHealthCommand::class)
        ->assertExitCode(1);

    Notification::assertSentTo($superAdmin, HorizonUnhealthyNotification::class);
});

test('mengirim notifikasi saat tidak ada job selesai melewati ambang batas menit', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $master = (object) ['status' => 'running'];
    $masters = Mockery::mock(MasterSupervisorRepository::class);
    $masters->shouldReceive('all')->andReturn([$master]);
    $this->app->instance(MasterSupervisorRepository::class, $masters);

    $jobs = Mockery::mock(JobRepository::class);
    $jobs->shouldReceive('getCompleted')->andReturn([
        (object) ['completed_at' => (string) now()->subMinutes(30)->timestamp],
    ]);
    $this->app->instance(JobRepository::class, $jobs);

    Notification::fake();

    $this->artisan(MonitorHorizonHealthCommand::class, ['--minutes' => 15])
        ->assertExitCode(1);

    Notification::assertSentTo($superAdmin, HorizonUnhealthyNotification::class);
});
