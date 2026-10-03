<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Livewire\Portal\Invoice\Index;
use App\Livewire\Portal\Invoice\Show;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\PengaturanGateway;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    PengaturanGateway::create([
        'provider' => 'xendit',
        'nama' => 'Xendit',
        'credentials' => ['secret_key' => 'xnd_development_test', 'callback_token' => 'token-test'],
        'is_default' => true,
    ]);

    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => $this->profil->id,
        'harga' => 300000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
    $this->router = Router::factory()->create();

    $this->pelanggan = Pelanggan::factory()->create(['email' => 'client1@test.com']);
    $this->akun = $this->pelanggan->akunPelanggan;
    $this->akun->update(['password' => Hash::make('password123')]);

    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif,
    ]);

    $this->invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'jumlah' => 300000,
        'jumlah_setelah_promo' => 300000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
});

test('pelanggan dapat melihat daftar tagihannya di portal', function () {
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Index::class)
        ->assertOk()
        ->assertSee($this->invoice->no_invoice);
});

test('pelanggan dapat melihat rincian tagihan miliknya', function () {
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->assertOk()
        ->assertSee($this->invoice->no_invoice);
});

test('pelanggan tidak dapat melihat tagihan milik pelanggan lain (403)', function () {
    $pelangganLain = Pelanggan::factory()->create(['email' => 'other@test.com']);
    $layananLain = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
    ]);
    $invoiceLain = Invoice::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'layanan_pelanggan_id' => $layananLain->id,
        'jumlah' => 100000,
        'jumlah_setelah_promo' => 100000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $invoiceLain])
        ->assertForbidden();
});

test('pelanggan melihat tombol bayar per metode dengan total termasuk biaya admin', function () {
    // VA: 300.000 + (9.000 + 4.000) x 1,11 = 314.430; QRIS: 300.000 + 0,7% x 300.000 + 4.000 x 1,11 = 306.540
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->assertSeeInOrder(['Virtual Account', 'Rp 314.430'])
        ->assertSeeInOrder(['QRIS', 'Rp 306.540']);
});

test('tombol Virtual Account membuka checkout Xendit yang hanya berisi VA dengan biaya VA', function () {
    fakeXenditSession();

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar', 'virtual_account')
        ->assertRedirectContains('https://xen.to/ps-');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.xendit.co/sessions'
        && $request['amount'] === 314430
        && $request['mode'] === 'PAYMENT_LINK'
        && in_array('BCA_VIRTUAL_ACCOUNT', $request['allowed_payment_channels'], true)
        && ! in_array('QRIS', $request['allowed_payment_channels'], true));

    $transaksi = $this->invoice->transaksiPaymentGateways()->sole();
    expect($transaksi->channel)->toBe(GatewayChannel::VirtualAccount)
        ->and((float) $transaksi->fee_gateway)->toBe(14430.0)
        ->and((float) $transaksi->total_tagihan)->toBe(314430.0)
        ->and($transaksi->xendit_reference_id)->toStartWith('ps-');
});

test('tombol QRIS membuka checkout Xendit yang hanya berisi QRIS dengan biaya persentase', function () {
    fakeXenditSession();

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar', 'qris')
        ->assertRedirectContains('https://xen.to/ps-');

    Http::assertSent(fn (Request $request) => $request['amount'] === 306540
        && $request['allowed_payment_channels'] === ['QRIS']);

    $transaksi = $this->invoice->transaksiPaymentGateways()->sole();
    expect($transaksi->channel)->toBe(GatewayChannel::Qris)
        ->and((float) $transaksi->fee_gateway)->toBe(6540.0);
});

test('biaya persentase dibulatkan ke atas walau pecahannya sangat kecil', function () {
    fakeXenditSession();
    $this->invoice->update(['jumlah' => 100143, 'jumlah_setelah_promo' => 100143]);

    // 0,7% x 100.143 + 4.440 = 5.141,001 -> Rp 5.142
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar', 'qris');

    expect((float) $this->invoice->transaksiPaymentGateways()->sole()->fee_gateway)->toBe(5142.0);
});

test('total transaksi sama persis dengan nominal yang dikirim ke gateway walau tagihan setelah promo berpecahan', function () {
    fakeXenditSession();
    $this->invoice->update(['jumlah_setelah_promo' => 299999.6]);

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar', 'virtual_account');

    Http::assertSent(fn (Request $request) => $request['amount'] === 314430);
    expect((float) $this->invoice->transaksiPaymentGateways()->sole()->total_tagihan)->toBe(314430.0);
});

test('metode bayar yang tidak dikenal memberi tahu pelanggan', function () {
    fakeXenditSession();

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar', 'ewallet')
        ->assertDispatched('toast-show');
});

test('pelanggan melihat tombol GoPay dan ShopeePay dengan tarif masing-masing, setelah VA dan QRIS', function () {
    // GoPay: 5% x 300.000 x 1,11 + 4.440 = 21.090; ShopeePay: 3,6% x 300.000 x 1,11 + 4.440 = 16.428
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->assertSeeInOrder(['Virtual Account', 'QRIS', 'GoPay', 'Rp 321.090', 'ShopeePay', 'Rp 316.428']);
});

