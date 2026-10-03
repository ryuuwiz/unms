<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusWebhookLog;
use App\Events\InvoicePaidEvent;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const VA_IPAYMU = '1179000899';

beforeEach(function () {
    Event::fake([InvoicePaidEvent::class]);

    PengaturanGateway::create([
        'provider' => 'ipaymu',
        'gateway' => 'ipaymu',
        'nama' => 'iPaymu',
        'credentials' => ['va' => VA_IPAYMU, 'api_key' => 'SANDBOX-KEY'],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => true,
    ]);

    $this->invoice = Invoice::factory()->create([
        'jumlah' => 300000,
        'jumlah_setelah_promo' => 300000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
    $this->trx = app(PaymentGatewayManager::class)->buatPaymentLink($this->invoice);
});

/**
 * Callback form-urlencoded, ditandatangani persis seperti contoh PHP di dokumentasi iPaymu.
 *
 * @param  array<string, string>  $override
 */
function kirimCallbackIpaymu(array $override = [], ?string $signature = null): TestResponse
{
    $payload = array_merge([
        'trx_id' => '184854',
        'sid' => 'f5aaa61d-7260',
        'reference_id' => test()->trx->external_id,
        'status' => 'berhasil',
        'status_code' => '1',
        'sub_total' => '300000',
        'total' => '302100',
        'amount' => '302100',
        'fee' => '2100',
        'paid_off' => '300000',
        'paid_at' => '2026-10-03 10:10:51',
        'is_escrow' => '0',
        'via' => 'qris',
        'channel' => 'mpm',
        'payment_no' => '',
        'url' => 'https://contoh.test/webhook/payment/ipaymu',
    ], $override);

    $data = $payload;
    foreach ($data as $key => &$val) {
        if ($key === 'is_escrow') {
            $val = ($val === '1' || $val === 'true');
        } elseif (in_array($key, ['trx_id', 'status_code', 'transaction_status_code', 'paid_off'])) {
            $val = intval($val);
        } else {
            $val = strval($val);
        }
    }
    unset($val);
    $data['additional_info'] = [];
    ksort($data);

    return test()->call('POST', '/webhook/payment/ipaymu', $payload, [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'HTTP_X_SIGNATURE' => $signature ?? hash_hmac('sha256', json_encode($data), VA_IPAYMU),
    ], http_build_query($payload));
}

function fakeCekTransaksiIpaymu(int $status, ?string $referenceId = null): void
{
    Http::fake(['*/api/v2/transaction' => Http::response([
        'Status' => 200,
        'Data' => ['TransactionId' => 184854, 'ReferenceId' => $referenceId, 'Amount' => 300000, 'Status' => $status],
    ])]);
}

test('link hosted iPaymu tidak menambah fee lokal: iPaymu yang membebankan fee ke pembeli', function () {
    expect($this->trx->gateway)->toBe('ipaymu')
        ->and((float) $this->trx->total_tagihan)->toBe(300000.0)
        ->and((float) $this->trx->fee_gateway)->toBe(0.0)
        ->and($this->invoice->refresh()->payment_gateway_url)->toContain('ipaymu.com/payment/');
});

test('callback bersignature valid dan terkonfirmasi API melunasi invoice dengan sub_total', function () {
    fakeCekTransaksiIpaymu(1, $this->trx->external_id);

    kirimCallbackIpaymu()->assertOk();

    expect($this->invoice->refresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($this->trx->refresh()->provider_reference_id)->toBe('184854')
        // paid_at iPaymu adalah WIB (10:10:51) -> 03:10:51 UTC di database.
        ->and($this->invoice->pembayarans()->sole()->dibayar_pada->utc()->format('H:i:s'))->toBe('03:10:51');
    Http::assertSent(fn ($request) => $request['transactionId'] === '184854');
});

test('signature yang diubah ditolak tanpa menyentuh invoice', function () {
    kirimCallbackIpaymu(signature: str_repeat('0', 64))->assertUnauthorized();

    expect($this->invoice->refresh()->status)->toBe(StatusInvoice::MenungguPembayaran);
});

test('callback lunas yang tidak terkonfirmasi API iPaymu tidak melunasi invoice', function () {
    fakeCekTransaksiIpaymu(0);

    kirimCallbackIpaymu()->assertOk();

    expect($this->invoice->refresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and(WebhookLog::latest('id')->first()->status_proses)->toBe(StatusWebhookLog::Gagal);
});

test('callback pending lalu berhasil dengan trx_id sama tidak dianggap duplikat', function () {
    fakeCekTransaksiIpaymu(1);

    kirimCallbackIpaymu(['status' => 'pending', 'status_code' => '0'])->assertOk();
    expect($this->trx->refresh()->provider_reference_id)->toBe('184854');

    kirimCallbackIpaymu()->assertOk()->assertJson(['status' => 'QUEUED']);
    expect($this->invoice->refresh()->status)->toBe(StatusInvoice::Lunas);
});

test('cek status tanpa trx_id mencari transaksi lewat riwayat berdasarkan ReferenceId', function () {
    Http::fake(['*/api/v2/history' => Http::response([
        'Status' => 200,
        'Data' => ['Total_Page' => 1, 'Results' => [
            ['TransactionId' => 9001, 'ReferenceId' => 'lain', 'Status' => 1],
            ['TransactionId' => 9002, 'ReferenceId' => $this->trx->external_id, 'Status' => -2],
            ['TransactionId' => 9003, 'ReferenceId' => $this->trx->external_id, 'Status' => 1, 'Amount' => 300000],
        ]],
    ])]);

    $status = app(PaymentGatewayManager::class)->cekStatusTransaksi($this->trx);

    expect($status['id'])->toBe('9003')
        ->and($status['status'])->toBe('PAID');
});
