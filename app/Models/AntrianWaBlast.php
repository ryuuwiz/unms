<?php

namespace App\Models;

use App\Enums\Wa\StatusAntrianWa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property int|null $sysblas_id
 * @property string $no_hp_tujuan
 * @property string $pesan
 * @property string $jenis
 * @property string|null $referensi_tipe
 * @property int|null $referensi_id
 * @property Carbon $tanggal_kirim
 * @property StatusAntrianWa $status
 * @property Carbon|null $dijadwalkan_pada
 * @property Carbon|null $dikirim_pada
 * @property array<string, mixed>|null $response_log
 * @property string|null $pesan_error
 * @property int $percobaan_ke
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $referensi
 * @property-read Sysblas|null $sysblas
 */
#[Fillable([
    'sysblas_id',
    'no_hp_tujuan',
    'pesan',
    'jenis',
    'referensi_tipe',
    'referensi_id',
    'tanggal_kirim',
    'status',
    'dijadwalkan_pada',
    'dikirim_pada',
    'response_log',
    'pesan_error',
    'percobaan_ke',
])]
class AntrianWaBlast extends Model
{
    protected $table = 'antrian_wa_blast';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sysblas_id' => 'integer',
            'status' => StatusAntrianWa::class,
            'tanggal_kirim' => 'date',
            'dijadwalkan_pada' => 'datetime',
            'dikirim_pada' => 'datetime',
            'response_log' => 'array',
            'percobaan_ke' => 'integer',
        ];
    }

    /**
     * Relasi ke koneksi gateway Sysblas.
     *
     * @return BelongsTo<Sysblas, $this>
     */
    public function sysblas(): BelongsTo
    {
        return $this->belongsTo(Sysblas::class, 'sysblas_id');
    }

    /**
     * Relasi polimorfik ke model referensi (Invoice, Ticket, dll).
     *
     * @return MorphTo<Model, $this>
     */
    public function referensi(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'referensi_tipe', 'referensi_id');
    }

    /**
     * Scope antrean yang siap diproses.
     *
     * @param  Builder<AntrianWaBlast>  $query
     * @return Builder<AntrianWaBlast>
     */
    public function scopeSiapKirim(Builder $query): Builder
    {
        return $query->where('status', StatusAntrianWa::Menunggu)
            ->where(function (Builder $q) {
                $q->whereNull('dijadwalkan_pada')
                    ->orWhere('dijadwalkan_pada', '<=', Carbon::now());
            });
    }

    /**
     * Tandai antrean sebagai berhasil terkirim.
     *
     * @param  array<string, mixed>  $responseLog
     */
    public function tandaiTerkirim(array $responseLog = []): void
    {
        $this->update([
            'status' => StatusAntrianWa::Terkirim,
            'dikirim_pada' => Carbon::now(),
            'response_log' => $responseLog,
            'pesan_error' => null,
        ]);
    }

    /**
     * Tandai antrean sebagai gagal.
     *
     * @param  array<string, mixed>|null  $responseLog
     */
    public function tandaiGagal(string $errorMessage, ?array $responseLog = null): void
    {
        $this->update([
            'status' => StatusAntrianWa::Gagal,
            'pesan_error' => $errorMessage,
            'response_log' => $responseLog,
            'percobaan_ke' => $this->percobaan_ke + 1,
        ]);

        self::catatGagal($this, $errorMessage);
    }

    /**
     * Log kegagalan akhir pesan WA (storage/logs/whatsapp-*.log) untuk ditelusuri admin/NOC.
     */
    public static function catatGagal(self $antrian, string $errorMessage): void
    {
        Log::channel('whatsapp')->error('Pesan WA gagal terkirim', [
            'antrian_id' => $antrian->id,
            'jenis' => $antrian->jenis,
            'referensi' => $antrian->referensi_tipe ? "{$antrian->referensi_tipe}#{$antrian->referensi_id}" : null,
            'no_hp' => $antrian->no_hp_tujuan,
            'error' => $errorMessage,
        ]);
    }
}
