<?php

namespace App\Jobs\Mikrotik\Concerns;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Exceptions\MikrotikException;
use App\Models\MikrotikJobLog;
use App\Services\Mikrotik\NotifikasiNoc;
use DateTimeInterface;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Antrean job per-pelanggan (provisi, isolir, un-isolir, ganti profil, hapus secret) -- ADR-0059.
 *
 * Kunci per router TERPISAH dari rekonsiliasi router-wide (`mikrotik-router-{id}`, dipegang
 * RecoverPppRouterJob/ProvisionRouterJob hingga menit-an), sehingga aksi pelanggan tidak antre di
 * belakangnya. Maksimal 2 sesi API bersamaan per router: satu rekonsiliasi + satu aksi pelanggan.
 *
 * Batas percobaan memakai waktu (retryUntil), bukan jumlah: setiap release karena kunci dipegang
 * ikut dihitung sebagai attempt, sehingga `$tries` habis tanpa handle() pernah jalan.
 */
trait AntreanLayananRouter
{
    /** Kegagalan koneksi (exception) maksimal sebelum dianggap gagal; release karena antre tidak dihitung. */
    public int $maxExceptions = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [2, 5, 10, 30, 60];

    /** Waktu dispatch; failed() memakainya untuk mencari log kegagalan milik job ini. */
    public ?string $didispatchPada = null;

    abstract protected function routerIdUntukKunci(): int;

    abstract public function failed(?Throwable $exception): void;

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("mikrotik-layanan-router-{$this->routerIdUntukKunci()}"))
                ->releaseAfter(1)
                ->expireAfter(120)
                ->shared(),
        ];
    }

    public function retryUntil(): DateTimeInterface
    {
        return Carbon::now()->addMinutes(10);
    }

    /**
     * Galat permanen langsung digagalkan (tanpa menunggu retry); galat koneksi dilempar untuk di-retry.
     *
     * @throws Throwable
     */
    protected function gagalkanAtauCobaLagi(Throwable $e): void
    {
        if (! MikrotikException::bisaDicobaLagi($e)) {
            // Di antrean: tandai gagal (Horizon failed jobs) yang juga memanggil failed(). Dipanggil langsung: failed() saja.
            $this->job ? $this->fail($e) : $this->failed($e);

            return;
        }

        throw $e;
    }

    protected function tandaiWaktuDispatch(): void
    {
        $this->didispatchPada = Carbon::now()->toDateTimeString();
    }

    /**
     * Kegagalan akhir: pakai log gagal milik job ini bila handle() sempat mencatatnya, selain itu
     * buat log baru (job yang tidak pernah jalan tetap tercatat), lalu beri tahu NOC.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function laporkanKegagalanAkhir(MikrotikJobType $jenis, ?int $routerId, ?int $layananId, ?Throwable $exception, array $payload = [], bool $beriTahu = true): void
    {
        if (! $routerId) {
            return;
        }

        $log = MikrotikJobLog::query()
            ->where('router_id', $routerId)
            ->where('layanan_pelanggan_id', $layananId)
            ->where('job_type', $jenis)
            ->where('status', MikrotikJobStatus::Failed)
            ->when($this->didispatchPada, fn ($q, string $waktu) => $q->where('created_at', '>=', $waktu))
            ->latest('id')
            ->first();

        $log ??= MikrotikJobLog::create([
            'router_id' => $routerId,
            'layanan_pelanggan_id' => $layananId,
            'job_type' => $jenis,
            'status' => MikrotikJobStatus::Failed,
            'attempt_count' => $this->attempts(),
            'payload' => $payload,
            'error_message' => $exception?->getMessage() ?? 'Batas waktu percobaan habis',
            'finished_at' => Carbon::now(),
        ]);

        if ($beriTahu) {
            app(NotifikasiNoc::class)->kirim($log, whatsapp: true);
        }
    }
}
