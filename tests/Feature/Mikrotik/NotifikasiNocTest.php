<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Jobs\Mikrotik\EnablePppoeAccountJob;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Livewire\NotifikasiLonceng;
use App\Models\MikrotikJobLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake([KirimWaBlastJob::class]);
    [$this->router, $this->layanan] = layananPppoeDinamis();
    $this->noc = User::factory()->create();
    $this->noc->assignRole('noc');
});

test('job yang gagal tanpa pernah menjalankan handle() tetap tercatat dan tidak menimpa log sukses lama', function () {
    $logLama = MikrotikJobLog::create([
        'router_id' => $this->router->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'job_type' => MikrotikJobType::EnablePppoe,
        'status' => MikrotikJobStatus::Success,
        'attempt_count' => 1,
    ]);

    (new EnablePppoeAccountJob($this->layanan))->failed(new MaxAttemptsExceededException('Batas waktu antre habis'));

    expect($logLama->fresh()->status)->toBe(MikrotikJobStatus::Success);

    $logGagal = MikrotikJobLog::where('status', MikrotikJobStatus::Failed)->sole();
    expect($logGagal->job_type)->toBe(MikrotikJobType::EnablePppoe)
        ->and($logGagal->error_message)->toBe('Batas waktu antre habis')
        ->and($this->noc->unreadNotifications()->count())->toBe(1);
});

test('lonceng menampilkan notifikasi user dan bisa menandai dibaca', function () {
    (new EnablePppoeAccountJob($this->layanan))->failed(new MaxAttemptsExceededException('Router tidak merespons'));

    Livewire::actingAs($this->noc)->test(NotifikasiLonceng::class)
        ->assertSee('MikroTik Gagal')
        ->assertSee('Router tidak merespons')
        ->call('tandaiSemuaDibaca');

    expect($this->noc->unreadNotifications()->count())->toBe(0);
});
