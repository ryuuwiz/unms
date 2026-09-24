<?php

namespace App\Models;

use Database\Factories\KategoriBarangFactory;
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
 */
#[Fillable(['kode', 'nama'])]
class KategoriBarang extends Model
{
    /** @use HasFactory<KategoriBarangFactory> */
    use HasFactory;

    protected $table = 'kategori_barang';

    /**
     * @return HasMany<JenisBarang, $this>
     */
    public function jenisBarang(): HasMany
    {
        return $this->hasMany(JenisBarang::class, 'kategori_barang_id');
    }
}
