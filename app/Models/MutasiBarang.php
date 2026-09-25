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
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Barang Masuk / Barang Keluar -- satu-satunya sumber angka stok (ADR-0057). Tidak diedit setelah tersimpan;
 * boleh dihapus lewat HapusMutasiBarangAction (ADR-0058).
 *
 * @property int $id
 * @property int $jenis_barang_id
 * @property ArahMutasiBarang $arah
 * @property TipeMutasiBarang $tipe
 * @property string|null $keperluan
 * @property Carbon $tanggal
 * @property int $jumlah
 * @property string|null $keterangan
 * @property int|null $ticket_id
 * @property int|null $dicatat_oleh
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JenisBarang $jenisBarang
 * @property-read Collection<int, User> $teknisi
 * @property-read Ticket|null $ticket
 * @property-read Collection<int, UnitBarang> $units
 */
#[Fillable(['jenis_barang_id', 'arah', 'tipe', 'tanggal', 'jumlah', 'keperluan', 'keterangan', 'ticket_id', 'dicatat_oleh'])]
class MutasiBarang extends Model
{
    /** @use HasFactory<MutasiBarangFactory> */
    use HasFactory, LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('inventaris')->logFillable();
    }

    /**
     * Teknisi penerima Barang Keluar; setara, tanpa penerima utama.
     *
     * @return BelongsToMany<User, $this>
     */
    public function teknisi(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mutasi_barang_teknisi', 'mutasi_barang_id', 'user_id');
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
        return $this->belongsToMany(UnitBarang::class, 'mutasi_barang_unit', 'mutasi_barang_id', 'unit_barang_id')
            ->withPivot('kondisi_sebelum_id');
    }
}
