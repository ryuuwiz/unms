<?php

namespace App\Models;

use App\Enums\StatusTugasTerjadwal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * Satu kali eksekusi tugas terjadwal (lihat "Log Tugas Terjadwal" di CONTEXT.md).
 *
 * @property int $id
 * @property string $perintah
 * @property StatusTugasTerjadwal $status
 * @property int|null $exit_code
 * @property string|null $durasi_detik
 * @property string|null $output
 * @property Carbon|null $mulai_at
 * @property Carbon $selesai_at
 */
#[Fillable([
    'perintah',
    'status',
    'exit_code',
    'durasi_detik',
    'output',
    'mulai_at',
    'selesai_at',
])]
class LogTugasTerjadwal extends Model
{
    use Prunable;

    public const int HARI_RETENSI = 30;

    public $timestamps = false;

    protected $table = 'log_tugas_terjadwal';

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('selesai_at', '<', now()->subDays(self::HARI_RETENSI));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusTugasTerjadwal::class,
            'mulai_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }
}
