<?php

use App\Actions\IpPublik\LepasIpPublikAction;
use App\Actions\IpPublik\TetapkanIpPublikAction;
use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Livewire\IpPublik\Create;
use App\Livewire\IpPublik\Edit;
use App\Livewire\IpPublik\Index;
use App\Livewire\LayananPelanggan\Edit as LayananEdit;
use App\Models\IpPool;
use App\Models\IpPublik;
use App\Models\LayananPelanggan;
use App\Models\Router;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->router = Router::factory()->create();
});

test('admin dapat menambah IP publik ke inventaris', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('router_id', $this->router->id)
        ->set('alamat_ip', '203.0.113.10')
        ->set('gateway', '203.0.113.1')
        ->set('harga_bulanan', 75000)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('ip-publik.index'));

    $ip = IpPublik::firstWhere('alamat_ip', '203.0.113.10');
    expect($ip)->not->toBeNull()
        ->and($ip->isTersedia())->toBeTrue()
        ->and((float) $ip->harga_bulanan)->toBe(75000.0);
});

test('alamat IP publik tidak boleh berada di rentang IP Pool router yang sama', function () {
    IpPool::factory()->create(['router_id' => $this->router->id, 'rentang_ip_awal' => '10.0.0.2', 'rentang_ip_akhir' => '10.0.0.254']);

    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('router_id', $this->router->id)
        ->set('alamat_ip', '10.0.0.50')
        ->set('gateway', '10.0.0.1')
        ->set('harga_bulanan', 0)
        ->call('save')
        ->assertHasErrors('alamat_ip');

    expect(IpPublik::count())->toBe(0);
});

test('alamat IP publik harus unik', function () {
    IpPublik::factory()->create(['alamat_ip' => '203.0.113.10']);

    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('router_id', $this->router->id)
        ->set('alamat_ip', '203.0.113.10')
        ->set('gateway', '203.0.113.1')
        ->set('harga_bulanan', 0)
        ->call('save')
        ->assertHasErrors('alamat_ip');
});

