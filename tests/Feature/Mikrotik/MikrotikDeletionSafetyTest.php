<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
use App\Exceptions\MikrotikException;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use App\Support\PppDeletionContext;

beforeEach(function () {
    $this->service = new MikrotikService;
    $this->router = Router::factory()->online()->create();
    $this->ctx = PppDeletionContext::system('uji', 'Uji penghapusan');
});

/**
 * @return array<int, string> endpoint query terkirim, berurutan
 */
function endpointsSent(ArrayObject $sent): array
{
    return array_map(fn ($q) => $q->getEndpoint(), $sent->getArrayCopy());
}

test('PppDeletionContext menolak actor atau alasan kosong', function () {
    expect(fn () => new PppDeletionContext('', 'x'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new PppDeletionContext(1, '  '))->toThrow(InvalidArgumentException::class)
        ->and(PppDeletionContext::forUser(null, 'alasan')->actor)->toBe('system:tanpa-user')
        ->and(PppDeletionContext::forUser(7, 'alasan')->actor)->toBe(7);
});

test('deletePppoeSecret menolak username kosong tanpa mengirim query apa pun (?name akan cocok dengan semua secret)', function () {
    [$client, $sent] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => 'a'], ['.id' => '*2', 'name' => 'b']],
    ]);

    expect(fn () => $this->service->deletePppoeSecret($this->router, '  ', $this->ctx, $client))
        ->toThrow(MikrotikException::class, 'kosong');

    $layanan = LayananPelanggan::factory()->create(['router_id' => $this->router->id, 'ppp_username' => null]);
    expect(fn () => $this->service->deletePppoeSecret($this->router, $layanan, $this->ctx, $client))
        ->toThrow(MikrotikException::class)
        ->and($sent)->toHaveCount(0);
});

test('deletePppoeSecret menghapus, memutus sesi, dan mencatat audit dengan actor, alasan, dan snapshot tanpa password', function () {
    [$client, $sent] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => 'budi_11111', 'comment' => 'UNMS: X', 'password' => 'rahasia', 'profile' => 'P10']],
        '/ppp/active/print' => [['.id' => '*A', 'name' => 'budi_11111']],
    ]);

    $hasil = $this->service->deletePppoeSecret($this->router, 'budi_11111', new PppDeletionContext(42, 'Layanan Berhenti'), $client);

    $log = MikrotikJobLog::where('job_type', MikrotikJobType::DeletePppoe)->first();
    expect($hasil)->toBeTrue()
        ->and(endpointsSent($sent))->toContain('/ppp/secret/remove', '/ppp/active/remove')
        ->and($log->status)->toBe(MikrotikJobStatus::Success)
        ->and($log->payload)->toMatchArray(['actor' => 42, 'reason' => 'Layanan Berhenti', 'username' => 'budi_11111', 'outcome' => 'deleted'])
        ->and($log->payload['snapshot'])->toMatchArray(['name' => 'budi_11111', 'profile' => 'P10'])
        ->and($log->payload['snapshot'])->not->toHaveKey('password');
});

test('deletePppoeSecret menolak secret berkomentar MANUAL:/NOC: dan mencatat Dilewati', function (string $comment) {
    [$client, $sent] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => 'noc_user', 'comment' => $comment]],
    ]);

    $hasil = $this->service->deletePppoeSecret($this->router, 'noc_user', $this->ctx, $client);

    $log = MikrotikJobLog::where('job_type', MikrotikJobType::DeletePppoe)->first();
    expect($hasil)->toBeFalse()
        ->and(endpointsSent($sent))->not->toContain('/ppp/secret/remove')
        ->and($log->status)->toBe(MikrotikJobStatus::Dilewati)
        ->and($log->payload['outcome'])->toBe('protected_skipped');
})->with(['MANUAL: buatan NOC', 'noc: teknisi', 'SYSTEM: monitoring', 'WHITELIST: vip']);

