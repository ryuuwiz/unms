<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Livewire\Pelanggan\Show;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\ProfilBandwidth;
use App\Models\Promo;
use App\Models\Router;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->pelanggan = Pelanggan::factory()->create([
        'nama_depan' => 'Budi',
        'nama_belakang' => 'Santoso',
        'email' => 'budi.santoso@example.com',
        'no_hp' => '628123456789',
        'telepon_rumah' => '0215551234',
        'nik' => '3201123456780001',
        'alamat_lengkap' => 'Jl. Merdeka No. 1, Jakarta',
    ]);
});

test('can render pelanggan detail page with info and tabs', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('628123456789')
        ->assertSee('budi.santoso@example.com')
        ->assertSee('0215551234')
        ->assertSee('Jl. Merdeka No. 1, Jakarta')
        ->assertSee('Akun Portal Pelanggan');
});

test('can toggle NIK visibility', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->assertOk()
        ->assertSee('3201••••••••0001')
        ->call('toggleShowNik')
        ->assertSee('3201123456780001')
        ->call('toggleShowNik')
        ->assertSee('3201••••••••0001');
});

test('can reset portal account password to default', function () {
    $this->pelanggan->akunPelanggan()->update([
        'password' => Hash::make('custompassword123'),
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->call('resetPasswordPortal')
        ->assertHasNoErrors();

    $this->pelanggan->akunPelanggan->refresh();
    expect(Hash::check('12345678', $this->pelanggan->akunPelanggan->password))->toBeTrue();
});

test('renders leaflet map and coordinate details when coordinates exist', function () {
    $pelangganWithCoords = Pelanggan::factory()->create([
        'latitude' => -6.2088000,
        'longitude' => 106.8456000,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $pelangganWithCoords])
        ->assertOk()
        ->assertSee('Lokasi pemasangan')
        ->assertSee('-6.2088000')
        ->assertSee('106.8456000')
        ->assertSee('destination=-6.2088,106.8456', false)
        ->assertSee('Estimasi kabel');
});

test('map-view marker script is not wrapped in a Blade block (Livewire comment markers would comment it out in JS)', function () {
    $compiled = Blade::compileString(file_get_contents(resource_path('views/components/map-view.blade.php')));

    expect($compiled)->toContain('L.marker(')
        ->not->toMatch('/<!--\[if BLOCK\]><!\[endif\]-->(?:(?!x-data).)*L\.marker/s');
});

test('renders empty state when coordinates are not set', function () {
    $pelangganWithoutCoords = Pelanggan::factory()->create([
        'latitude' => null,
        'longitude' => null,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $pelangganWithoutCoords])
        ->assertOk()
        ->assertSee('Lokasi pemasangan')
        ->assertSee('Koordinat belum ditentukan')
        ->assertSee('Atur Titik Koordinat');
});

test('can view billing tab with active, paid, and deleted invoices', function () {
    $profil = ProfilBandwidth::factory()->create();
    $router = Router::factory()->create();
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id, 'harga' => 250000]);

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'router_id' => $router->id,
        'status' => StatusLayanan::Aktif,
    ]);

    // Active invoice
    $activeInv = Invoice::create([
        'no_invoice' => 'INV-202608-000001',
        'periode_tagihan' => '2026-08',
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 250000,
        'jumlah_setelah_promo' => 250000,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_terbit' => Carbon::now(),
        'tanggal_jatuh_tempo' => Carbon::now()->addDays(7),
    ]);

    // Paid invoice
    $paidInv = Invoice::create([
        'no_invoice' => 'INV-202607-000001',
        'periode_tagihan' => '2026-07',
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 250000,
        'jumlah_setelah_promo' => 250000,
        'status' => StatusInvoice::Lunas,
        'tanggal_terbit' => Carbon::now()->subMonth(),
        'tanggal_jatuh_tempo' => Carbon::now()->subMonth()->addDays(7),
        'tanggal_lunas' => Carbon::now()->subMonth()->addDays(2),
    ]);

    Pembayaran::create([
        'invoice_id' => $paidInv->id,
        'metode' => 'manual_admin',
        'jumlah_dibayar' => 250000,
        'dibayar_pada' => Carbon::now()->subMonth()->addDays(2),
        'dicatat_oleh' => $this->superAdmin->id,
    ]);

    // Trashed invoice
    $deletedInv = Invoice::create([
        'no_invoice' => 'INV-202606-000001',
        'periode_tagihan' => '2026-06',
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 250000,
        'jumlah_setelah_promo' => 250000,
        'status' => StatusInvoice::Dibatalkan,
        'tanggal_terbit' => Carbon::now()->subMonths(2),
        'tanggal_jatuh_tempo' => Carbon::now()->subMonths(2)->addDays(7),
        'dihapus_oleh' => $this->superAdmin->id,
        'keterangan_hapus' => 'Salah paket pelanggan',
    ]);
    $deletedInv->delete();

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->call('setTab', 'billing')
        ->assertSet('activeTab', 'billing')
        ->assertSee('Tagihan belum dibayar')
        ->assertSee('INV-202608-000001')
        ->assertSee('Riwayat Pembayaran Lunas')
        ->assertSee('INV-202607-000001')
        ->assertSee('Riwayat Invoice Dihapus / Dibatalkan')
        ->assertSee('INV-202606-000001')
        ->assertSee('Salah paket pelanggan');
});

