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
 * Kepemilikan objek router dari nama (CONTEXT.md "Secret Milik Billing / Secret Manual NOC", ADR-0063):
 * secret bernama sama dengan username PPP layanan dikelola billing; hanya komentar penanda NOC
 * (MANUAL:/NOC:/SYSTEM:/WHITELIST:) yang melindungi dari penghapusan.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake([KirimWaBlastJob::class]);
    $this->service = new MikrotikService;
    [$this->router, $this->layanan] = layananPppoeDinamis();
    $this->tanpaKomentar = ['.id' => '*9', 'name' => $this->layanan->ppp_username, 'comment' => 'pelanggan lama, dibuat NOC', 'profile' => 'default', 'password' => 'rahasia-noc'];
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

test('provisi mengelola secret bernama sama walau tanpa komentar UNMS dan mengosongkan komentarnya', function () {
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [$this->tanpaKomentar]]);

    $this->service->createOrUpdatePppoeSecret($this->router, $this->layanan, $client);

    expect(sentAttributes($sent, '/ppp/secret/set'))->toMatchArray(['.id' => '*9', 'profile' => 'P10', 'comment' => '']);
});

test('penghapusan tetap tidak menyentuh secret berkomentar penanda NOC', function () {
    $dilindungi = ['comment' => 'NOC: jangan dihapus'] + $this->tanpaKomentar;
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [$dilindungi]]);

    expect($this->service->deletePppoeSecret($this->router, $this->layanan->ppp_username, PppDeletionContext::system('uji', 'Uji'), $client))->toBeFalse()
        ->and(endpointSecretTerkirim($sent))->toBe([]);
});

test('rekonsiliasi membereskan duplikat bernama sama kecuali yang berkomentar penanda NOC', function () {
    $dilindungi = ['.id' => '*11', 'comment' => 'MANUAL: cadangan'] + $this->tanpaKomentar;
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [$this->tanpaKomentar, ['.id' => '*10'] + $this->tanpaKomentar, $dilindungi]]);

    $hasil = $this->service->autoRecoverPppSecrets($this->router->fresh(), $client);

    $dihapus = collect($sent)->filter(fn ($q) => $q->getEndpoint() === '/ppp/secret/remove')
        ->map(fn ($q) => $q->getAttributes()[0])->values()->all();

    expect($hasil['duplicates_removed'])->toBe(1)
        ->and($dihapus)->toContain('=.id=*10')
        ->and($dihapus)->not->toContain('=.id=*11');
});

test('job provisi yang gagal permanen tidak di-retry, tercatat, dan memberi tahu NOC lewat lonceng dan WhatsApp', function () {
    Notification::fake();
    $noc = User::factory()->create(['phone' => '081234567890']);
    $noc->assignRole('noc');

    $service = Mockery::mock(MikrotikService::class);
    $service->shouldReceive('createOrUpdatePppoeSecret')->once()->andThrow(new MikrotikException('Paket belum didaftarkan ke router'));

    (new ProvisionPppoeAccountJob($this->layanan))->handle($service);

    $log = MikrotikJobLog::where('job_type', MikrotikJobType::ProvisionPppoe)->sole();
    expect($log->status)->toBe(MikrotikJobStatus::Failed)
        ->and(AntrianWaBlast::where('no_hp_tujuan', 'like', '%81234567890')->where('referensi_id', $log->id)->exists())->toBeTrue();
    Notification::assertSentTo($noc, MikrotikJobNotification::class, fn (MikrotikJobNotification $n) => $n->jobLog->is($log));
});
