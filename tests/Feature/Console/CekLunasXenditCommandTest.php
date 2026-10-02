<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Enums\UserStatus;
use App\Events\InvoicePaidEvent;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\User;
use App\Notifications\PelunasanSusulanNotification;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Carbon\CarbonInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
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
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => Pelanggan::factory()->create()->id,
        'paket_layanan_id' => $paket->id,
        'tanggal_expired' => now()->subDays(10)->toDateString(),
        'status' => StatusLayanan::Suspend,
    ]);

    // Pembayaran PAID per koneksi yang dikembalikan driver palsu: [pengaturan_gateway_id => list<array>].
    $this->pembayaranXendit = [];
    $this->invoiceDikedaluwarsakan = [];
    $this->expireGagal = false;
    $this->manager = app(PaymentGatewayManager::class);
    $test = $this;
    $this->manager->registerDriver('xendit', new class($test) extends XenditDriver
    {
        public function __construct(private $test) {}

        public function daftarPembayaranLunas(PengaturanGateway $setting, CarbonInterface $sejak, ?CarbonInterface $sampai = null): array
        {
            $this->test->sejakDiminta = $sejak;

            if (($this->test->pembayaranXendit[$setting->id] ?? null) === 'error') {
                throw new RuntimeException('HTTP 401 INVALID_API_KEY');
            }

            return array_map(fn (array $item) => $this->petakanPayload($item), $this->test->pembayaranXendit[$setting->id] ?? []);
        }

        public function kedaluwarsakanInvoice(TransaksiPaymentGateway $transaksi, PengaturanGateway $setting): void
        {
            if ($this->test->expireGagal) {
                throw new RuntimeException('HTTP 503 Xendit tidak tersedia');
            }

            $this->test->invoiceDikedaluwarsakan[] = $transaksi->xendit_reference_id;
        }
    });
    $this->app->instance(PaymentGatewayManager::class, $this->manager);
});

function invoiceSusulan(LayananPelanggan $layanan, StatusInvoice $status = StatusInvoice::MenungguPembayaran, array $atribut = []): Invoice
{
    return Invoice::factory()->create(array_merge([
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 150000,
        'jumlah_setelah_promo' => 150000,
        'tanggal_terbit' => now()->subDays(20)->toDateString(),
        'tanggal_jatuh_tempo' => now()->subDays(13)->toDateString(),
        'status' => $status,
    ], $atribut));
}

function transaksiSusulan(Invoice $invoice, PengaturanGateway $koneksi, StatusTransaksiGateway $status = StatusTransaksiGateway::Expired): TransaksiPaymentGateway
{
    return TransaksiPaymentGateway::create([
        'invoice_id' => $invoice->id,
        'pengaturan_gateway_id' => $koneksi->id,
        'gateway' => 'xendit',
        'external_id' => $invoice->no_invoice.'-1700000000',
        'xendit_reference_id' => 'inv_'.$invoice->id,
        'channel' => GatewayChannel::Invoice,
        'total_tagihan' => $invoice->jumlah_setelah_promo,
        'fee_gateway' => 0,
        'status' => $status,
    ]);
}

function bayarXendit(TransaksiPaymentGateway|string $target, float $nominal = 150000, string $paidAt = '2026-09-25T03:15:00.000Z'): array
{
    $externalId = $target instanceof TransaksiPaymentGateway ? $target->external_id : $target;

    return [
        'id' => 'inv_paid_'.md5($externalId),
        'external_id' => $externalId,
        'status' => 'PAID',
        'amount' => $nominal,
        'paid_amount' => $nominal,
        'paid_at' => $paidAt,
        'payment_method' => 'BANK_TRANSFER',
        'payment_channel' => 'BNI',
        'payment_id' => 'pay_'.md5($externalId),
        'currency' => 'IDR',
    ];
}

