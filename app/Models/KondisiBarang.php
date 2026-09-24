<?php

namespace App\Models;

use Database\Factories\KondisiBarangFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Segmen kode barang yang dikelola di pengaturan -- lihat CONTEXT.md "Kode Barang".
 *
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $dipakai Hanya terisi lewat withCount(... as dipakai) di halaman pengaturan
 */
#[Fillable(['kode', 'nama'])]
class KondisiBarang extends Model
{
    /** @use HasFactory<KondisiBarangFactory> */
    use HasFactory;

    protected $table = 'kondisi_barang';

    /**
     * @return HasMany<UnitBarang, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(UnitBarang::class, 'kondisi_barang_id');
    }
}
