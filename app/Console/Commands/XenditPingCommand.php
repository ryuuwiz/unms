<?php

namespace App\Console\Commands;

use App\Models\PengaturanGateway;
use App\Services\Xendit\XenditPaymentService;
use App\Services\Xendit\XenditWebhookVerifier;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class XenditPingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xendit:ping';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memeriksa konektivitas dan kesehatan konfigurasi Xendit API & Webhook';

    /**
     * Execute the console command.
     */
    public function handle(XenditPaymentService $paymentService, XenditWebhookVerifier $webhookVerifier): int
    {
        $this->info('====================================================');
        $this->info('       XENDIT GATEWAY DIAGNOSTIC & HEALTHCHECK      ');
        $this->info('====================================================');

        $secretKey = (string) config('services.xendit.secret_key');
        $callbackToken = (string) config('services.xendit.callback_token');
        $xenditEnv = (string) config('services.xendit.env', 'development');
        $setting = PengaturanGateway::getXenditSetting();

        // 1. Ringkasan Konfigurasi
        $maskedKey = ! empty($secretKey) ? substr($secretKey, 0, 16).'...'.substr($secretKey, -4) : '<KOSONG>';
        $maskedToken = ! empty($callbackToken) ? substr($callbackToken, 0, 8).'...'.substr($callbackToken, -4) : '<KOSONG>';

        $this->table(
            ['Parameter', 'Status / Nilai'],
            [
                ['Environment', $xenditEnv],
                ['Sandbox Mode (DB)', $setting->sandbox_mode ? 'AKTIF (Sandbox)' : 'NONAKTIF (Production)'],
                ['Gateway Active (DB)', $setting->is_active ? 'AKTIF' : 'NONAKTIF'],
                ['Secret Key (.env)', $maskedKey],
                ['Callback Token (.env)', $maskedToken],
            ]
        );

        if (empty($secretKey)) {
            $this->error('❌ XENDIT_SECRET_KEY belum diisi pada file .env!');

            return self::FAILURE;
        }

        // 2. Uji Koneksi API Outbound ke Xendit
        $this->newLine();
        $this->info('Menguji koneksi ke Xendit API Server...');
        $apiResult = $paymentService->cekKoneksiApi();

        if ($apiResult['success']) {
            $this->info('✅ Xendit API Server: TERHUBUNG');
            if (isset($apiResult['balance'])) {
                $this->line(sprintf('   Saldo Kas (Sandbox): IDR %s', number_format((float) $apiResult['balance'], 0, ',', '.')));
            }
        } else {
            $this->error('❌ Xendit API Server: GAGAL');
            $this->line('   Pesan: '.$apiResult['message']);
        }

        // 3. Uji Webhook Token Verifier
        $this->newLine();
        $this->info('Menguji Verifikasi Webhook Callback Token...');

        if (empty($callbackToken)) {
            $this->warn('⚠️  XENDIT_CALLBACK_TOKEN belum diatur di .env. Webhook callback dari Xendit akan ditolak (401).');
        } else {
            $mockRequestValid = Request::create('/webhook/xendit', 'POST', [], [], [], [
                'HTTP_X_CALLBACK_TOKEN' => $callbackToken,
            ]);
            $mockRequestInvalid = Request::create('/webhook/xendit', 'POST', [], [], [], [
                'HTTP_X_CALLBACK_TOKEN' => 'invalid_token_sample',
            ]);

            $validPassed = $webhookVerifier->verifikasi($mockRequestValid);
            $invalidRejected = ! $webhookVerifier->verifikasi($mockRequestInvalid);

            if ($validPassed && $invalidRejected) {
                $this->info('✅ Webhook Token Verifier: VALID (Proteksi Hash-Equals Bekerja Sempurna)');
            } else {
                $this->error('❌ Webhook Token Verifier: GAGAL pada pengujian pencocokan token.');
            }
        }

        $this->newLine();
        $this->info('Diagnostik selesai.');

        return $apiResult['success'] ? self::SUCCESS : self::FAILURE;
    }
}
