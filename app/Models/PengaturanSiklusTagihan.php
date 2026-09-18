<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PengaturanSiklusTagihanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pengaturan Siklus Tagihan (satu baris): hari-dalam-bulan jatuh tempo dan terbit invoice.
 *
 * @property int $id
 * @property int $hari_jatuh_tempo
 * @property int $hari_terbit_invoice
 */
#[Fillable(['hari_jatuh_tempo', 'hari_terbit_invoice'])]
class PengaturanSiklusTagihan extends Model
{
    /** @use HasFactory<PengaturanSiklusTagihanFactory> */
    use HasFactory;

    protected $table = 'pengaturan_siklus_tagihan';

    /**
     * Ambil satu-satunya baris pengaturan, dibuat dengan nilai bawaan (10 / 24) bila belum ada.
     */
    public static function ambil(): self
    {
        return static::query()->firstOrCreate([], ['hari_jatuh_tempo' => 10, 'hari_terbit_invoice' => 24]);
    }

    /**
     * Jatuh tempo sebuah Periode Tagihan (YYYY-MM): Hari Jatuh Tempo pada bulan periode itu.
     */
    public function jatuhTempoPeriode(string $periode): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $periode)->day($this->hari_jatuh_tempo);
    }

    /**
     * Tanggal terbit invoice untuk sebuah jatuh tempo: kemunculan terakhir Hari Terbit sebelum jatuh tempo.
     */
    public function tanggalTerbit(CarbonInterface $jatuhTempo): Carbon
    {
        $terbit = Carbon::parse($jatuhTempo)->startOfDay()->day($this->hari_terbit_invoice);

        return $terbit->gte($jatuhTempo) ? $terbit->subMonthNoOverflow() : $terbit;
    }

    /**
     * Sesuaikan tanggal ke Hari Jatuh Tempo pada bulan yang sama.
     */
    public function sesuaikanKeHariJatuhTempo(CarbonInterface $tanggal): Carbon
    {
        return Carbon::parse($tanggal)->startOfDay()->day($this->hari_jatuh_tempo);
    }

    /**
     * Lead Time Penerbitan Invoice: selisih hari terbit dan jatuh tempo pada siklus terdekat.
     */
    public function leadDays(): int
    {
        $jatuhTempo = Carbon::today()->day($this->hari_jatuh_tempo);

        if ($jatuhTempo->lt(Carbon::today())) {
            $jatuhTempo->addMonthNoOverflow();
        }

        return (int) $this->tanggalTerbit($jatuhTempo)->diffInDays($jatuhTempo);
    }
}