test('tombol e-wallet membuka checkout Xendit yang hanya berisi e-wallet itu', function (string $metode, string $kanal, int $total) {
    fakeXenditSession();

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar', $metode)
        ->assertRedirectContains('https://xen.to/ps-');

    Http::assertSent(fn (Request $request) => $request['amount'] === $total
        && $request['allowed_payment_channels'] === [$kanal]);
    expect($this->invoice->transaksiPaymentGateways()->sole()->channel->value)->toBe($metode);
})->with([
    'GoPay' => ['gopay', 'GOPAY', 321090],
    'ShopeePay' => ['shopeepay', 'SHOPEEPAY', 316428],
]);

test('menekan metode yang sama lagi memakai ulang checkout aktif, metode lain membuat checkout sendiri', function () {
    fakeXenditSession();
    $halaman = Livewire::actingAs($this->akun, 'pelanggan')->test(Show::class, ['invoice' => $this->invoice]);

    $halaman->call('bayar', 'virtual_account');
    $halaman->call('bayar', 'virtual_account');
    $halaman->call('bayar', 'qris');

    expect(Http::recorded(fn (Request $request) => $request->method() === 'POST'))->toHaveCount(2);
    expect($this->invoice->transaksiPaymentGateways()->pluck('channel')->all())
        ->toEqualCanonicalizing([GatewayChannel::VirtualAccount, GatewayChannel::Qris]);
});

test('checkout yang sudah kedaluwarsa diganti checkout baru', function () {
    fakeXenditSession();
    $halaman = Livewire::actingAs($this->akun, 'pelanggan')->test(Show::class, ['invoice' => $this->invoice]);
    $halaman->call('bayar', 'virtual_account');
    $this->invoice->transaksiPaymentGateways()->update(['expired_at' => Carbon::now()->subMinute()]);

    $halaman->call('bayar', 'virtual_account');

    expect(Http::recorded(fn (Request $request) => $request->method() === 'POST'))->toHaveCount(2);
});

test('metode bayar yang tidak ditawarkan ditolak tanpa memanggil gateway', function () {
    fakeXenditSession();

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar', 'ewallet')
        ->assertNoRedirect();

    Http::assertNothingSent();
});

test('pelanggan dapat memicu cek status pembayaran secara manual pada portal', function () {
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('cekStatusPembayaran')
        ->assertHasNoErrors();
});

test('pelanggan dapat mencetak invoice PDF miliknya sendiri', function () {
    $response = $this->actingAs($this->akun, 'pelanggan')
        ->get(route('portal.invoice.cetak', $this->invoice));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('pelanggan tidak dapat mencetak invoice PDF milik pelanggan lain (403)', function () {
    $pelangganLain = Pelanggan::factory()->create(['email' => 'other_print@test.com']);
    $layananLain = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
    ]);
    $invoiceLain = Invoice::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'layanan_pelanggan_id' => $layananLain->id,
    ]);

    $response = $this->actingAs($this->akun, 'pelanggan')
        ->get(route('portal.invoice.cetak', $invoiceLain));

    $response->assertForbidden();
});

test('tamu tidak dapat mencetak invoice tanpa otentikasi', function () {
    $this->get(route('invoice.cetak', $this->invoice))
        ->assertRedirect(route('login'));

    $this->get(route('portal.invoice.cetak', $this->invoice))
        ->assertRedirect(route('portal.login'));
});

test('tamu dapat membuka rincian tagihan tanpa login lewat tautan bertanda tangan', function () {
    $signedUrl = URL::signedRoute('portal.invoice.show', ['invoice' => $this->invoice->id]);

    $this->get($signedUrl)
        ->assertOk()
        ->assertSee($this->invoice->no_invoice);
});

test('tamu ditolak membuka rincian tagihan tanpa login dan tanpa tanda tangan valid', function () {
    $this->get(route('portal.invoice.show', $this->invoice))
        ->assertForbidden();
});

test('rute lama /bayar tetap berfungsi sebagai redirect ke Halaman Tagihan Mandiri', function () {
    // Halaman estimasi biaya terpisah sudah dihapus (lihat CONTEXT.md "Halaman Tagihan
    // Mandiri") -- rute lama dipertahankan hanya sebagai redirect, bukan dihapus total,
    // untuk tautan /bayar yang mungkin sudah ter-cache di notifikasi lama/riwayat browser.
    $this->get(route('portal.invoice.bayar', $this->invoice))
        ->assertRedirect(route('portal.invoice.show', $this->invoice));
});

test('pelanggan tidak dapat memicu pembayaran untuk invoice yang dibatalkan', function () {
    $this->invoice->update([
        'status' => StatusInvoice::Dibatalkan,
        'keterangan_hapus' => 'Dibatalkan karena koreksi tagihan ganda',
    ]);

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->assertOk()
        ->assertSee('Tagihan Ini Telah Dibatalkan')
        ->assertSee('Dibatalkan karena koreksi tagihan ganda')
        ->assertDontSee('Virtual Account')
        ->call('bayar', 'virtual_account')
        ->assertNoRedirect();

    expect($this->invoice->fresh()->payment_gateway_url)->toBeNull();
});
