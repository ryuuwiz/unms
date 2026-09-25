<?php

namespace App\Models;

use App\Enums\Ticket\DivisiTicket;
use Carbon\CarbonInterface;
use Database\Factories\RabItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Item RAB Kantor -- lihat CONTEXT.md "RAB Kantor".
 *
 * @property int $id
 * @property CarbonInterface $periode
 * @property string $uraian
 * @property int $qty
 * @property int $harga
 * @property DivisiTicket|null $divisi
 * @property string|null $divisi_lainnya
 */
class RabItem extends Model
{
    /** @use HasFactory<RabItemFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['periode', 'uraian', 'qty', 'harga', 'divisi', 'divisi_lainnya'];

    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'qty' => 'integer',
            'harga' => 'integer',
            'divisi' => DivisiTicket::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function jumlah(): int
    {
        return $this->qty * $this->harga;
    }

    public function labelDivisi(): string
    {
        return $this->divisi?->label() ?? (string) $this->divisi_lainnya;
    }

    /**
     * Bulan Terkunci: periode sebelum bulan berjalan.
     */
    public static function bulanTerkunci(CarbonInterface $periode): bool
    {
        return $periode->copy()->startOfMonth()->lt(now()->startOfMonth());
    }
}
