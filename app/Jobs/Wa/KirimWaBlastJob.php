<?php

namespace App\Jobs\Wa;

use App\Enums\Wa\StatusAntrianWa;
use App\Models\AntrianWaBlast;
use App\Models\Sysblas;
use App\Models\User;
use App\Notifications\GatewayWaBermasalahNotification;
use App\Services\Whatsapp\WhatsappClient;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Throwable;

/**
 * Batas percobaan memakai waktu (retryUntil), bukan jumlah: setiap release karena Jeda Antar-Pesan
 * / Batas Laju ikut dihitung sebagai attempt, sehingga `$tries` habis sebelum pesan sempat dikirim
 * (pola yang sama dengan ADR-0059). Hanya galat kirim sungguhan yang dihitung (`maxExceptions`).
 */
class KirimWaBlastJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BATAS_MENIT = 120;

    public int $maxExceptions = 3;

    /** Penyapu Antrean Macet tidak menggandakan job yang masih menunggu giliran. */
    public int $uniqueFor = self::BATAS_MENIT * 60;

    public function __construct(
        public AntrianWaBlast $antrian
    ) {
        $this->onQueue('wa-blast');
    }

    public function uniqueId(): string
    {
        return (string) $this->antrian->id;
    }

    public function retryUntil(): DateTimeInterface
    {
        return Carbon::now()->addMinutes(self::BATAS_MENIT);
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
        $delaySeconds = $sysblas ? max(1, $sysblas->delay_detik ?? 300) : 300;
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

        match ($result['status']) {
            'success' => $this->antrian->tandaiTerkirim($result),
            'rate_limited' => $this->release(15 + rand(1, 5)),
            'error' => $this->cobaLagiNanti($sysblas, $result['message']),
            'unauthorized' => $this->gagalkanKarenaGateway($sysblas, $result),
            default => $this->antrian->tandaiGagal($result['message'], $result),
        };
    }

    /**
     * Galat sementara (timeout, koneksi, HTTP 5xx): kembalikan ke Menunggu dan lempar agar di-retry
     * dengan backoff; dihitung maxExceptions.
     */
    private function cobaLagiNanti(?Sysblas $sysblas, string $galat): void
    {
        $this->antrian->update([
            'status' => StatusAntrianWa::Menunggu,
            'pesan_error' => $galat,
            'percobaan_ke' => $this->antrian->percobaan_ke + 1,
        ]);

        Log::channel('whatsapp')->warning('Pengiriman WA gagal sementara, akan dicoba lagi', [
            'antrian_id' => $this->antrian->id,
            'jenis' => $this->antrian->jenis,
            'gateway' => $sysblas?->nama,
            'error' => $galat,
        ]);

        $this->beritahuGatewayBermasalah($sysblas, $galat);

        throw new RuntimeException($galat);
    }

    /**
     * @param  array{success: bool, status: string, message: string, data: array<string, mixed>}  $result
     */
    private function gagalkanKarenaGateway(?Sysblas $sysblas, array $result): void
    {
        $this->antrian->tandaiGagal($result['message'], $result);
        $this->beritahuGatewayBermasalah($sysblas, $result['message']);
    }

    /**
     * Lonceng admin & super_admin, maksimal sekali per jam per gateway.
     */
    private function beritahuGatewayBermasalah(?Sysblas $sysblas, string $galat): void
    {
        if (! Cache::add('wa-gateway-bermasalah-'.($sysblas->id ?? 'default'), true, 3600)) {
            return;
        }

        Notification::send(
            User::role(['super_admin', 'admin'])->get(),
            new GatewayWaBermasalahNotification($sysblas->nama ?? 'Default', $galat),
        );
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->antrian->exists) {
            $this->antrian->tandaiGagal(
                $exception ? $exception->getMessage() : 'Batas waktu pengiriman WhatsApp habis.'
            );
        }
    }
}
