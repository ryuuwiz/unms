<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Events\InvoicePaidEvent;
use App\Livewire\Invoice\Show;
use App\Livewire\Pembayaran\Index;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\User;
use App\Services\Billing\BillingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->adminUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->adminUser->assignRole('admin');

    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => $this->profil->id,
        'harga' => 200000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
    $this->router = Router::factory()->create();
    $this->pelanggan = Pelanggan::factory()->create();
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif,
        'tanggal_expired' => Carbon::today()->addDays(5),
    ]);

    $this->invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'jumlah' => 200000,
        'jumlah_setelah_promo' => 200000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
});

test('user with pembayaran.lihat can view payment history', function () {
    Livewire::actingAs($this->adminUser)->test(Index::class)->assertOk();
});

test('admin can record manual payment and extend active service accumulatively (PRD 4.2 Condition 1)', function () {
    // Current expired is today + 5 days
    $oldExpired = Carbon::today()->addDays(5);
    $this->layanan->update(['tanggal_expired' => $oldExpired]);

    Livewire::actingAs($this->adminUser)
        ->test(Show::class, ['invoice' => $this->invoice])
        ->set('metode', 'manual_admin')
        ->set('jumlah_dibayar', 200000)
        ->set('referensi_transaksi', 'KASIR-001')
        ->set('dibayar_pada', now()->format('Y-m-d\TH:i'))
        ->call('prosesBayar')
        ->assertHasNoErrors();

    // Invoice status becomes Lunas
    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($this->invoice->fresh()->metode_pembayaran)->toBe(MetodePembayaran::ManualAdmin);

    // Payment record created
    $pembayaran = Pembayaran::where('invoice_id', $this->invoice->id)->first();
    expect($pembayaran)->not->toBeNull()
        ->and((float) $pembayaran->jumlah_dibayar)->toBe(200000.0)
        ->and($pembayaran->referensi_transaksi)->toBe('KASIR-001');

    // Service expiry extended from the old expiry by 1 month, snapped to Hari Jatuh Tempo (10)
    $expectedExpired = $oldExpired->copy()->addMonthNoOverflow()->day(10)->toDateString();
    expect($this->layanan->fresh()->tanggal_expired->toDateString())->toBe($expectedExpired)
        ->and($this->layanan->fresh()->status)->toBe(StatusLayanan::Aktif);
});

test('admin recording payment on suspended service with expiry already in the past extends from the old expiry, never from the payment date (Siklus Tagihan)', function () {
    // Service is suspended AND its tanggal_expired is already in the past: the cycle paid is the
    // one that lapsed, so the new expiry is old expiry + 1 month (snapped), not payment date + 1 month.
    $oldExpired = Carbon::today()->subDays(3);
    $this->layanan->update([
        'tanggal_expired' => $oldExpired,
        'status' => StatusLayanan::Suspend,
    ]);

    // InvoicePaidEvent kini juga terpancar untuk pembayaran manual (lihat BillingService::prosesPembayaranManual),
    // yang memicu TriggerMikrotikAktivasiStubListener secara sinkron di lingkungan test (QUEUE_CONNECTION=sync).
    // Test ini fokus pada perhitungan tanggal expired, bukan hasil provisioning MikroTik.
    Queue::fake();

    $billingService = app(BillingService::class);
    $billingService->prosesPembayaranManual(
        invoice: $this->invoice,
        payload: [
            'metode' => 'transfer',
            'jumlah_dibayar' => 200000,
            'dibayar_pada' => Carbon::today(),
        ],
        actor: $this->adminUser
    );

    // Expiry = old expiry + 1 month, snapped to Hari Jatuh Tempo (10)
    $expectedExpired = $oldExpired->copy()->addMonthNoOverflow()->day(10)->toDateString();
    expect($this->layanan->fresh()->tanggal_expired->toDateString())->toBe($expectedExpired)
        ->and($this->layanan->fresh()->status)->toBe(StatusLayanan::Aktif);
});

