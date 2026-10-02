<?php

namespace App\Console\Commands;

use App\Models\PengaturanGateway;
use App\Services\Xendit\XenditPaymentService;
use Illuminate\Console\Command;

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
    public function handle(XenditPaymentService $paymentService): int
    {
        $this->info('====================================================');
        $this->info('       XENDIT GATEWAY DIAGNOSTIC & HEALTHCHECK      ');
        $this->info('====================================================');

        $secretKey = (string) config('services.xendit.secret_key');
        $xenditEnv = (string) config('services.xendit.env', 'development');
        $setting = PengaturanGateway::getSettingForProvider('xendit') ?? PengaturanGateway::getXenditSetting();

        // 1. Ringkasan Konfigurasi
        $maskedKey = ! empty($secretKey) ? substr($secretKey, 0, 16).'...'.substr($secretKey, -4) : '<KOSONG>';

        $this->table(
            ['Parameter', 'Status / Nilai'],
            [
                ['Environment', $xenditEnv],
                ['Sandbox Mode (DB)', $setting->sandbox_mode ? 'AKTIF (Sandbox)' : 'NONAKTIF (Production)'],
                ['Gateway Active (DB)', $setting->is_active ? 'AKTIF' : 'NONAKTIF'],
                ['Secret Key (.env)', $maskedKey],
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

        // 3. Callback webhook: satu-satunya URL yang diterima, diverifikasi dengan token koneksi.
        $this->newLine();
        $this->line('   Callback URL Xendit: '.route('webhook.payment', ['gateway' => 'xendit']));

        if (empty($setting->getCredential('callback_token', ''))) {
            $this->warn('⚠️  Callback token belum diisi pada Koneksi Payment Gateway. Webhook callback dari Xendit akan ditolak (401).');
        } else {
            $this->info('✅ Callback token terisi pada Koneksi Payment Gateway.');
        }

        $this->newLine();
        $this->info('Diagnostik selesai.');

        return $apiResult['success'] ? self::SUCCESS : self::FAILURE;
    }
}