test('invoice Menunggu Pembayaran dan Kadaluarsa yang PAID di Xendit dilunasi dengan waktu bayar asli', function (StatusInvoice $status) {
    $invoice = invoiceSusulan($this->layanan, $status);
    $transaksi = transaksiSusulan($invoice, $this->koneksi);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($transaksi)];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('DILUNASI');

    $invoice->refresh();
    expect($invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($invoice->tanggal_lunas->toDateString())->toBe('2026-09-25')
        ->and($transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Paid)
        ->and(Pembayaran::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and($this->layanan->fresh()->tanggal_expired->greaterThan(now()))->toBeTrue();
    Event::assertDispatched(InvoicePaidEvent::class);
})->with([StatusInvoice::MenungguPembayaran, StatusInvoice::Kadaluarsa]);

test('pembayaran tanpa baris transaksi lokal dicocokkan lewat nomor invoice di external_id', function () {
    $invoice = invoiceSusulan($this->layanan);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($invoice->no_invoice.'-1699999999')];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas);
});

test('nominal yang tidak sama persis, external_id tak dikenal, dan pembayaran ganda dilaporkan tanpa mengubah data', function () {
    $nominalBeda = invoiceSusulan($this->layanan, atribut: ['periode_tagihan' => '2026-07']);
    $trxBeda = transaksiSusulan($nominalBeda, $this->koneksi);

    $sudahLunas = invoiceSusulan($this->layanan, StatusInvoice::Lunas, ['periode_tagihan' => '2026-08']);
    $trxLama = transaksiSusulan($sudahLunas, $this->koneksi);

    $this->pembayaranXendit[$this->koneksi->id] = [
        bayarXendit($trxBeda, 100000),
        bayarXendit('BFINV-TIDAKADA-1700000000'),
        bayarXendit($trxLama),
    ];

    $this->artisan('pembayaran:cek-lunas-xendit')
        ->assertSuccessful()
        ->expectsOutputToContain('Nominal tidak sama')
        ->expectsOutputToContain('tidak dikenal')
        ->expectsOutputToContain('Pembayaran ganda');

    expect($nominalBeda->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and(Pembayaran::count())->toBe(0);
    Event::assertNotDispatched(InvoicePaidEvent::class);
});

test('--dry-run tidak mengubah data dan menjalankan ulang tidak melunasi dua kali', function () {
    $invoice = invoiceSusulan($this->layanan);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit(transaksiSusulan($invoice, $this->koneksi))];

    $this->artisan('pembayaran:cek-lunas-xendit', ['--dry-run' => true])->assertSuccessful()->expectsOutputToContain('AKAN DILUNASI');
    expect($invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran);

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful();
    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->doesntExpectOutputToContain('Pembayaran ganda');

    expect(Pembayaran::where('invoice_id', $invoice->id)->count())->toBe(1);
    Event::assertDispatchedTimes(InvoicePaidEvent::class, 1);
});

