<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Jobs\Mikrotik\UpdatePppoeProfileJob;
use App\Livewire\Invoice\Create as InvoiceCreate;
use App\Livewire\LayananPelanggan\Create as LayananCreate;
use App\Livewire\Pelanggan\Show;
use App\Models\Invoice;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->router = Router::factory()->online()->create();
    $this->ipPool = IpPool::factory()->create(['router_id' => $this->router->id]);

    $this->profilHome = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Home-20M']);
    $this->paketHome = PaketLayanan::factory()->create([
        'nama_paket' => 'Paket Rumah 20M',
        'profil_bandwidth_id' => $this->profilHome->id,
        'harga' => 150000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);

    $this->profilOffice = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Office-50M']);
    $this->paketOffice = PaketLayanan::factory()->create([
        'nama_paket' => 'Paket Kantor 50M',
        'profil_bandwidth_id' => $this->profilOffice->id,
        'harga' => 450000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);

    $this->pelanggan = Pelanggan::factory()->create([
        'no_reg' => 'BF2408202601',
        'nama_depan' => 'Ahmad',
        'nama_belakang' => 'Dahlan',
    ]);
});

test('satu pelanggan dapat mendaftarkan beberapa layanan bertingkat dengan ppp_username dan site_id unik', function () {
    Queue::fake([ProvisionPppoeAccountJob::class]);

    // 1. Daftarkan Layanan Pertama (Paket Rumah)
    $comp1 = Livewire::actingAs($this->admin)
        ->test(LayananCreate::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paketHome->id)
        ->call('nextStep');

    $pppUsername1 = $comp1->get('ppp_username');
    expect($pppUsername1)->toMatch('/^'.preg_quote($this->pelanggan->no_reg, '/').'_[0-9]{5}$/');

    $comp1->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    $layanan1 = LayananPelanggan::where('ppp_username', $pppUsername1)->first();
    expect($layanan1)->not->toBeNull()
        ->and($layanan1->pelanggan_id)->toBe($this->pelanggan->id)
        ->and($layanan1->paket_layanan_id)->toBe($this->paketHome->id);

    // 2. Daftarkan Layanan Kedua untuk Pelanggan yang Sama (Paket Kantor)
    $comp2 = Livewire::actingAs($this->admin)
        ->test(LayananCreate::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paketOffice->id)
        ->call('nextStep');

    $pppUsername2 = $comp2->get('ppp_username');
    expect($pppUsername2)->toMatch('/^'.preg_quote($this->pelanggan->no_reg, '/').'_[0-9]{5}$/')
        ->and($pppUsername2)->not->toBe($pppUsername1);

    $comp2->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    $layanan2 = LayananPelanggan::where('ppp_username', $pppUsername2)->first();
    expect($layanan2)->not->toBeNull()
        ->and($layanan2->pelanggan_id)->toBe($this->pelanggan->id)
        ->and($layanan2->paket_layanan_id)->toBe($this->paketOffice->id)
        ->and($layanan2->site_id)->not->toBe($layanan1->site_id);

    // Verifikasi relasi di level Pelanggan
    expect($this->pelanggan->fresh()->layanans)->toHaveCount(2);
});

test('generateInvoice otomatis menerbitkan tagihan independen untuk setiap layanan aktif milik pelanggan yang sama', function () {
    $layananHome = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paketHome->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'BF2408202601_00001',
        'status' => StatusLayanan::Aktif,
    ]);

    $layananOffice = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paketOffice->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'BF2408202601_00002',
        'status' => StatusLayanan::Aktif,
    ]);

    $billingService = app(BillingService::class);
    $periode = '2026-09';

    $invoiceHome = $billingService->generateInvoice($layananHome, $this->admin->id, null, null, $periode);
    $invoiceOffice = $billingService->generateInvoice($layananOffice, $this->admin->id, null, null, $periode);

    // Kedua invoice harus berbeda dan terikat ke masing-masing layanan
    expect($invoiceHome->id)->not->toBe($invoiceOffice->id)
        ->and($invoiceHome->layanan_pelanggan_id)->toBe($layananHome->id)
        ->and($invoiceHome->jumlah)->toEqual(150000.0)
        ->and($invoiceOffice->layanan_pelanggan_id)->toBe($layananOffice->id)
        ->and($invoiceOffice->jumlah)->toEqual(450000.0)
        ->and($invoiceHome->pelanggan_id)->toBe($this->pelanggan->id)
        ->and($invoiceOffice->pelanggan_id)->toBe($this->pelanggan->id);

    // Pelanggan memiliki 2 invoice di periode ini
    expect($this->pelanggan->fresh()->invoices)->toHaveCount(2);
});