test('user tanpa izin tidak dapat membuka inventaris IP publik', function () {
    $sales = User::factory()->create();
    $sales->assignRole('sales');

    $this->actingAs($sales)->get(route('ip-publik.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('ip-publik.index'))->assertOk();
});

test('router dan alamat IP terkunci saat IP dipakai layanan, harga snapshot tidak berubah', function () {
    $layanan = LayananPelanggan::factory()->create(['router_id' => $this->router->id]);
    $ip = IpPublik::factory()->create([
        'router_id' => $this->router->id,
        'layanan_pelanggan_id' => $layanan->id,
        'harga_bulanan' => 50000,
        'harga_ditagih' => 50000,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Edit::class, ['ipPublik' => $ip])
        ->set('alamat_ip', '203.0.113.99')
        ->call('save')
        ->assertHasErrors('alamat_ip');

    Livewire::actingAs($this->admin)
        ->test(Edit::class, ['ipPublik' => $ip])
        ->set('harga_bulanan', 90000)
        ->call('save')
        ->assertHasNoErrors();

    $ip->refresh();
    expect((float) $ip->harga_bulanan)->toBe(90000.0)
        ->and((float) $ip->harga_ditagih)->toBe(50000.0);
});

test('IP publik terpakai tidak dapat dihapus dari inventaris', function () {
    $layanan = LayananPelanggan::factory()->create(['router_id' => $this->router->id]);
    $ip = IpPublik::factory()->create(['router_id' => $this->router->id, 'layanan_pelanggan_id' => $layanan->id]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('confirmDelete', $ip->id)
        ->call('deleteIpPublik');

    expect(IpPublik::find($ip->id))->not->toBeNull();

    $ip->update(['layanan_pelanggan_id' => null]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('confirmDelete', $ip->id)
        ->call('deleteIpPublik');

    expect(IpPublik::find($ip->id))->toBeNull();
});

test('menetapkan IP publik menyalin harga, mengubah secret jadi literal, dan memutus sesi', function () {
    $pool = IpPool::factory()->create(['router_id' => $this->router->id]);
    $layanan = LayananPelanggan::factory()->create(['router_id' => $this->router->id, 'ip_pool_id' => $pool->id]);
    $ip = IpPublik::factory()->create(['router_id' => $this->router->id, 'harga_bulanan' => 60000, 'gateway' => '203.0.113.1']);

    app(TetapkanIpPublikAction::class)->execute($ip, $layanan);

    $layanan = $layanan->fresh();
    expect((float) $ip->fresh()->harga_ditagih)->toBe(60000.0)
        ->and($layanan->resolveRemoteAddress())->toBe($ip->alamat_ip)
        ->and($layanan->resolveLocalAddress())->toBe('203.0.113.1')
        ->and($layanan->profilePool())->toBeNull()
        ->and($layanan->hargaTambahan())->toBe(60000.0);

    Queue::assertPushed(ProvisionPppoeAccountJob::class, fn ($job) => $job->kickActive && $job->layanan->is($layanan));
});

test('penetapan ditolak untuk router berbeda, IP terpakai, layanan sudah punya IP, atau bukan PPPoE', function () {
    $layanan = LayananPelanggan::factory()->create(['router_id' => $this->router->id]);
    $action = app(TetapkanIpPublikAction::class);

    $lainRouter = IpPublik::factory()->create();
    expect(fn () => $action->execute($lainRouter, $layanan))->toThrow(ValidationException::class);

    $terpakai = IpPublik::factory()->create(['router_id' => $this->router->id, 'layanan_pelanggan_id' => LayananPelanggan::factory()->create(['router_id' => $this->router->id])->id]);
    expect(fn () => $action->execute($terpakai, $layanan))->toThrow(ValidationException::class);

    $pertama = IpPublik::factory()->create(['router_id' => $this->router->id]);
    $kedua = IpPublik::factory()->create(['router_id' => $this->router->id]);
    $action->execute($pertama, $layanan);
    expect(fn () => $action->execute($kedua, $layanan->fresh()))->toThrow(ValidationException::class);

    $statis = LayananPelanggan::factory()->create(['router_id' => $this->router->id, 'jenis_koneksi' => JenisKoneksi::IpStatic]);
    $bebas = IpPublik::factory()->create(['router_id' => $this->router->id]);
    expect(fn () => $action->execute($bebas, $statis))->toThrow(ValidationException::class);
});

test('layanan berstatus proses tidak diprovisi ulang saat IP publik ditetapkan', function () {
    $layanan = LayananPelanggan::factory()->proses()->create(['router_id' => $this->router->id, 'ppp_username' => null]);
    $ip = IpPublik::factory()->create(['router_id' => $this->router->id]);

    app(TetapkanIpPublikAction::class)->execute($ip, $layanan);

    Queue::assertNotPushed(ProvisionPppoeAccountJob::class);
    expect($ip->fresh()->layanan_pelanggan_id)->toBe($layanan->id);
});

test('melepas IP publik mengosongkan snapshot harga dan mengembalikan secret ke pool', function () {
    $layanan = LayananPelanggan::factory()->create(['router_id' => $this->router->id]);
    $ip = IpPublik::factory()->create(['router_id' => $this->router->id, 'layanan_pelanggan_id' => $layanan->id, 'harga_ditagih' => 50000]);

    app(LepasIpPublikAction::class)->execute($ip);

    expect($ip->fresh()->isTersedia())->toBeTrue()
        ->and($ip->fresh()->harga_ditagih)->toBeNull()
        ->and($layanan->fresh()->resolveRemoteAddress())->toBeNull();
    Queue::assertPushed(ProvisionPppoeAccountJob::class, fn ($job) => $job->kickActive);
});

test('layanan berhenti melepas IP publik, suspend tetap memegangnya', function () {
    $layanan = LayananPelanggan::factory()->create(['router_id' => $this->router->id]);
    $ip = IpPublik::factory()->create(['router_id' => $this->router->id, 'layanan_pelanggan_id' => $layanan->id, 'harga_ditagih' => 50000]);

    $layanan->update(['status' => StatusLayanan::Suspend]);
    expect($ip->fresh()->layanan_pelanggan_id)->toBe($layanan->id);

    $layanan->update(['status' => StatusLayanan::Berhenti]);
    expect($ip->fresh()->isTersedia())->toBeTrue()
        ->and($ip->fresh()->harga_ditagih)->toBeNull();
});

test('layanan dihapus melepas IP publik', function () {
    $layanan = LayananPelanggan::factory()->create(['router_id' => $this->router->id]);
    $ip = IpPublik::factory()->create(['router_id' => $this->router->id, 'layanan_pelanggan_id' => $layanan->id, 'harga_ditagih' => 50000]);

    $layanan->delete();

    expect($ip->fresh()->isTersedia())->toBeTrue();
});

test('form edit layanan menetapkan dan melepas IP publik', function () {
    $pool = IpPool::factory()->create(['router_id' => $this->router->id]);
    $layanan = LayananPelanggan::factory()->create(['router_id' => $this->router->id, 'ip_pool_id' => $pool->id, 'ip_static' => null]);
    $ip = IpPublik::factory()->create(['router_id' => $this->router->id, 'harga_bulanan' => 40000]);

    Livewire::actingAs($this->admin)
        ->test(LayananEdit::class, ['layananPelanggan' => $layanan])
        ->set('ip_publik_id', $ip->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($ip->fresh()->layanan_pelanggan_id)->toBe($layanan->id);

    Livewire::actingAs($this->admin)
        ->test(LayananEdit::class, ['layananPelanggan' => $layanan->fresh()])
        ->assertSet('ip_publik_id', $ip->id)
        ->set('ip_publik_id', null)
        ->call('save')
        ->assertHasNoErrors();

    expect($ip->fresh()->isTersedia())->toBeTrue();
});

test('form edit layanan menolak ip_static di dalam rentang IP Pool router', function () {
    IpPool::factory()->create(['router_id' => $this->router->id, 'rentang_ip_awal' => '10.0.0.2', 'rentang_ip_akhir' => '10.0.0.254']);
    $layanan = LayananPelanggan::factory()->create(['router_id' => $this->router->id, 'jenis_koneksi' => JenisKoneksi::IpStatic]);

    Livewire::actingAs($this->admin)
        ->test(LayananEdit::class, ['layananPelanggan' => $layanan])
        ->set('ip_static', '10.0.0.50')
        ->call('save')
        ->assertHasErrors('ip_static');
});