test('koneksi sandbox dilewati, koneksi nonaktif tetap diperiksa, dan satu koneksi gagal tidak menghentikan yang lain', function () {
    $sandbox = PengaturanGateway::create([
        'provider' => 'xendit', 'gateway' => 'xendit', 'nama' => 'Xendit Sandbox',
        'credentials' => ['secret_key' => 'xnd_development_x'], 'is_active' => true, 'sandbox_mode' => true,
    ]);
    $nonaktif = PengaturanGateway::create([
        'provider' => 'xendit', 'gateway' => 'xendit', 'nama' => 'Xendit Lama',
        'credentials' => ['secret_key' => 'xnd_production_lama'], 'is_active' => false, 'sandbox_mode' => false,
    ]);

    $dariSandbox = invoiceSusulan($this->layanan, atribut: ['periode_tagihan' => '2026-07']);
    $dariLama = invoiceSusulan($this->layanan, atribut: ['periode_tagihan' => '2026-08']);
    $this->pembayaranXendit[$sandbox->id] = [bayarXendit(transaksiSusulan($dariSandbox, $sandbox))];
    $this->pembayaranXendit[$nonaktif->id] = [bayarXendit(transaksiSusulan($dariLama, $nonaktif))];
    $this->pembayaranXendit[$this->koneksi->id] = 'error';

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('INVALID_API_KEY');

    expect($dariSandbox->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($dariLama->fresh()->status)->toBe(StatusInvoice::Lunas);
});

/**
 * Invoice September (Rp150.000) yang sudah digabung ke invoice Oktober (Rp300.000, tunggakan Rp150.000).
 *
 * @return array{0: Invoice, 1: TransaksiPaymentGateway, 2: Invoice}
 */
function invoiceDigabungKeOktober(LayananPelanggan $layanan, PengaturanGateway $koneksi, StatusInvoice $statusOktober = StatusInvoice::MenungguPembayaran): array
{
    $oktober = invoiceSusulan($layanan, $statusOktober, [
        'periode_tagihan' => '2026-10',
        'jumlah' => 150000,
        'jumlah_setelah_promo' => 300000,
        'jumlah_tunggakan' => 150000,
    ]);
    $september = invoiceSusulan($layanan, StatusInvoice::Digabung, ['periode_tagihan' => '2026-09', 'digabung_ke_invoice_id' => $oktober->id]);

    return [$september, transaksiSusulan($september, $koneksi), $oktober];
}

test('invoice Digabung yang dibayar dilunasi, dilepas dari penggabung, dan nominal serta link penggabung dikoreksi', function () {
    [$september, $trxSeptember, $oktober] = invoiceDigabungKeOktober($this->layanan, $this->koneksi);
    $trxOktober = transaksiSusulan($oktober, $this->koneksi, StatusTransaksiGateway::Pending);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($trxSeptember)];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('DILUNASI');

    $september->refresh();
    $oktober->refresh();
    expect($september->status)->toBe(StatusInvoice::Lunas)
        ->and($september->digabung_ke_invoice_id)->toBeNull()
        ->and((float) $oktober->jumlah_setelah_promo)->toBe(150000.0)
        ->and((float) $oktober->jumlah_tunggakan)->toBe(0.0)
        ->and($oktober->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($trxOktober->fresh()->status)->toBe(StatusTransaksiGateway::Expired)
        ->and($this->invoiceDikedaluwarsakan)->toBe([$trxOktober->xendit_reference_id]);

    $linkBaru = $oktober->transaksiPaymentGatewayAktif();
    expect($linkBaru->id)->not->toBe($trxOktober->id)
        ->and($linkBaru->status)->toBe(StatusTransaksiGateway::Pending)
        ->and((float) $linkBaru->total_tagihan - (float) $linkBaru->fee_gateway)->toBe(150000.0);
});

test('invoice Digabung yang penggabungnya sudah Lunas dilaporkan sebagai pembayaran ganda', function () {
    [$september, $trxSeptember, $oktober] = invoiceDigabungKeOktober($this->layanan, $this->koneksi, StatusInvoice::Lunas);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($trxSeptember)];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('Pembayaran ganda');

    expect($september->fresh()->status)->toBe(StatusInvoice::Digabung)
        ->and((float) $oktober->fresh()->jumlah_setelah_promo)->toBe(300000.0);
});

test('gagal mematikan link penggabung di Xendit tidak membatalkan pelunasan dan dilaporkan', function () {
    [$september, $trxSeptember, $oktober] = invoiceDigabungKeOktober($this->layanan, $this->koneksi);
    transaksiSusulan($oktober, $this->koneksi, StatusTransaksiGateway::Pending);
    $this->expireGagal = true;
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($trxSeptember)];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('Xendit tidak tersedia');

    $oktober->refresh();
    expect($september->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and((float) $oktober->jumlah_setelah_promo)->toBe(150000.0)
        ->and($oktober->payment_gateway_url)->toBeNull();
});

function invoiceDibatalkan(LayananPelanggan $layanan, array $atribut = []): Invoice
{
    $invoice = invoiceSusulan($layanan, StatusInvoice::Dibatalkan, array_merge(['periode_tagihan' => '2026-08'], $atribut));
    $invoice->delete();

    return $invoice;
}

test('invoice Dibatalkan yang dibayar dipulihkan dan dilunasi bila periodenya belum Lunas', function () {
    $invoice = invoiceDibatalkan($this->layanan);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit(transaksiSusulan($invoice, $this->koneksi))];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('DILUNASI');

    $invoice = Invoice::query()->find($invoice->id);
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($this->layanan->fresh()->tanggal_expired->greaterThan(now()))->toBeTrue();
});

