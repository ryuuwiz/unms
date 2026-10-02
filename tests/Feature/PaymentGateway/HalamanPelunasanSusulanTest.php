<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusPemindaian;
use App\Enums\StatusTransaksiGateway;
use App\Enums\UserStatus;
use App\Events\InvoicePaidEvent;
use App\Jobs\PaymentGateway\PindaiPelunasanSusulanJob;
use App\Livewire\Pembayaran\PelunasanSusulan\Index;
use App\Models\Invoice;
use App\Models\KasusPelunasanSusulan;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\User;
use App\Notifications\PelunasanSusulanNotification;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\PaymentGateway\PemindaianPelunasanSusulan;
use Carbon\CarbonInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-02 08:00:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::fake([InvoicePaidEvent::class]);

    $this->koneksi = PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Produksi',
        'credentials' => ['secret_key' => 'xnd_production_a', 'callback_token' => 'token-a'],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => false,
    ]);

    $paket = PaketLayanan::factory()->create(['harga' => 150000, 'masa_aktif_nilai' => 1, 'masa_aktif_satuan' => 'bulan']);
    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => Pelanggan::factory()->create()->id,
        'paket_layanan_id' => $paket->id,
        'tanggal_expired' => now()->subDays(10)->toDateString(),
        'status' => StatusLayanan::Suspend,
    ]);
    $this->invoice = Invoice::factory()->create([
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 150000,
        'jumlah_setelah_promo' => 150000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
    $this->transaksi = TransaksiPaymentGateway::create([
        'invoice_id' => $this->invoice->id,
        'pengaturan_gateway_id' => $this->koneksi->id,
        'gateway' => 'xendit',
        'external_id' => $this->invoice->no_invoice.'-1700000000',
        'xendit_reference_id' => 'inv_lama',
        'channel' => GatewayChannel::Invoice,
        'total_tagihan' => 150000,
        'fee_gateway' => 0,
        'status' => StatusTransaksiGateway::Expired,
    ]);

    $this->pembayaranXendit = [];
    $manager = app(PaymentGatewayManager::class);
    $test = $this;
    $manager->registerDriver('xendit', new class($test) extends XenditDriver
    {
        public function __construct(private $test) {}

        public function daftarPembayaranLunas(PengaturanGateway $setting, CarbonInterface $sejak, ?CarbonInterface $sampai = null): array
        {
            $this->test->sejakDiminta = $sejak;

            return array_map(fn (array $item) => $this->petakanPayload($item), $this->test->pembayaranXendit);
        }
    });
    $this->app->instance(PaymentGatewayManager::class, $manager);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');
    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');
});

function pembayaranLunasXendit(TransaksiPaymentGateway $transaksi, float $nominal = 150000): array
{
    return [
        'id' => 'inv_paid_1',
        'external_id' => $transaksi->external_id,
        'status' => 'PAID',
        'amount' => $nominal,
        'paid_amount' => $nominal,
        'paid_at' => '2026-02-10T03:15:00.000Z',
        'payment_method' => 'BANK_TRANSFER',
        'payment_channel' => 'BNI',
        'currency' => 'IDR',
    ];
}

test('menu dan halaman Pelunasan Susulan tersedia untuk izin pembayaran.lihat', function () {
    $this->actingAs($this->admin)
        ->get(route('pembayaran.pelunasan-susulan.index'))
        ->assertOk()
        ->assertSee('Pelunasan Susulan');

    $tanpaIzin = User::factory()->create(['status' => UserStatus::Active]);
    $this->actingAs($tanpaIzin)->get(route('pembayaran.pelunasan-susulan.index'))->assertForbidden();
});

test('pratinjau dan lunasi men-dispatch job dengan mode dan tanggal awal WIB', function () {
    Queue::fake();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('dari', '2026-01-01')
        ->call('pratinjau')
        ->assertSee('Sedang memeriksa');

    Queue::assertPushed(PindaiPelunasanSusulanJob::class, fn (PindaiPelunasanSusulanJob $job) => $job->dryRun
        && Carbon::parse($job->sejak)->equalTo(Carbon::parse('2026-01-01 00:00:00', 'Asia/Jakarta')));

    Cache::lock(PemindaianPelunasanSusulan::KUNCI)->forceRelease();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('dari', '2026-01-01')
        ->call('lunasiSekarang');

    Queue::assertPushed(PindaiPelunasanSusulanJob::class, fn (PindaiPelunasanSusulanJob $job) => ! $job->dryRun);
});

