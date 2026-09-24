<?php

namespace App\Models;

use App\Enums\Wa\TipePengingatTagihan;
use Database\Factories\AturanPengingatTagihanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $nama_aturan
 * @property TipePengingatTagihan $tipe_pengingat
 * @property int $hari_offset
 * @property string $jam_eksekusi
 * @property int $template_id
 * @property bool $kirim_ulang_berkala
 * @property int|null $interval_hari
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WaTemplate $template
 */
#[Fillable([
    'nama_aturan',
    'tipe_pengingat',
    'hari_offset',
    'jam_eksekusi',
    'template_id',
    'kirim_ulang_berkala',
    'interval_hari',
    'is_aktif',
])]
class AturanPengingatTagihan extends Model
{
    /** @use HasFactory<AturanPengingatTagihanFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'aturan_pengingat_tagihan';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('aturan_pengingat_tagihan');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe_pengingat' => TipePengingatTagihan::class,
            'hari_offset' => 'integer',
            'kirim_ulang_berkala' => 'boolean',
            'interval_hari' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<WaTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WaTemplate::class, 'template_id');
    }

    /**
     * @param  Builder<AturanPengingatTagihan>  $query
     * @return Builder<AturanPengingatTagihan>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }

    /**
     * Hitung tanggal jatuh tempo target berdasarkan tanggal acuan (hari ini).
     */
    public function hitungTanggalJatuhTempoTarget(Carbon $tanggalAcuan): Carbon
    {
        return match ($this->tipe_pengingat) {
            TipePengingatTagihan::SebelumJatuhTempo => $tanggalAcuan->copy()->addDays($this->hari_offset),
            TipePengingatTagihan::HariH => $tanggalAcuan->copy(),
            TipePengingatTagihan::SetelahJatuhTempo => $tanggalAcuan->copy()->subDays($this->hari_offset),
        };
    }
}