test('invoice Dibatalkan yang periodenya sudah Lunas lewat invoice lain dilaporkan sebagai pembayaran ganda', function () {
    $invoice = invoiceDibatalkan($this->layanan);
    invoiceSusulan($this->layanan, StatusInvoice::Lunas, ['periode_tagihan' => '2026-08']);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit(transaksiSusulan($invoice, $this->koneksi))];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('Pembayaran ganda');

    expect(Invoice::query()->find($invoice->id))->toBeNull();
});

test('invoice Dibatalkan yang dulunya menggabung tunggakan dilaporkan, tidak dipulihkan', function () {
    $invoice = invoiceDibatalkan($this->layanan, ['jumlah_setelah_promo' => 300000, 'jumlah_tunggakan' => 150000]);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit(transaksiSusulan($invoice, $this->koneksi), 300000)];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('menggabung tunggakan');

    expect(Invoice::query()->find($invoice->id))->toBeNull();
});

test('pelunasan susulan dan kasus yang dilaporkan tercatat di audit trail dan dikabarkan ke admin serta super_admin', function () {
    Notification::fake();
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('admin');
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole('super_admin');
    $noc = User::factory()->create(['status' => UserStatus::Active]);
    $noc->assignRole('noc');

    $invoice = invoiceSusulan($this->layanan);
    $this->pembayaranXendit[$this->koneksi->id] = [
        bayarXendit(transaksiSusulan($invoice, $this->koneksi)),
        bayarXendit('BFINV-TIDAKADA-1700000000'),
    ];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful();

    expect(Activity::inLog('pelunasan_susulan')->count())->toBe(2)
        ->and(Activity::inLog('pelunasan_susulan')->where('subject_id', $invoice->id)->first()?->getProperty('aksi'))->toBe('DILUNASI');
    Notification::assertSentTo([$admin, $superAdmin], PelunasanSusulanNotification::class);
    Notification::assertNotSentTo($noc, PelunasanSusulanNotification::class);
});

test('tidak ada notifikasi saat tidak ada yang dilunasi atau dilaporkan, maupun saat dry-run', function () {
    Notification::fake();
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('admin');

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful();

    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit(transaksiSusulan(invoiceSusulan($this->layanan), $this->koneksi))];
    $this->artisan('pembayaran:cek-lunas-xendit', ['--dry-run' => true])->assertSuccessful();

    Notification::assertNothingSent();
    expect(Activity::inLog('pelunasan_susulan')->count())->toBe(0);
});

test('pembayaran:cek-lunas-xendit dijadwalkan harian', function () {
    $jadwal = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'pembayaran:cek-lunas-xendit'));

    expect($jadwal)->not->toBeNull()
        ->and($jadwal->expression)->toBe('15 2 * * *');
});

test('rantai penggabungan diikuti sampai penggabung terbuka: setiap invoice di rantai dikoreksi dan link penggabung terakhir diterbitkan ulang', function () {
    $oktober = invoiceSusulan($this->layanan, atribut: ['periode_tagihan' => '2026-10', 'jumlah_setelah_promo' => 450000, 'jumlah_tunggakan' => 300000]);
    $september = invoiceSusulan($this->layanan, StatusInvoice::Digabung, [
        'periode_tagihan' => '2026-09', 'jumlah_setelah_promo' => 300000, 'jumlah_tunggakan' => 150000, 'digabung_ke_invoice_id' => $oktober->id,
    ]);
    $agustus = invoiceSusulan($this->layanan, StatusInvoice::Digabung, ['periode_tagihan' => '2026-08', 'digabung_ke_invoice_id' => $september->id]);
    $trxOktober = transaksiSusulan($oktober, $this->koneksi, StatusTransaksiGateway::Pending);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit(transaksiSusulan($agustus, $this->koneksi))];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('DILUNASI');

    expect($agustus->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($agustus->fresh()->digabung_ke_invoice_id)->toBeNull()
        ->and((float) $september->fresh()->jumlah_setelah_promo)->toBe(150000.0)
        ->and((float) $september->fresh()->jumlah_tunggakan)->toBe(0.0)
        ->and($september->fresh()->status)->toBe(StatusInvoice::Digabung)
        ->and((float) $oktober->fresh()->jumlah_setelah_promo)->toBe(300000.0)
        ->and((float) $oktober->fresh()->jumlah_tunggakan)->toBe(150000.0)
        ->and($this->invoiceDikedaluwarsakan)->toBe([$trxOktober->xendit_reference_id]);
});