test('pembayaran invoice layanan A melunasi tagihan layanan A tanpa mengubah status invoice layanan B', function () {
    // tanggal_expired sengaja relatif terhadap "hari ini" (bukan tanggal absolut) agar tetap
    // berada di masa depan kapan pun suite ini dijalankan -- lihat PerpanjangMasaAktifAction
    // yang mengakumulasi hanya bila tanggal_expired masih isFuture().
    $expiredAwal = now()->addDays(20)->startOfDay();

    $layananHome = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paketHome->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'BF2408202601_00001',
        'status' => StatusLayanan::Aktif,
        'tanggal_mulai' => now()->subMonth()->toDateString(),
        'tanggal_expired' => $expiredAwal->toDateString(),
    ]);

    $layananOffice = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paketOffice->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'BF2408202601_00002',
        'status' => StatusLayanan::Aktif,
        'tanggal_mulai' => now()->subMonth()->toDateString(),
        'tanggal_expired' => $expiredAwal->toDateString(),
    ]);

    // InvoicePaidEvent kini juga terpancar untuk pembayaran manual (lihat BillingService::prosesPembayaranManual),
    // yang memicu TriggerMikrotikAktivasiStubListener secara sinkron di lingkungan test (QUEUE_CONNECTION=sync).
    // Test ini fokus pada isolasi status invoice antar layanan, bukan hasil provisioning MikroTik.
    Queue::fake();

    $billingService = app(BillingService::class);
    $invoiceHome = $billingService->generateInvoice($layananHome, $this->admin->id, null, null, '2026-09');
    $invoiceOffice = $billingService->generateInvoice($layananOffice, $this->admin->id, null, null, '2026-09');

    // Bayar lunas tagihan Layanan Home
    $billingService->prosesPembayaranManual($invoiceHome, [
        'jumlah_dibayar' => $invoiceHome->jumlah_setelah_promo,
        'metode' => MetodePembayaran::ManualAdmin,
        'catatan' => 'Pembayaran tunai kasir',
    ], $this->admin);

    $invoiceHome->refresh();
    $invoiceOffice->refresh();

    // Invoice Home LUNAS
    expect($invoiceHome->status)->toBe(StatusInvoice::Lunas)
        ->and($invoiceHome->tanggal_lunas)->not->toBeNull();

    // Masa aktif Layanan Home diperpanjang akumulatif dari expired lama + 1 bulan
    $layananHome->refresh();
    expect(Carbon::parse($layananHome->tanggal_expired)->toDateString())->toBe($expiredAwal->copy()->addMonthNoOverflow()->day(10)->toDateString());

    // Invoice Office TETAP Menunggu Pembayaran
    expect($invoiceOffice->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($invoiceOffice->tanggal_lunas)->toBeNull();

    // Masa aktif Layanan Office tidak berubah
    $layananOffice->refresh();
    expect(Carbon::parse($layananOffice->tanggal_expired)->toDateString())->toBe($expiredAwal->toDateString());
});

test('isolir atau suspend pada layanan A menonaktifkan PPP secret layanan A di MikroTik sedangkan layanan B tetap aktif', function () {
    $layananHome = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paketHome->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'BF2408202601_00001',
        'status' => StatusLayanan::Aktif,
    ]);

    $layananOffice = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paketOffice->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'BF2408202601_00002',
        'status' => StatusLayanan::Aktif,
    ]);

    $mikrotikMock = Mockery::mock(MikrotikService::class);
    // Hanya Layanan Home yang di-disable di router
    $mikrotikMock->shouldReceive('disablePppoeSecret')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            Mockery::on(fn ($l) => $l->ppp_username === 'BF2408202601_00001'),
            true
        )
        ->andReturn(true);

    $this->app->instance(MikrotikService::class, $mikrotikMock);

    // Eksekusi isolir pada layanan Home
    $layananHome->update(['status' => StatusLayanan::Suspend]);
    $mikrotikMock->disablePppoeSecret($this->router, $layananHome, true);

    expect($layananHome->fresh()->status)->toBe(StatusLayanan::Suspend)
        ->and($layananOffice->fresh()->status)->toBe(StatusLayanan::Aktif);
});

