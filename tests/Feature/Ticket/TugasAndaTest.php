<?php

use App\Actions\Ticket\UbahStatusDivisiTicketAction;
use App\Enums\StatusLayanan;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusDivisiTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
use App\Livewire\Ticket\Create as TicketCreate;
use App\Livewire\Ticket\Show;
use App\Models\LayananPelanggan;
use App\Models\OdpPort;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');

    $this->pengguna = collect(['super_admin', 'admin', 'noc', 'customer_service', 'teknisi'])
        ->mapWithKeys(function (string $peran) {
            $user = User::factory()->create(['status' => UserStatus::Active]);
            $user->assignRole($peran);

            return [$peran => $user];
        });

    $this->layanan = LayananPelanggan::factory()->create([
        'status' => StatusLayanan::Proses,
        'latitude' => -6.2,
        'longitude' => 106.8,
        'router_id' => null,
        'ppp_username' => null,
    ]);

    Livewire::actingAs($this->pengguna['admin'])
        ->test(TicketCreate::class)
        ->set('jenis', JenisTicket::Pemasangan->value)
        ->set('pelanggan_id', $this->layanan->pelanggan_id)
        ->set('layanan_pelanggan_id', $this->layanan->id)
        ->set('pic_id', $this->pengguna['teknisi']->id)
        ->set('deskripsi', 'Pemasangan baru untuk uji panel aksi divisi.')
        ->call('save')
        ->assertHasNoErrors();

    $this->ticket = Ticket::where('layanan_pelanggan_id', $this->layanan->id)->firstOrFail();
});

test('Teknisi PIC melihat Pengerjaan Lapangan di panel Tugas Anda', function () {
    Livewire::actingAs($this->pengguna['teknisi'])
        ->test(Show::class, ['ticket' => $this->ticket])
        ->assertSee('Tugas Anda')
        ->assertSeeHtml('data-tugas="teknisi:lapangan"')
        ->assertDontSeeHtml('data-tugas="noc:');
});

test('NOC melihat Aktivasi nonaktif beserta alasan sebelum Teknisi progress, lalu aktif sesudahnya', function () {
    Livewire::actingAs($this->pengguna['noc'])
        ->test(Show::class, ['ticket' => $this->ticket])
        ->assertSeeHtml('data-tugas="noc:aktivasi"')
        ->assertSee('Teknisi harus memilih ODP+Port dan mengunggah minimal 1 foto bukti dulu.');

    $this->ticket->pemasangan()->updateOrCreate([], ['odp_port_id' => OdpPort::factory()->create()->id]);
    $this->ticket->addMedia(UploadedFile::fake()->image('kabel.jpg'))->toMediaCollection('foto_pemasangan');
    app(UbahStatusDivisiTicketAction::class)->execute($this->ticket, DivisiTicket::Teknisi, StatusDivisiTicket::Progress, $this->pengguna['admin']);

    Livewire::actingAs($this->pengguna['noc'])
        ->test(Show::class, ['ticket' => $this->ticket->fresh()])
        ->assertSeeHtml('data-tugas="noc:aktivasi"')
        ->assertDontSee('Teknisi harus memilih ODP+Port dan mengunggah minimal 1 foto bukti dulu.');
});

test('Admin melihat alasan gate invoice pertama belum Lunas di panel Tugas Anda', function () {
    Livewire::actingAs($this->pengguna['admin'])
        ->test(Show::class, ['ticket' => $this->ticket])
        ->assertSeeHtml('data-tugas="admin:proses"')
        ->assertSee('Invoice pertama belum Lunas');
});

test('super_admin melihat semua aksi divisi tertunda sesuai urutan alur', function () {
    Livewire::actingAs($this->pengguna['super_admin'])
        ->test(Show::class, ['ticket' => $this->ticket])
        ->assertSeeHtmlInOrder([
            'data-tugas="teknisi:lapangan"',
            'data-tugas="noc:aktivasi"',
            'data-tugas="customer_service:proses"',
            'data-tugas="admin:proses"',
        ]);
});

test('panel Tugas Anda disembunyikan bila tidak ada aksi', function () {
    app(UbahStatusDivisiTicketAction::class)->execute($this->ticket, DivisiTicket::CustomerService, StatusDivisiTicket::Selesai, $this->pengguna['admin']);

    Livewire::actingAs($this->pengguna['customer_service'])
        ->test(Show::class, ['ticket' => $this->ticket->fresh()])
        ->assertDontSee('Tugas Anda');

    $this->ticket->update(['status' => StatusTicket::Batal]);

    Livewire::actingAs($this->pengguna['super_admin'])
        ->test(Show::class, ['ticket' => $this->ticket->fresh()])
        ->assertDontSee('Tugas Anda');
});
