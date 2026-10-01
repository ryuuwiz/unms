<?php

use App\Actions\Ticket\AssignPicAction;
use App\Actions\Ticket\UbahStatusTicketAction;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Events\InvoicePaidEvent;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Listeners\TriggerWaNotifikasiStubListener;
use App\Livewire\Ticket\Create;
use App\Livewire\Ticket\Show;
use App\Models\AntrianWaBlast;
use App\Models\Invoice;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Whatsapp\WhatsappClient;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        WaTemplateSeeder::class,
    ]);

    Storage::fake('public');
    Queue::fake([KirimWaBlastJob::class]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->teknisi = User::factory()->create([
        'phone' => '087777777777',
    ]);
    $this->teknisi->assignRole('teknisi');

    $this->pelanggan = Pelanggan::factory()->create([
        'no_hp' => '088888888888',
    ]);
});

test('pembuatan tiket baru dengan lampiran foto tidak mengirim notifikasi ke pelanggan', function () {
    $foto = UploadedFile::fake()->image('kendala.jpg');

    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('jenis', JenisTicket::Gangguan->value)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('prioritas', PrioritasTicket::Tinggi->value)
        ->set('divisis', [DivisiTicket::Teknisi->value])
        ->set('deskripsi', 'Kabel fiber optik putus tertabrak truk di depan rumah.')
        ->set('fotoKendala', $foto)
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::latest('id')->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->getFirstMedia('foto_kendala'))->not->toBeNull();

    $normalizedPelangganPhone = WhatsappClient::normalizePhoneNumber($this->pelanggan->no_hp);
    expect(AntrianWaBlast::where('no_hp_tujuan', $normalizedPelangganPhone)->exists())->toBeFalse();
});

test('foto kendala yang diunggah menampilkan preview gambar sebelum disimpan', function () {
    $foto = UploadedFile::fake()->image('kendala.jpg');

    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('fotoKendala', $foto)
        ->assertSeeHtml('alt="Preview Foto Kendala"');
});

test('penugasan teknisi mengantrikan pesan wa disposisi ke nomor hp teknisi', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'status' => StatusTicket::Baru,
        'dibuat_oleh' => $this->admin->id,
    ]);

    /** @var AssignPicAction $action */
    $action = app(AssignPicAction::class);
    $action->execute(
        ticket: $ticket,
        pic: $this->teknisi,
        actor: $this->admin,
        catatan: 'Harap bawa tangga dan OPM ke lokasi.'
    );

    $normalizedTeknisiPhone = WhatsappClient::normalizePhoneNumber($this->teknisi->phone);
    $antrianTeknisi = AntrianWaBlast::where('no_hp_tujuan', $normalizedTeknisiPhone)
        ->first();

    expect($antrianTeknisi)->not->toBeNull()
        ->and($antrianTeknisi->pesan)->toContain($ticket->nomor_ticket)
        ->and($antrianTeknisi->pesan)->toContain($this->teknisi->name);
});

test('perubahan status tiket tidak mengirim pesan wa ke pelanggan', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'status' => StatusTicket::Baru,
        'dibuat_oleh' => $this->admin->id,
    ]);

    /** @var UbahStatusTicketAction $action */
    $action = app(UbahStatusTicketAction::class);
    $action->execute(
        ticket: $ticket,
        statusBaru: StatusTicket::Diproses,
        actor: $this->admin,
        catatan: 'Teknisi sedang melakukan penyambungan splicing FO.'
    );

    $normalizedPelangganPhone = WhatsappClient::normalizePhoneNumber($this->pelanggan->no_hp);
    expect(AntrianWaBlast::where('no_hp_tujuan', $normalizedPelangganPhone)->exists())->toBeFalse();
});

test('penambahan catatan dengan foto pengerjaan tidak mengirim wa ke pelanggan', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'status' => StatusTicket::Diproses,
        'dibuat_oleh' => $this->admin->id,
    ]);

    $fotoBukti = UploadedFile::fake()->image('bukti_splicing.jpg');

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => $ticket])
        ->set('catatanProses', 'Penyambungan kabel selesai dengan redaman -18 dBm.')
        ->set('catatanIsInternal', false)
        ->set('fotoPengerjaan', $fotoBukti)
        ->call('simpanCatatan')
        ->assertHasNoErrors();

    $histori = $ticket->histori()->latest('id')->first();
    expect($histori)->not->toBeNull()
        ->and($histori->getFirstMedia('foto_pengerjaan'))->not->toBeNull();

    $normalizedPelangganPhone = WhatsappClient::normalizePhoneNumber($this->pelanggan->no_hp);
    expect(AntrianWaBlast::where('no_hp_tujuan', $normalizedPelangganPhone)->exists())->toBeFalse();
});

test('jadwal kunjungan teknisi mengirim notifikasi wa ke pelanggan', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('jenis', JenisTicket::Gangguan->value)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('prioritas', PrioritasTicket::Tinggi->value)
        ->set('divisis', [DivisiTicket::Teknisi->value])
        ->set('pic_id', $this->teknisi->id)
        ->set('dijadwalkan_pada', '2026-10-01T10:00')
        ->set('deskripsi', 'Koneksi pelanggan perlu diperiksa di lokasi.')
        ->call('save')
        ->assertHasNoErrors();

    $antrian = AntrianWaBlast::where('no_hp_tujuan', WhatsappClient::normalizePhoneNumber($this->pelanggan->no_hp))
        ->where('jenis', 'tiket_penjadwalan_teknisi')
        ->first();

    expect($antrian)->not->toBeNull()
        ->and($antrian->pesan)->toContain($this->teknisi->name)
        ->and($antrian->pesan)->toContain('01 Oktober 2026');
});

test('event invoice lunas otomatis mengantrikan pesan wa kuitansi konfirmasi bayar', function () {
    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'jumlah' => 300000,
        'status' => 'lunas',
    ]);

    $pembayaran = Pembayaran::factory()->create([
        'invoice_id' => $invoice->id,
        'jumlah_dibayar' => 300000,
        'metode' => 'payment_gateway',
        'referensi_transaksi' => 'XND-12345678',
    ]);

    $listener = new TriggerWaNotifikasiStubListener;
    $listener->handle(new InvoicePaidEvent($invoice, $pembayaran));

    $normalizedPelangganPhone = WhatsappClient::normalizePhoneNumber($this->pelanggan->no_hp);
    $antrian = AntrianWaBlast::where('no_hp_tujuan', $normalizedPelangganPhone)
        ->where('jenis', 'pembayaran_konfirmasi')
        ->first();

    expect($antrian)->not->toBeNull()
        ->and($antrian->pesan)->toContain('KUITANSI PEMBAYARAN LUNAS')
        ->and($antrian->pesan)->toContain('300.000');
});
