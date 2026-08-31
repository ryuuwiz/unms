<?php

namespace App\Console\Commands;

use App\Services\Whatsapp\WhatsappClient;
use Illuminate\Console\Command;

class WablasPingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wablas:ping {--send= : Kirim pesan uji coba ke nomor tujuan tertentu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa status koneksi WhatsApp Gateway WABLAS dan kirim pesan uji coba';

    /**
     * Execute the console command.
     */
    public function handle(WhatsappClient $client): int
    {
        $this->info('Memeriksa status gateway WABLAS...');

        $info = $client->getDeviceInfo();

        $this->table(['Parameter', 'Nilai'], [
            ['Host', config('services.wablas.host')],
            ['Nomor Terdaftar', $info['phone'] ?: '-'],
            ['Status Koneksi', $info['connected'] ? 'ONLINE / CONNECTED' : 'OFFLINE / DISCONNECTED'],
            ['Pesan Status', $info['message']],
            ['Sisa Kuota Pesan', is_scalar($info['quota']) ? (string) $info['quota'] : '-'],
            ['Masa Aktif', $info['expired_at'] ?: '-'],
        ]);

        $testNumber = $this->option('send');
        if ($testNumber) {
            $this->info("Mengirim pesan pengujian ke {$testNumber}...");
            $result = $client->sendMessage(
                $testNumber,
                'Halo! Ini adalah pesan pengujian koneksi WABLAS WhatsApp Gateway dari '.config('app.name', 'GOBILLING').' pada '.now()->translatedFormat('d F Y H:i:s')
            );

            if ($result['success']) {
                $this->info("✓ Pesan uji coba berhasil dikirim! Status: {$result['status']}");
            } else {
                $this->error("✗ Gagal mengirim pesan uji coba: {$result['message']}");
            }
        }

        return $info['connected'] ? Command::SUCCESS : Command::FAILURE;
    }
}
