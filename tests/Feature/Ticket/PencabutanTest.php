<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusOdpPort;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\UserStatus;
use App\Livewire\Ticket\Create as TicketCreate;
use App\Livewire\Ticket\Show;
use App\Models\AntrianWaBlast;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Database\Seeders\AturanPengingatTagihanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->noc = User::factory()->create(['status' => UserStatus::Active]);
    $this->noc->assignRole('noc');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');

    $this->sales = User::factory()->create(['status' => UserStatus::Active]);
    $this->sales->assignRole('sales');

    [$this->router, $this->layanan] = layananPppoeDinamis();
    daftarkanRouterPaket($this->layanan);
    $this->layanan->update(['status' => StatusLayanan::Aktif]);

    $this->mikrotik = Mockery::mock(MikrotikService::class)->makePartial();
    $this->app->instance(MikrotikService::class, $this->mikrotik);
});

test('hanya admin/super_admin yang boleh membuat tiket Pencabutan menurut Policy', function (string $role) {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->assignRole($role);

    expect($user->can('create', [Ticket::class, JenisTicket::Pencabutan]))->toBeFalse();
})->with(['noc', 'teknisi', 'sales']);

test('sales tidak bisa membuat tiket Pencabutan lewat form Buat Tiket', function () {
    Livewire::actingAs($this->sales)
        ->test(TicketCreate::class)
        ->set('jenis', JenisTicket::Pencabutan->value)
        ->set('pelanggan_id', $this->layanan->pelanggan_id)
        ->set('layanan_pelanggan_id', $this->layanan->id)
        ->set('deskripsi', 'Pelanggan minta berhenti berlangganan.')
        ->call('save')
        ->assertForbidden();
});

test('admin membuat tiket Pencabutan otomatis menugaskan divisi NOC dan Teknisi', function () {
    Livewire::actingAs($this->admin)
        ->test(TicketCreate::class)
        ->set('jenis', JenisTicket::Pencabutan->value)
        ->set('pelanggan_id', $this->layanan->pelanggan_id)
        ->set('layanan_pelanggan_id', $this->layanan->id)
        ->set('deskripsi', 'Pelanggan minta berhenti berlangganan.')
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::where('layanan_pelanggan_id', $this->layanan->id)->firstOrFail();

    expect($ticket->getDivisValues())->toEqualCanonicalizing([DivisiTicket::Noc->value, DivisiTicket::Teknisi->value]);
});

test('NOC menghapus PPP Secret dari router lewat Proses NOC pada tiket Pencabutan', function () {
    $this->mikrotik->shouldReceive('deletePppoeSecret')
        ->once()
        ->withArgs(fn ($router, $target) => $router->is($this->router) && $target->is($this->layanan))
        ->andReturn(true);

    $ticket = Ticket::factory()->create([
        'jenis' => JenisTicket::Pencabutan,
        'pelanggan_id' => $this->layanan->pelanggan_id,
        'layanan_pelanggan_id' => $this->layanan->id,
    ]);

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $ticket])
        ->call('hapusSecretPencabutan')
        ->assertOk();

    expect($ticket->fresh())
        ->secretSudahDihapus()->toBeTrue()
        ->secret_dihapus_oleh->toBe($this->noc->id);

    expect(TicketHistori::where('ticket_id', $ticket->id)->where('catatan', 'like', '%Secret berhasil dihapus%')->exists())->toBeTrue();
});

test('Teknisi tidak bisa menghapus PPP Secret; hanya NOC dan Admin', function () {
    $ticket = Ticket::factory()->create([
        'jenis' => JenisTicket::Pencabutan,
        'pelanggan_id' => $this->layanan->pelanggan_id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'pic_id' => $this->teknisi->id,
    ]);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket])
        ->call('hapusSecretPencabutan')
        ->assertForbidden();

    expect($ticket->fresh()->secretSudahDihapus())->toBeFalse();
});

test('Teknisi (PIC) melepas port ODP pada tiket Pencabutan', function () {
    $odp = Odp::factory()->create();
    $port = OdpPort::factory()->create(['odp_id' => $odp->id, 'status' => StatusOdpPort::Terpakai, 'layanan_pelanggan_id' => $this->layanan->id]);
    $this->layanan->update(['odp_port_id' => $port->id]);

    $ticket = Ticket::factory()->create([
        'jenis' => JenisTicket::Pencabutan,
        'pelanggan_id' => $this->layanan->pelanggan_id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'pic_id' => $this->teknisi->id,
    ]);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket])
        ->call('lepasPortOdpPencabutan')
        ->assertOk();

    expect($this->layanan->fresh()->odp_port_id)->toBeNull()
        ->and($port->fresh())->status->toBe(StatusOdpPort::Kosong)->layanan_pelanggan_id->toBeNull();
});

test('Teknisi yang bukan PIC tidak bisa melepas port ODP tiket Pencabutan orang lain', function () {
    $lainTeknisi = User::factory()->create(['status' => UserStatus::Active]);
    $lainTeknisi->assignRole('teknisi');

    $ticket = Ticket::factory()->create([
        'jenis' => JenisTicket::Pencabutan,
        'pelanggan_id' => $this->layanan->pelanggan_id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'pic_id' => $this->teknisi->id,
    ]);

    // TicketPolicy::view() sudah menolak Teknisi yang bukan PIC tiket ini sejak mount().
    Livewire::actingAs($lainTeknisi)
        ->test(Show::class, ['ticket' => $ticket])
        ->assertForbidden();
});

test('pengingat tagihan tidak dikirim untuk invoice milik layanan yang sudah Berhenti', function () {
    $this->seed([WaTemplateSeeder::class, AturanPengingatTagihanSeeder::class]);
    Http::fake();

    $layananAktif = LayananPelanggan::factory()->create(['status' => StatusLayanan::Aktif, 'pelanggan_id' => Pelanggan::factory()->create(['no_hp' => '081111111111'])->id]);
    $layananBerhenti = LayananPelanggan::factory()->create(['status' => StatusLayanan::Berhenti, 'pelanggan_id' => Pelanggan::factory()->create(['no_hp' => '082222222222'])->id]);

    $invoiceAktif = Invoice::factory()->create([
        'pelanggan_id' => $layananAktif->pelanggan_id,
        'layanan_pelanggan_id' => $layananAktif->id,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => now(),
    ]);
    $invoiceBerhenti = Invoice::factory()->create([
        'pelanggan_id' => $layananBerhenti->pelanggan_id,
        'layanan_pelanggan_id' => $layananBerhenti->id,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_jatuh_tempo' => now(),
    ]);

    $this->artisan('invoice:kirim-pengingat --force')->assertSuccessful();

    expect(AntrianWaBlast::where('referensi_id', $invoiceAktif->id)->exists())->toBeTrue()
        ->and(AntrianWaBlast::where('referensi_id', $invoiceBerhenti->id)->exists())->toBeFalse();
});

test('link pembayaran tidak dibuat untuk invoice milik layanan yang sudah Berhenti', function () {
    $this->layanan->update(['status' => StatusLayanan::Berhenti]);

    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->layanan->pelanggan_id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    $url = app(PaymentGatewayManager::class)->resolvePaymentUrl($invoice);

    expect($url)->toBeNull();
});