test('kasus yang dilaporkan hanya dicatat dan dikabarkan sekali walau muncul lagi di eksekusi berikutnya', function () {
    Notification::fake();
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('admin');
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit(transaksiSusulan(invoiceSusulan($this->layanan), $this->koneksi), 99000)];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful();
    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful();

    expect(Activity::inLog('pelunasan_susulan')->count())->toBe(1);
    Notification::assertSentToTimes($admin, PelunasanSusulanNotification::class, 1);
});

test('pembayaran tanpa transaksi lokal dari koneksi live non-default dilunasi walau koneksi default sandbox, dan transaksinya tercatat pada koneksi itu', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->koneksi->update(['is_default' => false]);
    PengaturanGateway::create([
        'provider' => 'xendit', 'gateway' => 'xendit', 'nama' => 'Xendit Sandbox Default',
        'credentials' => ['secret_key' => 'xnd_development_x'], 'is_active' => true, 'is_default' => true, 'sandbox_mode' => true,
    ]);
    $invoice = invoiceSusulan($this->layanan);
    $trxLama = transaksiSusulan($invoice, $this->koneksi);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($invoice->no_invoice.'-1699999999')];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful();

    $transaksiBayar = TransaksiPaymentGateway::where('external_id', $invoice->no_invoice.'-1699999999')->first();
    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($transaksiBayar?->status)->toBe(StatusTransaksiGateway::Paid)
        ->and($transaksiBayar?->pengaturan_gateway_id)->toBe($this->koneksi->id)
        ->and($trxLama->fresh()->status)->toBe(StatusTransaksiGateway::Expired);
});

test('pembayaran tanpa transaksi lokal yang sudah termasuk biaya gateway koneksi tetap dilunasi', function () {
    $this->koneksi->update(['bebankan_ke_pelanggan' => true, 'fee_va_nominal' => 4000]);
    $invoice = invoiceSusulan($this->layanan);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($invoice->no_invoice.'-1699999999', 154000)];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas);
});

test('--dari dan tanggal lunas mengikuti hari WIB', function () {
    $invoice = invoiceSusulan($this->layanan);
    // 18:30 UTC = 01:30 WIB keesokan harinya.
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit(transaksiSusulan($invoice, $this->koneksi), paidAt: '2026-09-25T18:30:00.000Z')];

    $this->artisan('pembayaran:cek-lunas-xendit', ['--dari' => '2026-09-01'])->assertSuccessful();

    expect($this->sejakDiminta->toIso8601ZuluString())->toBe('2026-08-31T17:00:00Z')
        ->and($invoice->fresh()->tanggal_lunas->toDateString())->toBe('2026-09-26');
});

test('pembayaran:pulihkan memakai aturan Pelunasan Susulan untuk invoice Digabung', function () {
    [$september, $trxSeptember, $oktober] = invoiceDigabungKeOktober($this->layanan, $this->koneksi);
    $this->manager->registerDriver('xendit', new class extends XenditDriver
    {
        public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
        {
            $nominal = $target instanceof TransaksiPaymentGateway ? (float) $target->total_tagihan : 0.0;

            return ['id' => 'inv_pulih', 'status' => 'PAID', 'amount' => $nominal, 'paid_amount' => $nominal, 'paid_at' => '2026-09-25T03:15:00.000Z'];
        }
    });

    $this->artisan('pembayaran:pulihkan', ['--dari' => now()->subDays(5)->toDateString()])->assertSuccessful();

    expect($september->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($september->fresh()->digabung_ke_invoice_id)->toBeNull()
        ->and((float) $oktober->fresh()->jumlah_setelah_promo)->toBe(150000.0);
});
