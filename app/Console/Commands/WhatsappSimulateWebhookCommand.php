<?php

namespace App\Console\Commands;

use App\Enums\Sysblas\SysblasProvider;
use App\Models\Sysblas;
use App\Services\Whatsapp\WhatsappClient;
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
    protected $description = 'Simulasi event webhook GOWA secara lokal (tanpa verifikasi signature HTTP)';

    /**
     * Execute the console command.
     */
    public function handle(WhatsappWebhookService $webhookService): int
    {
        $type = strtolower($this->argument('type'));
        $phone = $this->option('phone') ?? '08970919525';

        $session = (string) (Sysblas::query()
            ->where('provider', SysblasProvider::Gowa)
            ->orderByDesc('is_default')
            ->value('session_name') ?? 'default');
        $jid = WhatsappClient::normalizePhoneNumber($phone).'@s.whatsapp.net';

        if ($type === 'message') {
            $messageText = $this->option('message') ?? 'TAGIHAN';

            $this->info("📩 Mensimulasikan Pesan Masuk dari {$phone}: '{$messageText}'");

            return $this->simulasikan([
                'id' => 'msg_sim_'.uniqid(),
                'event' => 'message',
                'session' => $session,
                'payload' => [
                    'id' => 'msg_sim_'.uniqid(),
                    'from' => $jid,
                    'body' => $messageText,
                    'fromMe' => false,
                ],
            ], $webhookService);
        }

        if ($type === 'tracking') {
            $status = strtolower((string) $this->option('status'));

            $this->info("📡 Mensimulasikan ACK pengiriman untuk {$phone}: Status={$status}");

            return $this->simulasikan([
                'id' => 'ack_sim_'.uniqid(),
                'event' => 'message.ack',
                'session' => $session,
                'payload' => [
                    'id' => 'ack_sim_'.uniqid(),
                    'to' => $jid,
                    'ack' => in_array($status, ['failed', 'error'], true) ? -1 : 2,
                    'ackName' => $status,
                ],
            ], $webhookService);
        }

        $this->error("Tipe webhook tidak valid. Gunakan 'tracking' atau 'message'.");

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function simulasikan(array $payload, WhatsappWebhookService $webhookService): int
    {
        $result = $webhookService->process($payload);

        $this->table(['Status', 'Tipe', 'Hasil'], [
            [$result['status'] ? '✅ SUKSES' : '❌ GAGAL', $result['type'], $result['message']],
        ]);

        return self::SUCCESS;
    }
}