test('lunasi ditolak tanpa izin payment_gateway.ubah, pratinjau tetap boleh', function () {
    Queue::fake();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertDontSeeHtml('wire:click="lunasiSekarang"')
        ->call('lunasiSekarang')
        ->assertForbidden();

    Queue::assertNothingPushed();

    Livewire::actingAs($this->admin)->test(Index::class)->call('pratinjau');
    Queue::assertPushed(PindaiPelunasanSusulanJob::class);
});

test('tombol ditolak dengan pesan jelas saat pemindaian lain atau jadwal harian memegang kunci', function () {
    Queue::fake();
    Cache::lock(PemindaianPelunasanSusulan::KUNCI, 3600)->get();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('pratinjau')
        ->assertDispatched('toast-show', fn (string $event, array $params) => str_contains($params['slots']['text'], 'sedang berjalan'));

    Queue::assertNothingPushed();
    $this->artisan('pembayaran:cek-lunas-xendit')->assertFailed()->expectsOutputToContain('sedang berjalan');
});

test('job pratinjau menyimpan hasil, melepas kunci, dan tidak mengubah data; halaman menampilkan hasilnya', function () {
    $this->pembayaranXendit = [pembayaranLunasXendit($this->transaksi)];

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('dari', '2026-01-01')
        ->call('pratinjau')
        ->assertSee('Selesai')
        ->assertSee($this->invoice->no_invoice)
        ->assertSee('AKAN DILUNASI');

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and(Cache::lock(PemindaianPelunasanSusulan::KUNCI, 10)->get())->toBeTrue();
});