test('deletePppoeSecret gagal keras bila RouterOS membalas galat, bukan menganggap secret tidak ada', function () {
    [$client] = fakeRouterOs(['/ppp/secret/print' => ['after' => ['message' => 'not enough permissions (9)']]]);

    expect(fn () => $this->service->deletePppoeSecret($this->router, 'budi_11111', $this->ctx, $client))
        ->toThrow(MikrotikException::class, 'not enough permissions');
});

test('cleanOrphanedPppSecrets menolak mode hapus tanpa konteks', function () {
    [$client] = fakeRouterOs();

    expect(fn () => $this->service->cleanOrphanedPppSecrets($this->router, true, $client))
        ->toThrow(MikrotikException::class, 'konteks');
});

test('cleanOrphanedPppSecrets hanya menghapus orphan berkomentar UNMS:, bukan pola nama, secret NOC, atau akun sistem', function () {
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [
        ['.id' => '*1', 'name' => 'lama_11111', 'comment' => 'UNMS: sisa'],
        ['.id' => '*2', 'name' => 'budi_12345', 'comment' => ''],            // pola nama mirip billing, tanpa label
        ['.id' => '*3', 'name' => 'custom_noc', 'comment' => 'NOC: khusus'],
        ['.id' => '*4', 'name' => 'admin', 'comment' => ''],
        ['.id' => '*5', 'name' => 'manual_klien', 'comment' => ''],
    ]]);

    $hasil = $this->service->cleanOrphanedPppSecrets($this->router, true, $client, $this->ctx);

    $removed = collect($sent)->filter(fn ($q) => $q->getEndpoint() === '/ppp/secret/remove')->count();
    expect($hasil['deleted'])->toBe(1)
        ->and($removed)->toBe(1)
        ->and($hasil['orphans'])->toContain('budi_12345', 'manual_klien')
        ->and(MikrotikJobLog::where('job_type', MikrotikJobType::DeletePppoe)->count())->toBe(1);
});

test('cleanOrphanedPppSecrets mode audit tidak pernah menghapus', function () {
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [['.id' => '*1', 'name' => 'lama_11111', 'comment' => 'UNMS: sisa']]]);

    $hasil = $this->service->cleanOrphanedPppSecrets($this->router, false, $client);

    expect($hasil['mode'])->toBe('audit_only')
        ->and($hasil['orphans_count'])->toBe(1)
        ->and(endpointsSent($sent))->not->toContain('/ppp/secret/remove');
});

test('cleanOrphanedPppSecrets berhenti di batas hapus per eksekusi dan melaporkan kandidat sisa', function () {
    config(['mikrotik.max_deletes_per_run' => 3]);
    $secrets = [];
    foreach (range(1, 7) as $i) {
        $secrets[] = ['.id' => "*{$i}", 'name' => "lama_{$i}", 'comment' => 'UNMS: sisa'];
    }
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => $secrets]);

    $hasil = $this->service->cleanOrphanedPppSecrets($this->router, true, $client, $this->ctx);

    expect($hasil['deleted'])->toBe(3)
        ->and($hasil['cap_exceeded'])->toBeTrue()
        ->and($hasil['skipped_over_cap'])->toHaveCount(4)
        ->and($hasil['errors'][0])->toContain('Batas penghapusan 3');
});

/**
 * Layanan pada $this->router yang punya paket + profil valid.
 */
function layananUntukRekonsiliasi(Router $router, StatusLayanan $status, string $username): LayananPelanggan
{
    [, $layanan] = layananPppoeDinamis();
    $pool = IpPool::where('router_id', $router->id)->first() ?? tap(IpPool::find($layanan->ip_pool_id))->update(['router_id' => $router->id]);
    $layanan->updateQuietly(['router_id' => $router->id, 'ip_pool_id' => $pool->id, 'status' => $status, 'ppp_username' => $username]);

    return $layanan->fresh();
}

