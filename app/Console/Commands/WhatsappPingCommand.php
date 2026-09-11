<?php

namespace App\Console\Commands;

use App\Models\Sysblas;
use Illuminate\Console\Command;

class WhatsappPingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:ping
                            {--sysblas= : ID koneksi Sysblas yang diuji (default: koneksi default aktif)}
                            {--send= : Kirim pesan uji coba ke nomor tujuan tertentu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa status koneksi WhatsApp Gateway (GOWA/WAHA) dan kirim pesan uji coba';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sysblas = $this->option('sysblas')
            ? Sysblas::find((int) $this->option('sysblas'))
            : Sysblas::getDefault();

        if (! $sysblas) {
            $this->error('Tidak ditemukan koneksi Sysblas. Buat koneksi terlebih dahulu di menu Pengaturan > Koneksi WhatsApp.');

            return self::FAILURE;
        }

        $this->info("Memeriksa status gateway '{$sysblas->nama}' ({$sysblas->provider->label()})...");

        $client = $sysblas->makeClient();
        $info = $client->getDeviceInfo();

        $this->table(['Parameter', 'Nilai'], [
            ['Provider', $sysblas->provider->label()],
            ['Host', $sysblas->url_api],
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
                'Halo! Ini adalah pesan pengujian koneksi WhatsApp Gateway dari '.config('app.name', 'GOBILLING').' pada '.now()->translatedFormat('d F Y H:i:s')
            );

            if ($result['success']) {
                $this->info("✓ Pesan uji coba berhasil dikirim! Status: {$result['status']}");
            } else {
                $this->error("✗ Gagal mengirim pesan uji coba: {$result['message']}");
            }
        }

        return $info['connected'] ? self::SUCCESS : self::FAILURE;
    }
}
