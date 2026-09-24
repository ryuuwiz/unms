<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Events\InvoiceTerbitEvent;
use App\Livewire\Settings\TemplateDeskripsiTagihan as TemplatePage;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\PengaturanPrefixRegistrasi;
use App\Models\Perusahaan;
use App\Models\ProfilBandwidth;
use App\Models\TemplateDeskripsiTagihan;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\PaymentGateway\DeskripsiTagihanBuilder;
use Database\Seeders\PengaturanPrefixRegistrasiSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplateDeskripsiTagihanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([InvoiceTerbitEvent::class]);
    $this->seed([RolesAndPermissionsSeeder::class, PengaturanPrefixRegistrasiSeeder::class, TemplateDeskripsiTagihanSeeder::class]);

    $this->billing = app(BillingService::class);
    $this->builder = app(DeskripsiTagihanBuilder::class);

    $this->pelanggan = Pelanggan::factory()->create(['no_reg' => 'BF2409202601']);
    $this->paket = PaketLayanan::factory()->create([
        'nama_paket' => 'Paket Rumah 20M',
        'profil_bandwidth_id' => ProfilBandwidth::factory()->create()->id,
        'harga' => 150000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'status' => StatusLayanan::Aktif,
        'site_id' => 'SITE-ABC12345',
        'tanggal_expired' => '2026-10-10',
    ]);
});

test('invoice periodik memakai template default dengan brand prefix, bulan, paket, dan tanggal hingga', function () {
    $invoice = $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-10');

    expect($this->builder->buat($invoice))
        ->toBe('BESTFIBER (SITE-ABC12345) Pembayaran Internet Periode Oktober 2026 Paket Rumah 20M hingga 2026-11-10');
});

test('tanggal hingga sama persis dengan tanggal_expired setelah invoice dibayar', function () {
    $invoice = $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-10');
    $deskripsi = $this->builder->buat($invoice);

    $this->billing->prosesPembayaranManual($invoice, [
        'metode' => MetodePembayaran::ManualAdmin,
        'jumlah_dibayar' => $invoice->jumlah_setelah_promo,
    ]);

    expect($deskripsi)->toEndWith('hingga '.$this->layanan->fresh()->tanggal_expired->toDateString());
});

test('brand jatuh ke brand perusahaan untuk prefix tak dikenal atau nonaktif', function () {
    $brandPerusahaan = Perusahaan::default()->nama_brand;

    $this->pelanggan->update(['no_reg' => 'XYZ2409202601']);
    $invoice = $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-10');
    expect($this->builder->buat($invoice))->toStartWith($brandPerusahaan.' (');

    $this->pelanggan->update(['no_reg' => 'BF2409202601']);
    PengaturanPrefixRegistrasi::where('kode', 'BF')->update(['is_active' => false]);
    expect($this->builder->buat($invoice->fresh()))->toStartWith($brandPerusahaan.' (');
});

test('invoice tanpa periode memakai teks tetap dengan keterangan', function () {
    $invoice = $this->billing->generateManualInvoice($this->layanan, 50000, 'Biaya instalasi');

    expect($this->builder->buat($invoice))->toBe("BESTFIBER - Biaya instalasi - Invoice {$invoice->no_invoice}");
});

test('jatuh ke teks lama bila tidak ada default, ada placeholder tak dikenal, dan dipotong 255 karakter', function () {
    $invoice = $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-10');
    $teksLama = "Tagihan Internet UNMS Invoice {$invoice->no_invoice}";

    TemplateDeskripsiTagihan::query()->update(['konten' => 'BESTFIBER {tidak_ada} {site_id}']);
    expect($this->builder->buat($invoice))->toBe($teksLama);

    TemplateDeskripsiTagihan::query()->delete();
    expect($this->builder->buat($invoice))->toBe($teksLama);

    TemplateDeskripsiTagihan::factory()->default()->create(['konten' => str_repeat('A', 400)]);
    expect(mb_strlen($this->builder->buat($invoice)))->toBe(255);
});

test('CRUD: placeholder tak dikenal ditolak, default selalu satu, default tidak bisa dihapus', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)->test(TemplatePage::class)
        ->call('openCreateModal')
        ->set('nama', 'Salah')
        ->set('konten', '{brand} {tidak_ada}')
        ->call('simpan')
        ->assertHasErrors(['konten']);

    Livewire::actingAs($admin)->test(TemplatePage::class)
        ->call('openCreateModal')
        ->set('nama', 'Baru')
        ->set('konten', '{brand} {no_invoice}')
        ->set('is_default', true)
        ->call('simpan')
        ->assertHasNoErrors();

    $baru = TemplateDeskripsiTagihan::where('nama', 'Baru')->firstOrFail();
    expect($baru->is_default)->toBeTrue()
        ->and(TemplateDeskripsiTagihan::where('is_default', true)->count())->toBe(1);

    $lama = TemplateDeskripsiTagihan::where('nama', 'Default Pembayaran Internet')->firstOrFail();

    Livewire::actingAs($admin)->test(TemplatePage::class)
        ->call('hapus', $baru->id);
    expect(TemplateDeskripsiTagihan::find($baru->id))->not->toBeNull();

    Livewire::actingAs($admin)->test(TemplatePage::class)
        ->call('jadikanDefault', $lama->id)
        ->call('hapus', $baru->id);

    expect(TemplateDeskripsiTagihan::find($baru->id))->toBeNull()
        ->and($lama->fresh()->is_default)->toBeTrue();
});

test('pengguna tanpa izin payment_gateway tidak bisa mengubah template', function () {
    $teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $teknisi->assignRole('teknisi');

    Livewire::actingAs($teknisi)->test(TemplatePage::class)
        ->call('openCreateModal')
        ->assertForbidden();
});

test('migrasi backfill membuat default bila belum ada dan mengganti nama BF hanya dari nilai lama', function () {
    $migrasi = require glob(database_path('migrations/*_backfill_template_deskripsi_tagihan_dan_prefix_bestfiber.php'))[0];

    TemplateDeskripsiTagihan::query()->delete();
    PengaturanPrefixRegistrasi::where('kode', 'BF')->update(['nama' => 'Bestfiber']);

    $migrasi->up();
    $migrasi->up();

    expect(TemplateDeskripsiTagihan::where('is_default', true)->count())->toBe(1)
        ->and(PengaturanPrefixRegistrasi::where('kode', 'BF')->value('nama'))->toBe('BESTFIBER');

    PengaturanPrefixRegistrasi::where('kode', 'BF')->update(['nama' => 'Best Fiber Custom']);
    $migrasi->up();
    expect(PengaturanPrefixRegistrasi::where('kode', 'BF')->value('nama'))->toBe('Best Fiber Custom');
});