test('rekonsiliasi membatalkan diri bila daftar secret dibalas galat, bukan memulihkan semua layanan', function () {
    layananUntukRekonsiliasi($this->router, StatusLayanan::Aktif, 'budi_11111');
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => ['after' => ['message' => 'not enough permissions (9)']]]);

    expect(fn () => $this->service->autoRecoverPppSecrets($this->router->fresh(), $client))
        ->toThrow(MikrotikException::class, 'not enough permissions')
        ->and(endpointsSent($sent))->not->toContain('/ppp/secret/add');
});

test('rekonsiliasi menghapus secret layanan Berhenti dengan jejak, tapi tidak pernah menyentuh layanan Suspend', function () {
    $berhenti = layananUntukRekonsiliasi($this->router, StatusLayanan::Berhenti, 'lama_11111');
    $suspend = layananUntukRekonsiliasi($this->router, StatusLayanan::Suspend, 'nunggak_22222');
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [
        ['.id' => '*1', 'name' => 'lama_11111', 'comment' => 'UNMS: x', 'profile' => 'P10'],
        ['.id' => '*2', 'name' => 'nunggak_22222', 'comment' => 'UNMS: y', 'profile' => 'P10@'.$suspend->ipPool->nama_pool, 'disabled' => 'true', 'password' => $suspend->ppp_password_terenkripsi],
    ]]);

    $hasil = $this->service->autoRecoverPppSecrets($this->router->fresh(), $client);

    $log = MikrotikJobLog::where('job_type', MikrotikJobType::DeletePppoe)->first();
    expect($hasil['terminated_removed'])->toBe(1)
        ->and(MikrotikJobLog::where('job_type', MikrotikJobType::DeletePppoe)->count())->toBe(1)
        ->and($log->payload['username'])->toBe('lama_11111')
        ->and($log->payload['actor'])->toBe('system:reconcile')
        ->and($log->payload['reason'])->toContain("layanan #{$berhenti->id}");
});

test('rekonsiliasi dry-run hanya melaporkan penghapusan layanan Berhenti', function () {
    layananUntukRekonsiliasi($this->router, StatusLayanan::Berhenti, 'lama_11111');
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [['.id' => '*1', 'name' => 'lama_11111', 'comment' => 'UNMS: x']]]);

    $hasil = $this->service->autoRecoverPppSecrets($this->router->fresh(), $client, dryRun: true);

    expect(endpointsSent($sent))->not->toContain('/ppp/secret/remove')
        ->and(collect($hasil['dry_run_changes'])->firstWhere('reason', 'layanan_berhenti')['action'])->toBe('would_remove');
});

test('rekonsiliasi tidak menghapus secret Berhenti yang dilindungi komentar NOC:', function () {
    layananUntukRekonsiliasi($this->router, StatusLayanan::Berhenti, 'lama_11111');
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [['.id' => '*1', 'name' => 'lama_11111', 'comment' => 'NOC: dipertahankan']]]);

    $hasil = $this->service->autoRecoverPppSecrets($this->router->fresh(), $client);

    expect($hasil['terminated_removed'])->toBe(0)
        ->and(endpointsSent($sent))->not->toContain('/ppp/secret/remove');
});

test('rekonsiliasi hanya menghapus duplikat bernama sama untuk username terdaftar, dan menghormati batas hapus', function () {
    $layanan = layananUntukRekonsiliasi($this->router, StatusLayanan::Aktif, 'budi_11111');
    $sinkron = ['profile' => 'P10@'.$layanan->ipPool->nama_pool, 'password' => $layanan->ppp_password_terenkripsi, 'disabled' => 'false'];
    [$client, $sent] = fakeRouterOs(['/ppp/secret/print' => [
        ['.id' => '*1', 'name' => 'budi_11111', 'comment' => 'UNMS: a'] + $sinkron,
        ['.id' => '*2', 'name' => 'budi_11111', 'comment' => 'UNMS: a'] + $sinkron,   // duplikat terdaftar
        ['.id' => '*3', 'name' => 'tamu', 'comment' => ''],
        ['.id' => '*4', 'name' => 'tamu', 'comment' => ''],                                   // duplikat tak terdaftar
    ]]);

    $hasil = $this->service->autoRecoverPppSecrets($this->router->fresh(), $client);

    $removed = collect($sent)->filter(fn ($q) => $q->getEndpoint() === '/ppp/secret/remove')
        ->map(fn ($q) => array_values($q->getAttributes())[0])->values()->all();
    expect($removed)->toBe(['=.id=*2'])
        ->and($hasil['duplicates_removed'])->toBe(1)
        ->and($hasil['unmanaged_duplicates'])->toBe(['tamu']);
});

