<?php

namespace App\Console\Commands;

use App\Services\Whatsapp\WhatsappWebhookService;
use Illuminate\Console\Command;

class WhatsappSimulateWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:simulate-webhook
                            {type : Tipe webhook yang disimulasikan: tracking | message}
                            {--phone= : Nomor WhatsApp pengirim/tujuan}
                            {--message= : Isi teks pesan masuk (untuk type=message)}
                            {--status=delivered : Status tracking (sent, delivered, read, failed)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulasi payload webhook WhatsApp (GOWA/WAHA) secara lokal';

    /**
     * Execute the console command.
     */
    public function handle(WhatsappWebhookService $webhookService): int
    {
        $type = strtolower($this->argument('type'));
        $phone = $this->option('phone') ?? '08970919525';

        if ($type === 'message') {
            $messageText = $this->option('message') ?? 'TAGIHAN';

            $this->info("📩 Mensimulasikan Pesan Masuk dari {$phone}: '{$messageText}'");

            $payload = [
                'id' => 'msg_sim_'.uniqid(),
                'phone' => $phone,
                'sender' => $phone,
                'message' => $messageText,
                'pushName' => 'Pelanggan Uji Coba',
                'timestamp' => time(),
            ];

            $result = $webhookService->process($payload);

            $this->table(['Status', 'Tipe', 'Hasil'], [
                [$result['status'] ? '✅ SUKSES' : '❌ GAGAL', $result['type'], $result['message']],
            ]);

            return self::SUCCESS;
        }

        if ($type === 'tracking') {
            $status = $this->option('status');

            $this->info("📡 Mensimulasikan Tracking Status DLR untuk {$phone}: Status={$status}");

            $payload = [
                'id' => 'dlr_sim_'.uniqid(),
                'phone' => $phone,
                'status' => $status,
                'note' => "Simulasi status {$status}",
                'timestamp' => time(),
            ];

            $result = $webhookService->process($payload);

            $this->table(['Status', 'Tipe', 'Hasil'], [
                [$result['status'] ? '✅ SUKSES' : '❌ GAGAL', $result['type'], $result['message']],
            ]);

            return self::SUCCESS;
        }

        $this->error("Tipe webhook tidak valid. Gunakan 'tracking' atau 'message'.");

        return self::FAILURE;
    }
}