test('job lunasi melunasi, menyimpan kasus, dan mengabari Admin seperti command', function () {
    Notification::fake();
    $kurang = Invoice::factory()->create([
        'pelanggan_id' => $this->invoice->pelanggan_id,
        'layanan_pelanggan_id' => $this->invoice->layanan_pelanggan_id,
        'jumlah' => 150000,
        'jumlah_setelah_promo' => 150000,
        'periode_tagihan' => '2026-03',
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
    $trxKurang = $this->transaksi->replicate()->fill(['invoice_id' => $kurang->id, 'external_id' => $kurang->no_invoice.'-1700000000', 'xendit_reference_id' => 'inv_kurang', 'provider_reference_id' => 'inv_kurang']);
    $trxKurang->save();
    $this->pembayaranXendit = [pembayaranLunasXendit($this->transaksi), array_merge(pembayaranLunasXendit($trxKurang, 99000), ['id' => 'inv_paid_2'])];

    Queue::fake();
    app(PemindaianPelunasanSusulan::class)->mulai(Carbon::parse('2026-01-01', 'Asia/Jakarta'), false, $this->superAdmin);
    Queue::pushed(PindaiPelunasanSusulanJob::class)->first()->handle(app(PemindaianPelunasanSusulan::class));

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and(KasusPelunasanSusulan::count())->toBe(1)
        ->and($this->sejakDiminta->equalTo(Carbon::parse('2025-12-31 17:00:00', 'UTC')))->toBeTrue();
    Notification::assertSentTo($this->admin, PelunasanSusulanNotification::class);
    expect(app(PemindaianPelunasanSusulan::class)->terakhir()['status'])->toBe(StatusPemindaian::Selesai)
        ->and(Cache::lock(PemindaianPelunasanSusulan::KUNCI, 10)->get())->toBeTrue();
});

test('job yang gagal mencatat galat dan melepas kunci', function () {
    Queue::fake();
    $pemindaian = app(PemindaianPelunasanSusulan::class);
    $id = $pemindaian->mulai(Carbon::parse('2026-01-01'), true, $this->superAdmin);
    $job = Queue::pushed(PindaiPelunasanSusulanJob::class)->first();

    $job->failed(new RuntimeException('Xendit tidak tersedia'));

    expect($pemindaian->terakhir())->toMatchArray(['id' => $id, 'status' => StatusPemindaian::Gagal, 'galat' => 'Xendit tidak tersedia'])
        ->and(Cache::lock(PemindaianPelunasanSusulan::KUNCI, 10)->get())->toBeTrue();
});

test('kasus terbuka tampil, yang sudah ditangani tidak', function () {
    $terbuka = KasusPelunasanSusulan::factory()->create(['invoice_id' => $this->invoice->id, 'alasan' => 'Pembayaran ganda: periode sudah Lunas.']);
    KasusPelunasanSusulan::factory()->ditangani()->create(['alasan' => 'Nominal tidak sama dengan tagihan.']);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee($this->invoice->no_invoice)
        ->assertSee($terbuka->alasan)
        ->assertDontSee('Nominal tidak sama dengan tagihan.');
});

test('menandai Sudah Ditangani menyimpan penanda, waktu, catatan, dan activity log', function () {
    $kasus = KasusPelunasanSusulan::factory()->create(['invoice_id' => $this->invoice->id]);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('bukaTandaiDitangani', $kasus->id)
        ->set('catatanPenanganan', 'Sudah direfund di Xendit.')
        ->call('tandaiDitangani')
        ->assertHasNoErrors()
        ->assertDontSee($kasus->alasan);

    $kasus->refresh();
    expect($kasus->ditangani_oleh)->toBe($this->superAdmin->id)
        ->and($kasus->ditangani_pada)->not->toBeNull()
        ->and($kasus->catatan_penanganan)->toBe('Sudah direfund di Xendit.')
        ->and(Activity::inLog('pelunasan_susulan')->where('subject_type', $kasus->getMorphClass())->where('subject_id', $kasus->id)->where('causer_id', $this->superAdmin->id)->exists())->toBeTrue();
});

test('tanpa izin payment_gateway.ubah, menandai kasus ditolak', function () {
    $kasus = KasusPelunasanSusulan::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertDontSeeHtml('bukaTandaiDitangani')
        ->set('kasusDitandai', $kasus->id)
        ->call('tandaiDitangani')
        ->assertForbidden();

    expect($kasus->fresh()->sudahDitangani())->toBeFalse();
});

test('URL notifikasi Pelunasan Susulan mengarah ke halaman Pelunasan Susulan', function () {
    expect((new PelunasanSusulanNotification(1, 2))->toArray($this->admin)['url'])
        ->toBe(route('pembayaran.pelunasan-susulan.index'));
});

test('job yang diambil ulang karena melewati retry_after tidak melepas kunci pemindaian yang masih berjalan', function () {
    Queue::fake();
    app(PemindaianPelunasanSusulan::class)->mulai(Carbon::parse('2026-01-01'), true, $this->superAdmin);
    $job = Queue::pushed(PindaiPelunasanSusulanJob::class)->first();

    $job->failed(new MaxAttemptsExceededException('attempted too many times'));

    expect(Cache::lock(PemindaianPelunasanSusulan::KUNCI, 10)->get())->toBeFalse()
        ->and(app(PemindaianPelunasanSusulan::class)->terakhir()['status'])->toBe(StatusPemindaian::Berjalan);
});

test('job yang kuncinya sudah kedaluwarsa tidak memindai dan ditandai gagal', function () {
    Queue::fake();
    $pemindaian = app(PemindaianPelunasanSusulan::class);
    $pemindaian->mulai(Carbon::parse('2026-01-01'), false, $this->superAdmin);
    $job = Queue::pushed(PindaiPelunasanSusulanJob::class)->first();
    Cache::lock(PemindaianPelunasanSusulan::KUNCI)->forceRelease();
    $this->pembayaranXendit = [pembayaranLunasXendit($this->transaksi)];

    $job->handle($pemindaian);

    expect($pemindaian->terakhir()['status'])->toBe(StatusPemindaian::Gagal)
        ->and($this->invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran);
});

test('pemindaian yang tidak diambil worker dapat dibatalkan dan melepas kunci', function () {
    Queue::fake();

    $halaman = Livewire::actingAs($this->admin)->test(Index::class)->call('pratinjau');
    $job = Queue::pushed(PindaiPelunasanSusulanJob::class)->first();

    $halaman->call('batalkanPemindaian')
        ->assertDispatched('toast-show', fn (string $event, array $params) => str_contains($params['slots']['text'], 'tidak dapat dibatalkan'));

    Carbon::setTestNow(now()->addMinutes(3));

    $halaman->call('batalkanPemindaian')
        ->assertSee('Dibatalkan');

    expect(Cache::lock(PemindaianPelunasanSusulan::KUNCI, 10)->get())->toBeTrue();

    $job->handle(app(PemindaianPelunasanSusulan::class));
    expect(app(PemindaianPelunasanSusulan::class)->terakhir()['status'])->toBe(StatusPemindaian::Dibatalkan);
});

test('tanggal hari ini menurut WIB diterima walau di UTC masih kemarin', function () {
    Queue::fake();
    Carbon::setTestNow('2026-10-01 20:00:00');

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('dari', '2026-10-02')
        ->call('pratinjau')
        ->assertHasNoErrors();

    Queue::assertPushed(PindaiPelunasanSusulanJob::class);
});