test('syncIpPool gagal bila RouterOS menolak, dan sync_status menjadi failed (bukan success)', function () {
    $pool = IpPool::factory()->create(['router_id' => $this->router->id]);
    [$client] = fakeRouterOs(['/ip/pool/add' => ['after' => ['message' => 'failure: ranges invalid']]]);

    expect(fn () => $this->service->syncIpPool($this->router, $pool, $client))->toThrow(MikrotikException::class, 'ranges invalid');

    $pool->refresh();
    expect($pool->sync_status)->toBe('failed')->and($pool->applied_to_router_at)->toBeNull();
});

test('disablePppoeSecret gagal bila set disabled ditolak, dan menolak layanan tanpa username', function () {
    $layanan = LayananPelanggan::factory()->create(['router_id' => $this->router->id, 'ppp_username' => 'budi_11111']);
    [$client] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => 'budi_11111']],
        '/ppp/secret/set' => ['after' => ['message' => 'failure: denied']],
    ]);

    expect(fn () => $this->service->disablePppoeSecret($this->router, $layanan, true, $client))->toThrow(MikrotikException::class, 'denied');

    $tanpaNama = LayananPelanggan::factory()->create(['router_id' => $this->router->id, 'ppp_username' => null]);
    [$client2, $sent2] = fakeRouterOs();
    expect(fn () => $this->service->disablePppoeSecret($this->router, $tanpaNama, true, $client2))->toThrow(MikrotikException::class)
        ->and($sent2)->toHaveCount(0);
});

test('removeActiveSession menganggap sesi yang sudah berakhir (no such item) bukan kegagalan', function () {
    [$client] = fakeRouterOs([
        '/ppp/active/print' => [['.id' => '*A', 'name' => 'budi_11111']],
        '/ppp/active/remove' => ['after' => ['message' => 'no such item (4)']],
    ]);

    expect($this->service->removeActiveSession($this->router, 'budi_11111', $client))->toBeTrue();
});

test('removeIpPool hanya membersihkan objek bertanda UNMS dan mencatat audit', function () {
    [$client, $sent] = fakeRouterOs([
        '/ppp/profile/print' => [
            ['.id' => '*P1', 'name' => 'P10@Pool-X', 'comment' => 'UNMS: P10'],
            ['.id' => '*P2', 'name' => 'Manual@Pool-X', 'comment' => 'buatan noc'],
        ],
        '/queue/simple/print' => [['.id' => '*Q', 'name' => 'POOL-Pool-X', 'comment' => 'UNMS Managed Pool Queue']],
        '/ip/pool/print' => [['.id' => '*IP', 'name' => 'Pool-X', 'comment' => 'UNMS Managed IP Pool']],
    ]);

    $hasil = $this->service->removeIpPool($this->router, 'Pool-X', $this->ctx, $client);

    $ids = collect($sent)->filter(fn ($q) => str_ends_with((string) $q->getEndpoint(), '/remove'))->map(fn ($q) => array_values($q->getAttributes())[0])->values()->all();
    expect($hasil['removed'])->toHaveCount(3)
        ->and($ids)->toBe(['=.id=*P1', '=.id=*Q', '=.id=*IP'])
        ->and(MikrotikJobLog::where('job_type', MikrotikJobType::DeleteIpPool)->first()->payload)->toMatchArray(['actor' => 'system:uji', 'pool' => 'Pool-X']);
});
