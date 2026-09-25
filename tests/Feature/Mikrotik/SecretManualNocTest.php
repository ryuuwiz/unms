<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Exceptions\MikrotikException;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Models\AntrianWaBlast;
use App\Models\MikrotikJobLog;
use App\Models\User;
use App\Notifications\MikrotikJobNotification;
use App\Services\Mikrotik\MikrotikService;
use App\Support\PppDeletionContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Secret Milik Billing / Secret Manual NOC (CONTEXT.md, ADR-0059): tanpa komentar `UNMS:` tidak disentuh.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake([KirimWaBlastJob::class]);
    $this->service = new MikrotikService;
    [$this->router, $this->layanan] = layananPppoeDinamis();
    $this->manual = ['.id' => '*9', 'name' => $this->layanan->ppp_username, 'comment' => 'pelanggan lama, dibuat NOC', 'profile' => 'default', 'password' => 'rahasia-noc'];
});

/**
 * @return list<string>
 */
function endpointSecretTerkirim(ArrayObject $sent): array
{
    return collect($sent)->map(fn ($q) => $q->getEndpoint())
        ->filter(fn (string $e) => in_array($e, ['/ppp/secret/add', '/ppp/secret/set', '/ppp/secret/unset', '/ppp/secret/remove', '/ppp/active/remove'], true))
        ->values()->all();
}

test('provisi, isolir, un-isolir, dan ganti profil menolak Secret Manual NOC bernama sama tanpa mengubahnya', function (string $aksi) {
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [$this->manual], '/ppp/active/print' => [['.id' => '*A', 'name' => $this->layanan->ppp_username]]]);

    expect(fn () => match ($aksi) {
        'provisi' => $this->service->createOrUpdatePppoeSecret($this->router, $this->layanan, $client),
        'isolir' => $this->service->disablePppoeSecret($this->router, $this->layanan, true, $client),
        'unisolir' => $this->service->enablePppoeSecret($this->router, $this->layanan, $client),
        'profil' => $this->service->updatePppoeProfile($this->router, $this->layanan, true, $client),
    })->toThrow(MikrotikException::class, "tanpa komentar 'UNMS:'");

    expect(endpointSecretTerkirim($sent))->toBe([]);
})->with(['provisi', 'isolir', 'unisolir', 'profil']);

test('provisi memakai entri milik billing dan membiarkan Secret Manual NOC bernama sama', function () {
    $milikBilling = ['.id' => '*1', 'name' => $this->layanan->ppp_username, 'comment' => 'UNMS: S1 - Budi'];
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [$this->manual, $milikBilling]]);

    $this->service->createOrUpdatePppoeSecret($this->router, $this->layanan, $client);

    expect(sentAttributes($sent, '/ppp/secret/set'))->toMatchArray(['.id' => '*1'])
        ->and(endpointSecretTerkirim($sent))->not->toContain('/ppp/secret/remove');
});

test('penghapusan tidak pernah menyentuh Secret Manual NOC', function () {
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [$this->manual]]);

    expect($this->service->deletePppoeSecret($this->router, $this->layanan->ppp_username, PppDeletionContext::system('uji', 'Uji'), $client))->toBeFalse()
        ->and(endpointSecretTerkirim($sent))->toBe([]);
});

test('rekonsiliasi melaporkan Secret Manual NOC bernama sama dan tidak menimpa, mengisolir, atau menghapus duplikatnya', function () {
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [$this->manual, $this->manual + ['.id' => '*10']]]);

    $hasil = $this->service->autoRecoverPppSecrets($this->router->fresh(), $client);

    expect(endpointSecretTerkirim($sent))->toBe([])
        ->and(implode(' ', $hasil['errors']))->toContain('Secret Manual NOC')
        ->and($hasil['duplicates_removed'])->toBe(0);
});

test('job provisi yang bentrok dengan Secret Manual NOC gagal tanpa retry, tercatat, dan memberi tahu NOC lewat lonceng dan WhatsApp', function () {
    Notification::fake();
    $noc = User::factory()->create(['phone' => '081234567890']);
    $noc->assignRole('noc');

    $service = Mockery::mock(MikrotikService::class);
    $service->shouldReceive('createOrUpdatePppoeSecret')->once()->andThrow(new MikrotikException("Secret sudah ada tanpa komentar 'UNMS:'"));

    (new ProvisionPppoeAccountJob($this->layanan))->handle($service);

    $log = MikrotikJobLog::where('job_type', MikrotikJobType::ProvisionPppoe)->sole();
    expect($log->status)->toBe(MikrotikJobStatus::Failed)
        ->and(AntrianWaBlast::where('no_hp_tujuan', 'like', '%81234567890')->where('referensi_id', $log->id)->exists())->toBeTrue();
    Notification::assertSentTo($noc, MikrotikJobNotification::class, fn (MikrotikJobNotification $n) => $n->jobLog->is($log));
});
