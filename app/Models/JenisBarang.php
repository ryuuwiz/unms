<?php

namespace App\Models;

use App\Enums\Barang\ArahMutasiBarang;
use Database\Factories\JenisBarangFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Item inventaris -- lihat CONTEXT.md "Jenis Barang" dan ADR-0057.
 *
 * @property int $id
 * @property int $kategori_barang_id
 * @property string $kode Kode jenis (kolom KODE BARANG di Data Barang); unit berkode sendiri
 * @property string $nama
 * @property string $satuan
 * @property bool $dilacak_per_unit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KategoriBarang $kategori
 * @property-read int $stok_awal Hanya terisi lewat scope denganStokPeriode()
 * @property-read int $jumlah_masuk Idem
 * @property-read int $jumlah_keluar Idem
 * @property-read int $stok_akhir Idem
 */
#[Fillable(['kategori_barang_id', 'kode', 'nama', 'satuan', 'dilacak_per_unit'])]
class JenisBarang extends Model
{
    /** @use HasFactory<JenisBarangFactory> */
    use HasFactory;

    protected $table = 'jenis_barang';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dilacak_per_unit' => 'boolean',
            'stok_awal' => 'integer',
            'jumlah_masuk' => 'integer',
            'jumlah_keluar' => 'integer',
            'stok_akhir' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<KategoriBarang, $this>
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriBarang::class, 'kategori_barang_id');
    }

    /**
     * @return HasMany<MutasiBarang, $this>
     */
    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiBarang::class, 'jenis_barang_id');
    }

    /**
     * @return HasMany<UnitBarang, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(UnitBarang::class, 'jenis_barang_id');
    }

    /**
     * Stok saat ini (semua mutasi sampai hari ini).
     */
    public function stok(): int
    {
        $masuk = (int) $this->mutasi()->where('arah', ArahMutasiBarang::Masuk)->sum('jumlah');
        $keluar = (int) $this->mutasi()->where('arah', ArahMutasiBarang::Keluar)->sum('jumlah');

        return $masuk - $keluar;
    }

    /**
     * Query Stok Periode sebulan: setiap baris membawa stok_awal (mutasi sebelum awal bulan),
     * jumlah_masuk, jumlah_keluar, stok_akhir -- lihat CONTEXT.md "Stok Periode". Selalu dihitung
     * dari mutasi, tidak pernah disimpan. Dibungkus subquery beralias `jenis_barang` supaya kolom
     * hitungan bisa langsung dipakai di where()/orderBy() dan relasi tetap jalan.
     *
     * @return Builder<JenisBarang>
     */
    public static function stokPeriode(Carbon $bulan): Builder
    {
        $awal = $bulan->copy()->startOfMonth()->toDateString();
        $akhir = $bulan->copy()->endOfMonth()->toDateString();

        $jumlah = fn (ArahMutasiBarang $arah, bool $sebelumBulan) => MutasiBarang::query()
            ->selectRaw('COALESCE(SUM(jumlah), 0)')
            ->whereColumn('mutasi_barang.jenis_barang_id', 'jb.id')
            ->where('arah', $arah->value)
            ->when(
                $sebelumBulan,
                fn (Builder $q) => $q->where('tanggal', '<', $awal),
                fn (Builder $q) => $q->whereBetween('tanggal', [$awal, $akhir]),
            );

        $mentah = self::query()
            ->from('jenis_barang', 'jb')
            ->select('jb.*')
            ->selectSub($jumlah(ArahMutasiBarang::Masuk, true), 'masuk_sebelum')
            ->selectSub($jumlah(ArahMutasiBarang::Keluar, true), 'keluar_sebelum')
            ->selectSub($jumlah(ArahMutasiBarang::Masuk, false), 'jumlah_masuk')
            ->selectSub($jumlah(ArahMutasiBarang::Keluar, false), 'jumlah_keluar');

        $dihitung = DB::query()
            ->fromSub($mentah, 'm')
            ->select('m.*')
            ->selectRaw('m.masuk_sebelum - m.keluar_sebelum as stok_awal')
            ->selectRaw('m.masuk_sebelum - m.keluar_sebelum + m.jumlah_masuk - m.jumlah_keluar as stok_akhir');

        return self::query()->fromSub($dihitung, 'jenis_barang');
    }
}
