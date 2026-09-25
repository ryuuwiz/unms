<?php

namespace App\Listeners;

use App\Enums\StatusTugasTerjadwal;
use App\Models\LogTugasTerjadwal;
use App\Models\User;
use App\Notifications\TugasTerjadwalGagalNotification;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mencatat Log Tugas Terjadwal dari event scheduler bawaan Laravel dan memberi alert
 * super_admin/noc saat tugas gagal (maksimal satu alert per tugas per 30 menit).
 */
class CatatLogTugasTerjadwal
{
    public const int BATAS_OUTPUT = 5000;

    public const int MENIT_THROTTLE_ALERT = 30;

    public function handle(ScheduledTaskFinished|ScheduledTaskFailed|ScheduledBackgroundTaskFinished $event): void
    {
        // Listener berjalan di dalam try schedule:run -- exception di sini akan memicu
        // ScheduledTaskFailed lagi lalu menghentikan tugas-tugas berikutnya. Logging tidak
        // boleh pernah mematikan penjadwal.
        try {
            $this->catat($event);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function catat(ScheduledTaskFinished|ScheduledTaskFailed|ScheduledBackgroundTaskFinished $event): void
    {
        $task = $event->task;

        // Tugas runInBackground: Finished pertama hanya berarti proses sudah dilepas;
        // hasil sebenarnya datang lewat ScheduledBackgroundTaskFinished.
        if ($task->runInBackground && $event instanceof ScheduledTaskFinished && ! $task->skippedBecauseOverlapping) {
            return;
        }

        $status = match (true) {
            $event instanceof ScheduledTaskFailed => StatusTugasTerjadwal::Gagal,
            $task->skippedBecauseOverlapping => StatusTugasTerjadwal::Dilewati,
            ($task->exitCode ?? 0) !== 0 => StatusTugasTerjadwal::Gagal,
            default => StatusTugasTerjadwal::Berhasil,
        };

        if ($this->isFrekuensiTinggi($task) && $status !== StatusTugasTerjadwal::Gagal) {
            return;
        }

        $perintah = $this->namaPerintah($task);
        $runtime = $event instanceof ScheduledTaskFinished && $status !== StatusTugasTerjadwal::Dilewati ? $event->runtime : null;

        LogTugasTerjadwal::create([
            'perintah' => $perintah,
            'status' => $status,
            'exit_code' => $task->exitCode,
            'durasi_detik' => $runtime,
            'output' => $status === StatusTugasTerjadwal::Gagal ? $this->ekorOutput($task, $event) : null,
            'mulai_at' => $runtime !== null ? now()->subMilliseconds((int) round($runtime * 1000)) : null,
            'selesai_at' => now(),
        ]);

        if ($status === StatusTugasTerjadwal::Gagal) {
            $this->alertOperator($perintah);
        }
    }

    /**
     * Tugas sub-menit dan tiap-menit hanya dicatat saat gagal agar tabel tidak membengkak.
     */
    protected function isFrekuensiTinggi(Event $task): bool
    {
        return $task->isRepeatable() || $task->expression === '* * * * *';
    }

    /**
     * "'/usr/bin/php' 'artisan' invoice:generate" menjadi "invoice:generate".
     */
    protected function namaPerintah(Event $task): string
    {
        if ($task->command === null) {
            return (string) ($task->description ?? 'Closure');
        }

        return Str::of($task->command)->after("'artisan' ")->trim()->limit(255, '')->value();
    }

    /**
     * Pesan exception bila tugas melempar, atau 5 KB terakhir output (storeOutput di routes/console.php).
     */
    protected function ekorOutput(Event $task, ScheduledTaskFinished|ScheduledTaskFailed|ScheduledBackgroundTaskFinished $event): ?string
    {
        if ($event instanceof ScheduledTaskFailed) {
            return Str::limit($event->exception->getMessage(), self::BATAS_OUTPUT);
        }

        if (! is_string($task->output) || ! is_file($task->output)) {
            return null;
        }

        return substr((string) file_get_contents($task->output), -self::BATAS_OUTPUT) ?: null;
    }

    protected function alertOperator(string $perintah): void
    {
        if (! Cache::add('tugas-terjadwal-alert:'.sha1($perintah), true, now()->addMinutes(self::MENIT_THROTTLE_ALERT))) {
            return;
        }

        User::role(['super_admin', 'noc'])->get()
            ->each->notify(new TugasTerjadwalGagalNotification($perintah));
    }
}
