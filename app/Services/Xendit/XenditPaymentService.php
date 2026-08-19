<?php

namespace App\Services\Xendit;

use App\Enums\GatewayChannel;
use App\Enums\StatusTransaksiGateway;
use App\Http\Controllers\Webhook\XenditWebhookController;
use App\Models\Invoice;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Xendit\BalanceAndTransaction\BalanceApi;
use Xendit\Configuration;
use Xendit\PaymentRequest\PaymentMethodParameters;
use Xendit\PaymentRequest\PaymentMethodReusability;
use Xendit\PaymentRequest\PaymentMethodType;
use Xendit\PaymentRequest\PaymentRequestApi;
use Xendit\PaymentRequest\PaymentRequestCurrency;
use Xendit\PaymentRequest\PaymentRequestParameters;
use Xendit\PaymentRequest\QRCodeChannelCode;
use Xendit\PaymentRequest\QRCodeChannelProperties;
use Xendit\PaymentRequest\QRCodeParameters;
use Xendit\PaymentRequest\VirtualAccountChannelProperties;
use Xendit\PaymentRequest\VirtualAccountParameters;
use Xendit\XenditSdkException;

class XenditPaymentService
{
    protected PaymentRequestApi $paymentRequestApi;

    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.xendit.secret_key');
        Configuration::setXenditKey($this->apiKey);
        $this->paymentRequestApi = new PaymentRequestApi(
            client: new Client(['timeout' => 30]),
            config: Configuration::getDefaultConfiguration()
        );
    }

    /**
     * Buat tagihan Virtual Account untuk Invoice tertentu.
     */
    public function buatVirtualAccount(Invoice $invoice, string $bankCode): TransaksiPaymentGateway
    {
        $bankCodeUpper = strtoupper(trim($bankCode));
        $pengaturan = PengaturanGateway::getXenditSetting();
        $nominalInvoice = (float) $invoice->jumlah_setelah_promo;
        $fee = $pengaturan->hitungFee('virtual_account', $nominalInvoice);
        $totalTagihan = $nominalInvoice + $fee;

        // Expiration: 3 hari dari sekarang atau tanggal jatuh tempo jika lebih dekat (min 24 jam)
        $expiredAt = $this->hitungExpiredAt($invoice);

        $externalId = sprintf('INV-%s-VA-%s-%s', $invoice->no_invoice, strtolower($bankCodeUpper), now()->timestamp);
        $pelanggan = $invoice->pelanggan;
        $customerName = substr($pelanggan->namaLengkap(), 0, 50);

        $payloadRequest = [
            'reference_id' => $externalId,
            'amount' => $totalTagihan,
            'currency' => PaymentRequestCurrency::IDR,
            'payment_method' => [
                'type' => PaymentMethodType::VIRTUAL_ACCOUNT,
                'reusability' => PaymentMethodReusability::ONE_TIME_USE,
                'reference_id' => $externalId,
                'virtual_account' => [
                    'channel_code' => $bankCodeUpper,
                    'channel_properties' => [
                        'customer_name' => $customerName,
                        'expires_at' => $expiredAt->toIso8601String(),
                    ],
                ],
            ],
            'customer' => [
                'reference_id' => (string) $pelanggan->no_reg,
                'given_names' => $pelanggan->nama_depan,
                'surname' => $pelanggan->nama_belakang ?? '',
                'email' => $pelanggan->email,
                'mobile_number' => $pelanggan->no_hp ? '+'.$pelanggan->no_hp : null,
            ],
            'description' => "Tagihan Internet UNMS Invoice {$invoice->no_invoice}",
        ];

        try {
            // Panggil API Xendit jika API Key tersedia dan bukan testing environment
            if (! empty($this->apiKey) && ! app()->environment('testing')) {
                $params = new PaymentRequestParameters([
                    'reference_id' => $externalId,
                    'amount' => $totalTagihan,
                    'currency' => PaymentRequestCurrency::IDR,
                    'payment_method' => new PaymentMethodParameters([
                        'type' => PaymentMethodType::VIRTUAL_ACCOUNT,
                        'reusability' => PaymentMethodReusability::ONE_TIME_USE,
                        'reference_id' => $externalId,
                        'virtual_account' => new VirtualAccountParameters([
                            'channel_code' => $bankCodeUpper,
                            'channel_properties' => new VirtualAccountChannelProperties([
                                'customer_name' => $customerName,
                                'expires_at' => $expiredAt->toIso8601String(),
                            ]),
                        ]),
                    ]),
                    'description' => "Tagihan Internet UNMS Invoice {$invoice->no_invoice}",
                ]);

                $response = $this->paymentRequestApi->createPaymentRequest(
                    idempotency_key: $externalId,
                    payment_request_parameters: $params
                );

                $responseArray = json_decode((string) json_encode($response), true) ?: [];
                $xenditId = $response->getId();

                $pm = $response->getPaymentMethod();
                $va = $pm?->getVirtualAccount();
                $props = $va?->getChannelProperties();
                $nomorVa = $props?->getVirtualAccountNumber() ?? ($responseArray['payment_method']['virtual_account']['channel_properties']['virtual_account_number'] ?? null);
            } else {
                // Mock fallback untuk testing lokal/unit test tanpa network call
                $xenditId = 'pr_mock_'.uniqid();
                $nomorVa = '8808'.mt_rand(10000000, 99999999);
                $responseArray = ['mock' => true, 'id' => $xenditId, 'virtual_account_number' => $nomorVa];
            }

            return TransaksiPaymentGateway::create([
                'invoice_id' => $invoice->id,
                'gateway' => 'xendit',
                'external_id' => $externalId,
                'xendit_reference_id' => $xenditId,
                'channel' => GatewayChannel::VirtualAccount,
                'channel_detail' => strtolower($bankCodeUpper),
                'nomor_pembayaran' => $nomorVa,
                'total_tagihan' => $totalTagihan,
                'fee_gateway' => $fee,
                'status' => StatusTransaksiGateway::Pending,
                'expired_at' => $expiredAt,
                'payload_request' => $payloadRequest,
                'payload_response' => $responseArray,
            ]);
        } catch (XenditSdkException $e) {
            Log::error('Gagal membuat VA Xendit: '.$e->getMessage(), [
                'invoice' => $invoice->no_invoice,
                'bank' => $bankCodeUpper,
                'error' => $e->getFullError(),
            ]);
            throw new Exception('Gagal membuat Virtual Account Xendit: '.$e->getMessage());
        } catch (Exception $e) {
            Log::error('Error pembuatan VA Xendit: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Buat tagihan QRIS Dinamis untuk Invoice tertentu.
     */
    public function buatQris(Invoice $invoice): TransaksiPaymentGateway
    {
        $pengaturan = PengaturanGateway::getXenditSetting();
        $nominalInvoice = (float) $invoice->jumlah_setelah_promo;
        $fee = $pengaturan->hitungFee('qris', $nominalInvoice);
        $totalTagihan = $nominalInvoice + $fee;

        $expiredAt = $this->hitungExpiredAt($invoice);
        $externalId = sprintf('INV-%s-QRIS-%s', $invoice->no_invoice, now()->timestamp);
        $pelanggan = $invoice->pelanggan;

        $payloadRequest = [
            'reference_id' => $externalId,
            'amount' => $totalTagihan,
            'currency' => PaymentRequestCurrency::IDR,
            'payment_method' => [
                'type' => PaymentMethodType::QR_CODE,
                'reusability' => PaymentMethodReusability::ONE_TIME_USE,
                'reference_id' => $externalId,
                'qr_code' => [
                    'channel_code' => QRCodeChannelCode::QRIS,
                    'channel_properties' => [
                        'expires_at' => $expiredAt->toIso8601String(),
                    ],
                ],
            ],
            'description' => "Tagihan Internet UNMS QRIS {$invoice->no_invoice}",
        ];

        try {
            if (! empty($this->apiKey) && ! app()->environment('testing')) {
                $params = new PaymentRequestParameters([
                    'reference_id' => $externalId,
                    'amount' => $totalTagihan,
                    'currency' => PaymentRequestCurrency::IDR,
                    'payment_method' => new PaymentMethodParameters([
                        'type' => PaymentMethodType::QR_CODE,
                        'reusability' => PaymentMethodReusability::ONE_TIME_USE,
                        'reference_id' => $externalId,
                        'qr_code' => new QRCodeParameters([
                            'channel_code' => QRCodeChannelCode::QRIS,
                            'channel_properties' => new QRCodeChannelProperties([
                                'expires_at' => $expiredAt->toIso8601String(),
                            ]),
                        ]),
                    ]),
                    'description' => "Tagihan Internet UNMS QRIS {$invoice->no_invoice}",
                ]);

                $response = $this->paymentRequestApi->createPaymentRequest(
                    idempotency_key: $externalId,
                    payment_request_parameters: $params
                );

                $responseArray = json_decode((string) json_encode($response), true) ?: [];
                $xenditId = $response->getId();

                $pm = $response->getPaymentMethod();
                $qr = $pm?->getQrCode();
                $props = $qr?->getChannelProperties();
                $qrString = $props?->getQrString() ?? ($responseArray['payment_method']['qr_code']['channel_properties']['qr_string'] ?? null);
            } else {
                $xenditId = 'pr_qris_mock_'.uniqid();
                $qrString = '00020101021226590014ID.LINKAJA.WWW01189360091100223120150215MOCK'.uniqid();
                $responseArray = ['mock' => true, 'id' => $xenditId, 'qr_string' => $qrString];
            }

            return TransaksiPaymentGateway::create([
                'invoice_id' => $invoice->id,
                'gateway' => 'xendit',
                'external_id' => $externalId,
                'xendit_reference_id' => $xenditId,
                'channel' => GatewayChannel::Qris,
                'channel_detail' => 'qris',
                'nomor_pembayaran' => 'QRIS Dinamis',
                'qr_string' => $qrString,
                'total_tagihan' => $totalTagihan,
                'fee_gateway' => $fee,
                'status' => StatusTransaksiGateway::Pending,
                'expired_at' => $expiredAt,
                'payload_request' => $payloadRequest,
                'payload_response' => $responseArray,
            ]);
        } catch (XenditSdkException $e) {
            Log::error('Gagal membuat QRIS Xendit: '.$e->getMessage(), [
                'invoice' => $invoice->no_invoice,
                'error' => $e->getFullError(),
            ]);
            throw new Exception('Gagal membuat QRIS Xendit: '.$e->getMessage());
        } catch (Exception $e) {
            Log::error('Error pembuatan QRIS Xendit: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Cek status transaksi pembayaran ke Xendit API (untuk rekonsiliasi admin).
     *
     * @return array<string, mixed>
     */
    public function cekStatusTransaksi(TransaksiPaymentGateway $transaksi): array
    {
        if (empty($this->apiKey)) {
            return [
                'status' => $transaksi->status->value,
                'message' => 'Xendit Secret Key belum dikonfigurasi (Mode Offline / Dev Mock).',
            ];
        }

        try {
            if ($transaksi->xendit_reference_id) {
                $response = $this->paymentRequestApi->getPaymentRequestByID($transaksi->xendit_reference_id);

                return json_decode((string) json_encode($response), true) ?: [];
            }

            return ['status' => $transaksi->status->value];
        } catch (Exception $e) {
            Log::error("Gagal cek status transaksi {$transaksi->external_id}: ".$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Cek koneksi dan validitas API Key ke Xendit Sandbox/Production API.
     *
     * @return array<string, mixed>
     */
    public function cekKoneksiApi(): array
    {
        if (app()->environment('testing')) {
            return [
                'success' => true,
                'message' => 'Koneksi ke Xendit API berhasil terhubung (Testing Mock).',
                'balance' => 15000000.0,
                'currency' => 'IDR',
            ];
        }

        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'XENDIT_SECRET_KEY belum dikonfigurasi di file .env',
            ];
        }

        try {
            $balanceApi = new BalanceApi(
                client: new Client(['timeout' => 15]),
                config: Configuration::getDefaultConfiguration()
            );

            $balance = $balanceApi->getBalance('CASH');
            $balanceData = json_decode((string) json_encode($balance), true) ?: [];

            return [
                'success' => true,
                'message' => 'Koneksi ke Xendit API berhasil terhubung.',
                'balance' => $balanceData['balance'] ?? ($balance->getBalance() ?? null),
                'currency' => 'IDR',
            ];
        } catch (XenditSdkException $e) {
            Log::error('Gagal ping koneksi Xendit API: '.$e->getMessage(), [
                'error' => $e->getFullError(),
            ]);

            return [
                'success' => false,
                'message' => 'Autentikasi Xendit gagal atau API Key tidak valid: '.$e->getMessage(),
                'error' => $e->getFullError(),
            ];
        } catch (Exception $e) {
            Log::error('Error ping koneksi Xendit API: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Gagal menghubungi Xendit API: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Simulasikan pembayaran di server Xendit Sandbox via SDK.
     *
     * @return array<string, mixed>
     */
    public function simulasikanPembayaran(TransaksiPaymentGateway $transaksi): array
    {
        if (app()->environment('testing')) {
            return [
                'success' => true,
                'message' => 'Simulasi pembayaran berhasil dikirim ke Xendit Sandbox API (Testing Mock).',
                'data' => ['status' => 'SUCCEEDED', 'mock' => true],
            ];
        }

        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'XENDIT_SECRET_KEY belum dikonfigurasi di .env',
            ];
        }

        if (empty($transaksi->xendit_reference_id)) {
            return [
                'success' => false,
                'message' => 'Transaksi tidak memiliki referensi ID Xendit (xendit_reference_id).',
            ];
        }

        try {
            $response = $this->paymentRequestApi->simulatePaymentRequestPayment($transaksi->xendit_reference_id);
            $result = json_decode((string) json_encode($response), true) ?: [];

            return [
                'success' => true,
                'message' => 'Simulasi pembayaran berhasil dikirim ke Xendit Sandbox API. Xendit akan mengirim webhook ke URL terdaftar.',
                'data' => $result,
            ];
        } catch (XenditSdkException $e) {
            Log::error("Gagal simulasi pembayaran Xendit {$transaksi->external_id}: ".$e->getMessage(), [
                'error' => $e->getFullError(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal simulasi pembayaran Xendit: '.$e->getMessage(),
                'error' => $e->getFullError(),
            ];
        } catch (Exception $e) {
            Log::error("Error simulasi pembayaran Xendit {$transaksi->external_id}: ".$e->getMessage());

            return [
                'success' => false,
                'message' => 'Error simulasi Xendit: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Simulasikan penerimaan Webhook lokal secara internal tanpa butuh tunnel publik.
     *
     * @return array<string, mixed>
     */
    public function simulasikanWebhookLokal(TransaksiPaymentGateway $transaksi): array
    {
        $token = (string) config('services.xendit.callback_token');

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'XENDIT_CALLBACK_TOKEN belum dikonfigurasi di file .env',
            ];
        }

        $isQris = $transaksi->channel === GatewayChannel::Qris;

        $payload = [
            'event' => 'payment.succeeded',
            'id' => 'sim_evt_'.uniqid(),
            'created' => Carbon::now()->toIso8601String(),
            'data' => [
                'id' => $transaksi->xendit_reference_id ?: ('sim_pr_'.uniqid()),
                'reference_id' => $transaksi->external_id,
                'status' => 'SUCCEEDED',
                'amount' => (float) $transaksi->total_tagihan,
                'currency' => 'IDR',
                'payment_method' => [
                    'type' => $isQris ? 'QR_CODE' : 'VIRTUAL_ACCOUNT',
                    'reference_id' => $transaksi->external_id,
                    'virtual_account' => [
                        'channel_code' => strtoupper($transaksi->channel_detail ?: 'BCA'),
                        'channel_properties' => [
                            'account_number' => $transaksi->nomor_pembayaran ?: '880812345678',
                        ],
                    ],
                    'qr_code' => [
                        'channel_code' => 'QRIS',
                        'channel_properties' => [
                            'qr_string' => $transaksi->qr_string ?: 'SIMULATED_QRIS_STRING',
                        ],
                    ],
                ],
                'updated' => Carbon::now()->toIso8601String(),
            ],
        ];

        $rawJson = json_encode($payload);
        $request = Request::create(
            uri: '/webhook/xendit',
            method: 'POST',
            parameters: $payload,
            cookies: [],
            files: [],
            server: [
                'HTTP_X_CALLBACK_TOKEN' => $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $rawJson
        );
        $request->headers->set('x-callback-token', $token);

        /** @var XenditWebhookController $controller */
        $controller = app(XenditWebhookController::class);
        $response = $controller->handle($request);
        $responseData = json_decode((string) $response->getContent(), true) ?: [];

        $isSuccess = $response->getStatusCode() === 200;

        return [
            'success' => $isSuccess,
            'status_code' => $response->getStatusCode(),
            'message' => $isSuccess ? 'Simulasi webhook lokal berhasil diproses.' : 'Webhook lokal gagal diproses.',
            'response' => $responseData,
        ];
    }

    /**
     * Hitung tanggal kedaluwarsa tagihan gateway (3 hari atau tanggal jatuh tempo invoice).
     */
    protected function hitungExpiredAt(Invoice $invoice): Carbon
    {
        $default3Days = Carbon::now()->addDays(3);
        $due = Carbon::parse($invoice->tanggal_jatuh_tempo)->endOfDay();

        // Jika jatuh tempo lebih awal dari 3 hari, tapi tetap minimal 24 jam ke depan
        if ($due->isPast()) {
            return Carbon::now()->addDay();
        }

        if ($due->lessThan($default3Days)) {
            return $due->diffInHours(Carbon::now()) < 24 ? Carbon::now()->addDay() : $due;
        }

        return $default3Days;
    }
}
