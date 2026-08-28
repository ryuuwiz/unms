<?php

namespace App\Jobs\Wa;

use App\Models\AntrianWaBlast;
use App\Services\Wablas\WablasClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class KirimWaBlastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public function __construct(
        public AntrianWaBlast $antrian
    ) {
        $this->onQueue('wa-blast');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(WablasClient $client): void
    {
        // Pastikan record masih ada dan belum berstatus terkirim
        if (! $this->antrian->exists || $this->antrian->status->value === 'terkirim') {
            return;
        }

        // Rate Limiter: Maksimal 25 pesan per 60 detik per gateway WABLAS
        $limiterKey = 'wablas-rate-limit';
        $executed = RateLimiter::attempt(
            $limiterKey,
            25,
            function () use ($client) {
                $this->antrian->update(['status' => 'diproses']);

                $result = $client->sendMessage(
                    $this->antrian->no_hp_tujuan,
                    $this->antrian->pesan
                );

                if ($result['success']) {
                    $this->antrian->tandaiTerkirim($result);
                } else {
                    $this->antrian->tandaiGagal($result['message'], $result);
                }
            },
            60
        );

        if (! $executed) {
            // Jika laju pengiriman penuh, kembalikan ke antrean dengan delay 5 detik
            $this->release(5);
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->antrian->exists) {
            $this->antrian->tandaiGagal(
                $exception ? $exception->getMessage() : 'Job pengiriman WhatsApp gagal melebihi batas percobaan.'
            );
        }

        Log::error('KirimWaBlastJob Failed Permanently', [
            'antrian_id' => $this->antrian->id,
            'phone' => $this->antrian->no_hp_tujuan,
            'error' => $exception?->getMessage(),
        ]);
    }
}
