<?php

namespace App\Models;

use App\Enums\Barang\ArahMutasiBarang;
use App\Enums\Barang\TipeMutasiBarang;
use Database\Factories\MutasiBarangFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Barang Masuk / Barang Keluar -- satu-satunya sumber angka stok (ADR-0057). Tidak diedit setelah tersimpan.
 *
 * @property int $id
 * @property int $jenis_barang_id
 * @property ArahMutasiBarang $arah
 * @property TipeMutasiBarang $tipe
 * @property Carbon $tanggal
 * @property int $jumlah
 * @property string|null $keterangan
 * @property int|null $teknisi_id
 * @property int|null $ticket_id
 * @property int|null $dicatat_oleh
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JenisBarang $jenisBarang
 * @property-read User|null $teknisi
 * @property-read Ticket|null $ticket
 * @property-read Collection<int, UnitBarang> $units
 */
#[Fillable(['jenis_barang_id', 'arah', 'tipe', 'tanggal', 'jumlah', 'keterangan', 'teknisi_id', 'ticket_id', 'dicatat_oleh'])]
class MutasiBarang extends Model
{
    /** @use HasFactory<MutasiBarangFactory> */
    use HasFactory;

    protected $table = 'mutasi_barang';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'arah' => ArahMutasiBarang::class,
            'tipe' => TipeMutasiBarang::class,
            'tanggal' => 'date',
            'jumlah' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JenisBarang, $this>
     */
    public function jenisBarang(): BelongsTo
    {
        return $this->belongsTo(JenisBarang::class, 'jenis_barang_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * @return BelongsToMany<UnitBarang, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(UnitBarang::class, 'mutasi_barang_unit', 'mutasi_barang_id', 'unit_barang_id');
    }
}
