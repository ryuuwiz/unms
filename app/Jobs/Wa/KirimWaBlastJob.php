<?php

namespace App\Jobs\Wa;

use App\Models\AntrianWaBlast;
use App\Models\Sysblas;
use App\Services\Whatsapp\WhatsappClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
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

    public function handle(WhatsappClient $client): void
    {
        // Pastikan record masih ada dan belum berstatus terkirim
        if (! $this->antrian->exists || $this->antrian->status->value === 'terkirim') {
            return;
        }

        // Resolusi koneksi Sysblas spesifik atau default
        $sysblas = $this->antrian->sysblas ?? Sysblas::getDefault();
        $targetClient = $sysblas ? $sysblas->makeClient() : $client;
        $maxAttempts = $sysblas ? max(1, $sysblas->limit_per_menit) : 25;
        $delaySeconds = $sysblas ? max(1, $sysblas->delay_detik ?? 3) : 3;
        $jitterSeconds = $sysblas ? max(0, $sysblas->jitter_detik ?? 2) : 2;

        $gatewayId = $sysblas ? $sysblas->id : 'default';
        $limiterKey = "sysblas-rate-limit-{$gatewayId}";
        $slotKey = "sysblas-next-send-slot-{$gatewayId}";

        $now = time();
        $nextSendSlot = (int) Cache::get($slotKey, 0);

        // Pacing anti-burst: Jika masih dalam jeda inter-message cooldown, lepaskan job dengan jeda dinamis
        if ($nextSendSlot > $now) {
            $waitDuration = ($nextSendSlot - $now) + ($jitterSeconds > 0 ? rand(0, $jitterSeconds) : 0);
            $this->release(max(1, $waitDuration));

            return;
        }

        // Cek apakah limit per 60 detik sudah tercapai
        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $availableIn = RateLimiter::availableIn($limiterKey);
            $this->release(max(3, $availableIn + rand(1, 3)));

            return;
        }

        // Kunci slot waktu pengiriman berikutnya dengan jitter acak agar tidak berpola kaku
        $calculatedDelay = $delaySeconds + ($jitterSeconds > 0 ? rand(0, $jitterSeconds) : 0);
        Cache::put($slotKey, $now + $calculatedDelay, 120);

        RateLimiter::hit($limiterKey, 60);

        $this->antrian->update(['status' => 'diproses']);

        $result = $targetClient->sendMessage(
            $this->antrian->no_hp_tujuan,
            $this->antrian->pesan
        );

        if ($result['success']) {
            $this->antrian->tandaiTerkirim($result);
        } else {
            // Jika terkena response 429 Too Many Requests dari WAHA, tunda job untuk retry
            if (($result['status'] ?? '') === 'rate_limited') {
                $this->release(15 + rand(1, 5));

                return;
            }

            $this->antrian->tandaiGagal($result['message'], $result);
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
