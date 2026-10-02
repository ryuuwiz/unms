<?php

use App\Enums\GatewayChannel;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Carbon::setTestNow('2026-10-02 08:00:00');

    $this->koneksi = new PengaturanGateway([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Utama',
        'credentials' => ['secret_key' => 'xnd_production_rahasia', 'callback_token' => 'token'],
        'sandbox_mode' => false,
    ]);
});

function invoiceXenditPaid(string $id, string $externalId, array $atribut = []): array
{
    return array_merge([
        'id' => $id,
        'external_id' => $externalId,
        'status' => 'PAID',
        'amount' => 150000,
        'paid_amount' => 150000,
        'paid_at' => '2026-10-01T03:15:00.000Z',
        'payment_method' => 'BANK_TRANSFER',
        'payment_channel' => 'BNI',
        'payment_id' => 'pay_'.$id,
        'currency' => 'IDR',
    ], $atribut);
}

test('daftar pembayaran PAID Xendit dipetakan dengan waktu bayar, nominal, dan channel asli', function () {
    Http::fake(['api.xendit.co/v2/invoices*' => Http::response([invoiceXenditPaid('inv_1', 'BFINV-1-1700000000')])]);

    $daftar = (new XenditDriver)->daftarPembayaranLunas($this->koneksi, now()->subDay());

    expect($daftar)->toHaveCount(1)
        ->and($daftar[0]->externalId)->toBe('BFINV-1-1700000000')
        ->and($daftar[0]->status)->toBe('PAID')
        ->and($daftar[0]->paidAmount)->toBe(150000.0)
        ->and($daftar[0]->paidAt)->toBe('2026-10-01T03:15:00.000Z')
        ->and($daftar[0]->channel)->toBe(GatewayChannel::VirtualAccount)
        ->and($daftar[0]->channelDetail)->toBe('bni')
        ->and($daftar[0]->eventId)->toBe('inv_1');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'statuses=PAID&statuses=SETTLED')
        && str_contains($request->url(), 'paid_after=')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('xnd_production_rahasia:')));
});

test('daftar pembayaran mengikuti halaman berikutnya bila satu jendela penuh dan berhenti saat tidak ada data baru', function () {
    $halamanPenuh = collect(range(1, 100))->map(fn (int $i) => invoiceXenditPaid("inv_{$i}", "EXT-{$i}"))->all();
    $halamanKedua = [invoiceXenditPaid('inv_101', 'EXT-101')];

    Http::fake(function (Request $request) use ($halamanPenuh, $halamanKedua) {
        if (str_contains($request->url(), 'last_invoice=inv_100')) {
            return Http::response($halamanKedua);
        }

        // Jendela hari lain kosong; hanya jendela 2026-10-01 berisi pembayaran.
        return str_contains($request->url(), 'paid_after=2026-10-01')
            ? Http::response($halamanPenuh)
            : Http::response([]);
    });

    $daftar = (new XenditDriver)->daftarPembayaranLunas($this->koneksi, Carbon::parse('2026-10-01'));

    expect($daftar)->toHaveCount(101)
        ->and(collect($daftar)->pluck('externalId')->unique())->toHaveCount(101);
});

test('kursor yang diabaikan Xendit tidak membuat paginasi berputar tanpa akhir', function () {
    $halamanPenuh = collect(range(1, 100))->map(fn (int $i) => invoiceXenditPaid("inv_{$i}", "EXT-{$i}"))->all();

    Http::fake(fn (Request $request) => str_contains($request->url(), 'paid_after=2026-10-01')
        ? Http::response($halamanPenuh)
        : Http::response([]));

    $daftar = (new XenditDriver)->daftarPembayaranLunas($this->koneksi, Carbon::parse('2026-10-01'));

    expect($daftar)->toHaveCount(100);
});

test('gagal mengambil daftar dari Xendit melempar exception, bukan daftar kosong', function () {
    Http::fake(['api.xendit.co/v2/invoices*' => Http::response(['error_code' => 'INVALID_API_KEY'], 401)]);

    (new XenditDriver)->daftarPembayaranLunas($this->koneksi, now()->subDay());
})->throws(RuntimeException::class);

test('mematikan invoice Xendit memanggil endpoint expire dengan kredensial koneksi', function () {
    Http::fake(['api.xendit.co/invoices/*' => Http::response(['status' => 'EXPIRED'])]);
    $transaksi = new TransaksiPaymentGateway(['xendit_reference_id' => 'inv_lama']);

    (new XenditDriver)->kedaluwarsakanInvoice($transaksi, $this->koneksi);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://api.xendit.co/invoices/inv_lama/expire!');
});
