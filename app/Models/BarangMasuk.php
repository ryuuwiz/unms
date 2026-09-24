<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $barang_id
 * @property Carbon $tanggal
 * @property int $jumlah_masuk
 * @property string|null $keterangan
 * @property int|null $dicatat_oleh
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Barang $barang
 * @property-read User|null $dicatatOleh
 */
#[Fillable(['barang_id', 'tanggal', 'jumlah_masuk', 'keterangan', 'dicatat_oleh'])]
class BarangMasuk extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'barang_masuk';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['barang_id', 'tanggal', 'jumlah_masuk'])
            ->useLogName('barang_masuk');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah_masuk' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Barang, $this>
     */
    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