test('can process payment from quick modal in detail pelanggan', function () {
    $profil = ProfilBandwidth::factory()->create();
    $router = Router::factory()->create();
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id, 'harga' => 300000]);

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'router_id' => $router->id,
        'status' => StatusLayanan::Aktif,
        'tanggal_expired' => Carbon::today(),
    ]);

    $activeInv = Invoice::create([
        'no_invoice' => 'INV-202608-000099',
        'periode_tagihan' => '2026-08',
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 300000,
        'jumlah_setelah_promo' => 300000,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_terbit' => Carbon::now(),
        'tanggal_jatuh_tempo' => Carbon::now()->addDays(7),
    ]);

    // InvoicePaidEvent kini juga terpancar untuk pembayaran manual (lihat BillingService::prosesPembayaranManual),
    // yang memicu TriggerMikrotikAktivasiStubListener secara sinkron di lingkungan test (QUEUE_CONNECTION=sync).
    // Test ini fokus pada alur modal pembayaran, bukan hasil provisioning MikroTik.
    Queue::fake();

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->call('openBayarModal', $activeInv->id)
        ->assertSet('showBayarModal', true)
        ->assertSet('selectedInvoiceId', $activeInv->id)
        ->set('bayarMetode', 'manual_admin')
        ->set('bayarJumlah', 300000)
        ->set('bayarReferensi', 'STRUK-999')
        ->call('prosesBayarInvoice')
        ->assertHasNoErrors()
        ->assertSet('showBayarModal', false);

    $activeInv->refresh();
    expect($activeInv->status)->toBe(StatusInvoice::Lunas);
    expect($activeInv->pembayarans)->toHaveCount(1);
    expect($activeInv->pembayarans->first()->referensi_transaksi)->toBe('STRUK-999');
});

test('can open tambah invoice modal and create manual invoice from detail pelanggan', function () {
    $profil = ProfilBandwidth::factory()->create();
    $router = Router::factory()->create();
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id, 'harga' => 300000]);

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'router_id' => $router->id,
        'status' => StatusLayanan::Aktif,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->call('openTambahInvoiceModal')
        ->assertSet('showTambahInvoiceModal', true)
        ->set('tambahInvoiceLayananId', $layanan->id)
        ->set('tambahInvoiceKeterangan', 'Biaya instalasi pemasangan baru')
        ->set('tambahInvoiceJumlah', 250000)
        ->set('tambahInvoiceTanggalJatuhTempo', now()->addDays(7)->toDateString())
        ->call('simpanTambahInvoice')
        ->assertHasNoErrors()
        ->assertSet('showTambahInvoiceModal', false);

    $invoice = Invoice::where('pelanggan_id', $this->pelanggan->id)->latest('id')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->periode_tagihan)->toBeNull()
        ->and($invoice->keterangan)->toBe('Biaya instalasi pemasangan baru')
        ->and((float) $invoice->jumlah_setelah_promo)->toBe(250000.0)
        ->and($invoice->layanan_pelanggan_id)->toBe($layanan->id);
});