test('admin recording payment on suspended service with expiry still in the future extends accumulatively (unified isFuture rule)', function () {
    // Layanan Suspend TAPI tanggal_expired masih di masa depan. Aturan lama BillingService
    // (status === Aktif) akan mereset tanggal ini; aturan baru yang disatukan lewat
    // PerpanjangMasaAktifAction (isFuture()) harus tetap akumulatif -- sisa masa aktif yang
    // belum terpakai tidak boleh hangus hanya karena layanan sedang terisolir.
    $futureExpired = Carbon::today()->addDays(5);
    $this->layanan->update([
        'tanggal_expired' => $futureExpired,
        'status' => StatusLayanan::Suspend,
    ]);

    Queue::fake();

    $billingService = app(BillingService::class);
    $billingService->prosesPembayaranManual(
        invoice: $this->invoice,
        payload: [
            'metode' => 'transfer',
            'jumlah_dibayar' => 200000,
            'dibayar_pada' => Carbon::today(),
        ],
        actor: $this->adminUser
    );

    // Akumulatif dari expired lama + 1 bulan (disesuaikan ke Hari Jatuh Tempo), BUKAN reset dari tanggal bayar
    $expectedExpired = $futureExpired->copy()->addMonthNoOverflow()->day(10)->toDateString();
    expect($this->layanan->fresh()->tanggal_expired->toDateString())->toBe($expectedExpired)
        ->and($this->layanan->fresh()->status)->toBe(StatusLayanan::Aktif);
});

test('idempotency guard prevents duplicate payment on already paid invoice', function () {
    $this->invoice->update(['status' => StatusInvoice::Lunas]);

    $billingService = app(BillingService::class);

    expect(fn () => $billingService->prosesPembayaranManual(
        invoice: $this->invoice,
        payload: [
            'metode' => 'manual_admin',
            'jumlah_dibayar' => 200000,
        ],
        actor: $this->adminUser
    ))->toThrow(Exception::class);
});

test('pembayaran dapat di-soft-delete tanpa kehilangan baris dari database', function () {
    Queue::fake();

    $billingService = app(BillingService::class);
    $pembayaran = $billingService->prosesPembayaranManual(
        invoice: $this->invoice,
        payload: ['metode' => 'manual_admin', 'jumlah_dibayar' => 200000],
        actor: $this->adminUser
    );

    $pembayaran->delete();

    expect(Pembayaran::find($pembayaran->id))->toBeNull()
        ->and(Pembayaran::withTrashed()->find($pembayaran->id))->not->toBeNull()
        ->and(Pembayaran::withTrashed()->find($pembayaran->id)->deleted_at)->not->toBeNull();
});

test('force delete invoice yang sudah memiliki pembayaran ditolak oleh database (restrictOnDelete)', function () {
    Queue::fake();

    $billingService = app(BillingService::class);
    $billingService->prosesPembayaranManual(
        invoice: $this->invoice,
        payload: ['metode' => 'manual_admin', 'jumlah_dibayar' => 200000],
        actor: $this->adminUser
    );

    // Soft delete invoice tetap aman (tidak menyentuh FK), tapi force delete yang benar-benar
    // menghapus barisnya wajib gagal karena catatan keuangan tidak boleh ikut hilang.
    expect(fn () => $this->invoice->forceDelete())->toThrow(QueryException::class);

    expect(Pembayaran::where('invoice_id', $this->invoice->id)->count())->toBe(1);
});

test('pembayaran manual dengan nominal kurang bayar ditolak dan tidak melunasi invoice', function () {
    $billingService = app(BillingService::class);

    expect(fn () => $billingService->prosesPembayaranManual(
        invoice: $this->invoice,
        payload: [
            'metode' => 'manual_admin',
            'jumlah_dibayar' => 100000, // Tagihan sebenarnya 200000
        ],
        actor: $this->adminUser
    ))->toThrow(Exception::class);

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and(Pembayaran::where('invoice_id', $this->invoice->id)->count())->toBe(0);
});

test('pembayaran manual dengan nominal lebih bayar ditolak dan tidak melunasi invoice', function () {
    $billingService = app(BillingService::class);

    expect(fn () => $billingService->prosesPembayaranManual(
        invoice: $this->invoice,
        payload: [
            'metode' => 'manual_admin',
            'jumlah_dibayar' => 250000, // Tagihan sebenarnya 200000
        ],
        actor: $this->adminUser
    ))->toThrow(Exception::class);

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and(Pembayaran::where('invoice_id', $this->invoice->id)->count())->toBe(0);
});

test('pembayaran manual memancarkan InvoicePaidEvent agar notifikasi WA konfirmasi pembayaran terkirim', function () {
    Event::fake([InvoicePaidEvent::class]);

    $billingService = app(BillingService::class);
    $pembayaran = $billingService->prosesPembayaranManual(
        invoice: $this->invoice,
        payload: [
            'metode' => 'manual_admin',
            'jumlah_dibayar' => 200000,
            'referensi_transaksi' => 'KASIR-EVENT-001',
        ],
        actor: $this->adminUser
    );

    Event::assertDispatched(InvoicePaidEvent::class, function (InvoicePaidEvent $event) use ($pembayaran) {
        return $event->invoice->id === $this->invoice->id
            && $event->pembayaran->id === $pembayaran->id;
    });
});
