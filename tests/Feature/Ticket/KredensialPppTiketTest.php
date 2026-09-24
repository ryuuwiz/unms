<?php

use App\Enums\StatusLayanan;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
use App\Livewire\LayananPelanggan\Index as LayananIndex;
use App\Livewire\Ticket\Create as TicketCreate;
use App\Livewire\Ticket\Show;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');

    $this->noc = User::factory()->create(['status' => UserStatus::Active]);
    $this->noc->assignRole('noc');

    $this->pelanggan = Pelanggan::factory()->create();
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => ProfilBandwidth::factory()->create()->id]);
    $this->router = Router::factory()->online()->create();
    $this->ipPool = IpPool::factory()->create(['router_id' => $this->router->id]);
});

/**
 * @param  array<string, mixed>  $layananAttributes
 */
function buatTiketDenganLayanan(array $layananAttributes, JenisTicket $jenis = JenisTicket::Gangguan): Ticket
{
    $layanan = LayananPelanggan::factory()->create($layananAttributes + [
        'pelanggan_id' => test()->pelanggan->id,
        'paket_layanan_id' => test()->paket->id,
        'router_id' => test()->router->id,
    ]);

    Livewire::actingAs(test()->admin)
        ->test(TicketCreate::class)
        ->set('jenis', $jenis->value)
        ->set('pelanggan_id', test()->pelanggan->id)
        ->set('layanan_pelanggan_id', $layanan->id)
        ->set('pic_id', test()->teknisi->id)
        ->set('deskripsi', 'Tiket uji kredensial PPP layanan.')
        ->call('save')
        ->assertHasNoErrors();

    return Ticket::where('layanan_pelanggan_id', $layanan->id)->firstOrFail();
}

function prosesNoc(Ticket $ticket, string $mode, ?string $password = null): Testable
{
    $component = Livewire::actingAs(test()->noc)
        ->test(Show::class, ['ticket' => $ticket])
        ->call('openProsesModal', DivisiTicket::Noc->value)
        ->set('prosesModeMikrotik', $mode)
        ->set('prosesRouterId', test()->router->id)
        ->set('prosesPaketLayananId', test()->paket->id)
        ->set('prosesCatatan', 'Proses NOC uji kredensial PPP.');

    if ($password !== null) {
        $component->set('prosesPppPassword', $password);
    }

    return $component->call('prosesDivisiSubmit');
}

test('Aktivasi Pemasangan meng-generate password 8 karakter alfanumerik dan mengirimnya ke RouterOS', function () {
    $ticket = buatTiketDenganLayanan([
        'status' => StatusLayanan::Proses, 'router_id' => null, 'ppp_username' => null, 'ppp_password_terenkripsi' => null,
    ], JenisTicket::Pemasangan);

    $mock = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mock);
    $mock->shouldReceive('createOrUpdatePppoeSecret')
        ->once()
        ->withArgs(fn ($router, LayananPelanggan $layanan) => preg_match('/^[A-Za-z0-9]{8}$/', (string) $layanan->ppp_password_terenkripsi) === 1)
        ->andReturn([]);

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $ticket])
        ->set('aktivasiRouterId', $this->router->id)
        ->set('aktivasiIpPoolId', $this->ipPool->id)
        ->call('prosesAktivasi')
        ->assertHasNoErrors();

    expect($ticket->layananPelanggan->fresh()->ppp_password_terenkripsi)->toMatch('/^[A-Za-z0-9]{8}$/');
});

test('Proses NOC mode proses mengisi password kosong pada layanan Proses dan tidak menimpa yang sudah ada', function () {
    $mock = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mock);
    $mock->shouldReceive('createOrUpdatePppoeSecret')->twice()->andReturn([]);

    $kosong = buatTiketDenganLayanan(['status' => StatusLayanan::Proses, 'ppp_password_terenkripsi' => null]);
    prosesNoc($kosong, 'proses')->assertHasNoErrors();
    expect($kosong->layananPelanggan->fresh()->ppp_password_terenkripsi)->toMatch('/^[A-Za-z0-9]{8}$/');

    $terisi = buatTiketDenganLayanan(['status' => StatusLayanan::Proses, 'ppp_password_terenkripsi' => 'lama12345']);
    prosesNoc($terisi, 'proses')->assertHasNoErrors();
    expect($terisi->layananPelanggan->fresh()->ppp_password_terenkripsi)->toBe('lama12345');
});

test('mode Sudah Registrasi Mikrotik mewajibkan password asli, menyimpannya, dan tidak memanggil RouterOS', function () {
    $mock = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mock);
    $mock->shouldNotReceive('createOrUpdatePppoeSecret');

    $ticket = buatTiketDenganLayanan(['status' => StatusLayanan::Proses, 'ppp_password_terenkripsi' => null]);

    prosesNoc($ticket, 'sudah')->assertHasErrors(['prosesPppPassword' => 'required']);
    expect($ticket->layananPelanggan->fresh()->ppp_password_terenkripsi)->toBeNull();

    prosesNoc($ticket, 'sudah', str_repeat('x', 65))->assertHasErrors(['prosesPppPassword' => 'max']);

    prosesNoc($ticket, 'sudah', 'Sp@si-Asli 1!')->assertHasNoErrors();
    expect($ticket->layananPelanggan->fresh()->ppp_password_terenkripsi)->toBe('Sp@si-Asli 1!');
});