test('dropdown invoice manual memfilter daftar layanan sesuai pelanggan yang dipilih', function () {
    $layananHome = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paketHome->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'BF2408202601_00001',
    ]);

    $layananOffice = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paketOffice->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'BF2408202601_00002',
    ]);

    // Pelanggan lain dengan layanannya
    $pelangganLain = Pelanggan::factory()->create(['no_reg' => 'BF2408202699']);
    $layananLain = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'paket_layanan_id' => $this->paketHome->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'BF2408202699_00001',
    ]);

    Livewire::actingAs($this->admin)
        ->test(InvoiceCreate::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->assertSee($layananHome->site_id)
        ->assertSee($layananOffice->site_id)
        ->assertDontSee($layananLain->site_id);
});

test('pendaftaran layanan mendukung nama_site dan koordinat lokasi spesifik per titik pasang', function () {
    Queue::fake([ProvisionPppoeAccountJob::class]);

    $comp = Livewire::actingAs($this->admin)
        ->test(LayananCreate::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paketHome->id)
        ->call('nextStep')
        ->set('nama_site', 'Kantor Cabang Sudirman')
        ->set('alamat_pemasangan', 'Gedung Wisma Sudirman Lt. 5')
        ->set('latitude', -6.2146)
        ->set('longitude', 106.8212)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    $layanan = LayananPelanggan::where('pelanggan_id', $this->pelanggan->id)
        ->where('nama_site', 'Kantor Cabang Sudirman')
        ->first();

    expect($layanan)->not->toBeNull()
        ->and($layanan->alamat_pemasangan)->toBe('Gedung Wisma Sudirman Lt. 5')
        ->and($layanan->latitude)->toBe(-6.2146)
        ->and($layanan->longitude)->toBe(106.8212)
        ->and($layanan->alamat_efektif)->toBe('Gedung Wisma Sudirman Lt. 5')
        ->and($layanan->latitude_efektif)->toBe(-6.2146)
        ->and($layanan->nama_site_label)->toBe('Kantor Cabang Sudirman');
});

test('alamat_efektif dan koordinat_efektif fallback ke master pelanggan jika lokasi site null', function () {
    $this->pelanggan->update([
        'alamat_lengkap' => 'Jl. Kebon Sirih No. 10',
        'latitude' => -6.1818,
        'longitude' => 106.8271,
    ]);

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paketHome->id,
        'nama_site' => null,
        'alamat_pemasangan' => null,
        'latitude' => null,
        'longitude' => null,
    ]);

    expect($layanan->alamat_efektif)->toBe('Jl. Kebon Sirih No. 10')
        ->and($layanan->latitude_efektif)->toBe(-6.1818)
        ->and($layanan->longitude_efektif)->toBe(106.8271)
        ->and($layanan->nama_site_label)->toBe($layanan->site_id);
});

test('staf dapat mengubah paket layanan via modal di halaman detail pelanggan', function () {
    Queue::fake([UpdatePppoeProfileJob::class]);

    $mockMikrotik = Mockery::mock(MikrotikService::class);
    $mockMikrotik->shouldReceive('getPppStatus')->andReturn([
        'is_connected' => true,
        'uptime' => '1h',
        'ip_address' => '10.0.0.50',
    ]);
    $this->app->instance(MikrotikService::class, $mockMikrotik);

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paketHome->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->call('openUbahPaketModal', $layanan->id)
        ->assertSet('showUbahPaketModal', true)
        ->assertSet('selectedLayananId', $layanan->id)
        ->set('newPaketId', $this->paketOffice->id)
        ->call('prosesUbahPaket')
        ->assertSet('showUbahPaketModal', false);

    expect($layanan->fresh()->paket_layanan_id)->toBe($this->paketOffice->id);

    Queue::assertPushed(UpdatePppoeProfileJob::class, function ($job) use ($layanan) {
        return $job->layanan->id === $layanan->id;
    });
});