test('kode promo yang diketik manual menerapkan diskon pada tambah invoice modal', function () {
    $profil = ProfilBandwidth::factory()->create();
    $router = Router::factory()->create();
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id, 'harga' => 300000]);

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'router_id' => $router->id,
        'status' => StatusLayanan::Aktif,
    ]);

    $promo = Promo::factory()->create([
        'kode_promo' => 'INSTALL50',
        'diskon_nilai' => 50000,
        'diskon_tipe' => 'nominal',
        'minimal_nominal_invoice' => 0,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->call('openTambahInvoiceModal')
        ->set('tambahInvoiceKodePromo', 'install50')
        ->assertSet('tambahInvoicePromoId', $promo->id)
        ->assertHasNoErrors('tambahInvoiceKodePromo')
        ->set('tambahInvoiceLayananId', $layanan->id)
        ->set('tambahInvoiceKeterangan', 'Biaya instalasi dengan promo')
        ->set('tambahInvoiceJumlah', 250000)
        ->set('tambahInvoiceTanggalJatuhTempo', now()->addDays(7)->toDateString())
        ->call('simpanTambahInvoice')
        ->assertHasNoErrors();

    $invoice = Invoice::where('pelanggan_id', $this->pelanggan->id)->latest('id')->first();
    expect((float) $invoice->jumlah_setelah_promo)->toBe(200000.0)
        ->and($invoice->promo_id)->toBe($promo->id);
});

test('tambah invoice modal menolak layanan milik pelanggan lain', function () {
    $pelangganLain = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create();
    $router = Router::factory()->create();
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id, 'harga' => 300000]);

    $layananLain = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'paket_layanan_id' => $paket->id,
        'router_id' => $router->id,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->call('openTambahInvoiceModal')
        ->set('tambahInvoiceLayananId', $layananLain->id)
        ->set('tambahInvoiceKeterangan', 'Percobaan lintas pelanggan')
        ->set('tambahInvoiceJumlah', 100000)
        ->set('tambahInvoiceTanggalJatuhTempo', now()->addDays(7)->toDateString())
        ->call('simpanTambahInvoice');
})->throws(ModelNotFoundException::class);

test('can switch to audit tab and render activity logs', function () {
    activity()
        ->performedOn($this->pelanggan)
        ->causedBy($this->superAdmin)
        ->log('Memperbarui data pelanggan');

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->call('setTab', 'audit')
        ->assertSet('activeTab', 'audit')
        ->assertSee('Log Aktivitas Data Pelanggan')
        ->assertSee('Memperbarui data pelanggan');
});

test('ppp password is masked by default and reveal button only visible to super admin', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('admin');

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'ppp_password_terenkripsi' => 'plaintext_rahasia',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->set('activeTab', 'subscriptions')
        ->assertSee('Pass: ••••••••')
        ->assertDontSee('plaintext_rahasia')
        ->assertSeeHtml("wire:click=\"revealPppPassword({$layanan->id})\"");

    Livewire::actingAs($admin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->set('activeTab', 'subscriptions')
        ->assertSee('Pass: ••••••••')
        ->assertDontSee('plaintext_rahasia')
        ->assertDontSeeHtml("wire:click=\"revealPppPassword({$layanan->id})\"");
});

test('super admin can reveal ppp password and the reveal is audit logged', function () {
    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'ppp_password_terenkripsi' => 'plaintext_rahasia',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->set('activeTab', 'subscriptions')
        ->call('revealPppPassword', $layanan->id)
        ->assertSet('revealedPppPasswordValue', 'plaintext_rahasia')
        ->assertSee('Pass: plaintext_rahasia');

    $activity = Activity::where('subject_type', LayananPelanggan::class)
        ->where('subject_id', $layanan->id)
        ->where('causer_id', $this->superAdmin->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->getProperty('action'))->toBe('reveal_ppp_password');
});

test('admin without lihat_ppp_password permission cannot reveal ppp password', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('admin');

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'ppp_password_terenkripsi' => 'plaintext_rahasia',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->set('activeTab', 'subscriptions')
        ->call('revealPppPassword', $layanan->id)
        ->assertForbidden();
});

test('handles invalid encrypted nik gracefully without throwing DecryptException', function () {
    $pelangganInvalid = Pelanggan::factory()->create();

    // Simulasikan ciphertext rusak / MAC tidak valid langsung pada level database
    $invalidPayload = base64_encode(json_encode([
        'iv' => base64_encode(random_bytes(16)),
        'value' => base64_encode('corrupted_ciphertext_data'),
        'mac' => hash_hmac('sha256', 'wrong_data', 'wrong_key'),
        'tag' => '',
    ]));

    DB::table('pelanggan')->where('id', $pelangganInvalid->id)->update([
        'nik' => $invalidPayload,
    ]);

    $pelangganInvalid->refresh();

    // Verifikasi bahwa model tidak melempar DecryptException tetapi mengembalikan null
    expect($pelangganInvalid->nik)->toBeNull();
    expect($pelangganInvalid->toArray()['nik'])->toBeNull();

    // Verifikasi tampilan detail pelanggan Livewire tetap render 200 OK
    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $pelangganInvalid])
        ->assertOk()
        ->assertSee('—');
});