test('Proses NOC mode proses pada layanan Aktif berpassword kosong wajib password asli dan tidak menyentuh router', function () {
    $mock = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mock);
    $mock->shouldNotReceive('createOrUpdatePppoeSecret');

    $ticket = buatTiketDenganLayanan(['status' => StatusLayanan::Aktif, 'ppp_password_terenkripsi' => null]);

    prosesNoc($ticket, 'proses')->assertHasErrors(['prosesPppPassword' => 'required']);
    expect($ticket->layananPelanggan->fresh()->ppp_password_terenkripsi)->toBeNull();
});

test('tombol Provisi mengisi password kosong hanya untuk layanan Proses', function () {
    $mock = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mock);
    $mock->shouldReceive('createOrUpdatePppoeSecret')->andReturn([]);

    $proses = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id, 'paket_layanan_id' => $this->paket->id, 'router_id' => $this->router->id,
        'status' => StatusLayanan::Proses, 'ppp_password_terenkripsi' => null,
    ]);
    $aktif = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id, 'paket_layanan_id' => $this->paket->id, 'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif, 'ppp_password_terenkripsi' => null,
    ]);

    Livewire::actingAs($this->noc)->test(LayananIndex::class)
        ->call('provisionLayanan', $proses->id)
        ->call('provisionLayanan', $aktif->id);

    expect($proses->fresh()->ppp_password_terenkripsi)->toMatch('/^[A-Za-z0-9]{8}$/')
        ->and($aktif->fresh()->ppp_password_terenkripsi)->toBeNull();
});

test('Teknisi PIC mengungkap password di tiket terbuka dan aksinya tercatat di audit trail', function () {
    $ticket = buatTiketDenganLayanan(['status' => StatusLayanan::Aktif, 'ppp_username' => 'BF_12345', 'ppp_password_terenkripsi' => 'rahasia88']);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket])
        ->assertSee('BF_12345')
        ->assertDontSee('rahasia88')
        ->call('revealPppPassword')
        ->assertSet('revealedPppPassword', 'rahasia88')
        ->assertSee('rahasia88')
        ->call('sembunyikanPppPassword')
        ->assertDontSee('rahasia88');

    $activity = Activity::where('subject_type', LayananPelanggan::class)
        ->where('subject_id', $ticket->layanan_pelanggan_id)
        ->where('causer_id', $this->teknisi->id)
        ->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->getProperty('action'))->toBe('reveal_ppp_password');
});

test('reveal ditolak pada tiket Selesai/Batal dan untuk peran tanpa izin', function () {
    $ticket = buatTiketDenganLayanan(['status' => StatusLayanan::Aktif, 'ppp_password_terenkripsi' => 'rahasia88']);

    $cs = User::factory()->create(['status' => UserStatus::Active]);
    $cs->assignRole('customer_service');

    Livewire::actingAs($cs)
        ->test(Show::class, ['ticket' => $ticket])
        ->call('revealPppPassword')
        ->assertForbidden();

    $ticket->update(['status' => StatusTicket::Batal]);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket->fresh()])
        ->call('revealPppPassword')
        ->assertForbidden();

    expect($this->noc->can('lihatKredensialPpp', $ticket->fresh()))->toBeFalse();
});

test('tiket sebelum aktivasi menampilkan placeholder tanpa username maupun password', function () {
    $ticket = buatTiketDenganLayanan([
        'status' => StatusLayanan::Proses, 'router_id' => null, 'ppp_username' => null, 'ppp_password_terenkripsi' => null,
    ], JenisTicket::Pemasangan);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket])
        ->assertSee('Menunggu proses NOC')
        ->assertDontSee('PPP Password:');
});

test('migrasi memberi izin lihat_ppp_password ke teknisi dan noc', function () {
    foreach (['teknisi', 'noc'] as $role) {
        Role::findByName($role)->revokePermissionTo('layanan_pelanggan.lihat_ppp_password');
    }
    expect($this->teknisi->fresh()->can('layanan_pelanggan.lihat_ppp_password'))->toBeFalse();

    (require database_path('migrations/2026_09_24_120000_grant_lihat_ppp_password_to_teknisi_and_noc.php'))->up();

    expect($this->teknisi->fresh()->can('layanan_pelanggan.lihat_ppp_password'))->toBeTrue()
        ->and($this->noc->fresh()->can('layanan_pelanggan.lihat_ppp_password'))->toBeTrue();
});
