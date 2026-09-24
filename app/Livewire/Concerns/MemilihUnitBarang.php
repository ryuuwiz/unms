<?php

namespace App\Livewire\Concerns;

use App\Enums\Barang\StatusUnitBarang;
use App\Models\UnitBarang;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pemilihan unit barang lewat pindai barcode (scanner 1D mengetik kode + Enter) atau ketik manual.
 * Dipakai form Barang Masuk (pengembalian) dan Barang Keluar -- lihat CONTEXT.md "Status Unit Barang".
 */
trait MemilihUnitBarang
{
    /** @var list<int> */
    public array $unitIds = [];

    public string $scanKode = '';

    /**
     * Status unit yang boleh dipilih pada form ini.
     *
     * @return list<StatusUnitBarang>
     */
    abstract protected function statusUnitDipilih(): array;

    abstract protected function jenisBarangTerpilih(): ?int;

    public function tambahUnit(): void
    {
        $kode = strtoupper(trim($this->scanKode));
        $this->scanKode = '';

        if ($kode !== '') {
            $this->tambahkan(UnitBarang::query()->where('kode', $kode)->first(), $kode);
        }
    }

    public function pilihUnit(int $id): void
    {
        $this->tambahkan(UnitBarang::find($id), "#{$id}");
    }

    private function tambahkan(?UnitBarang $unit, string $label): void
    {
        if (! $unit || $unit->jenis_barang_id !== $this->jenisBarangTerpilih() || ! in_array($unit->status, $this->statusUnitDipilih(), true)) {
            $this->addError('unitIds', "Unit {$label} tidak ditemukan atau statusnya tidak sesuai untuk barang terpilih.");

            return;
        }

        if (! in_array($unit->id, $this->unitIds, true)) {
            $this->unitIds[] = $unit->id;
        }

        $this->resetErrorBag('unitIds');
    }

    public function hapusUnit(int $id): void
    {
        $this->unitIds = array_values(array_filter($this->unitIds, fn (int $unitId) => $unitId !== $id));
    }

    /**
     * @return Collection<int, UnitBarang>
     */
    protected function unitTerpilih(): Collection
    {
        return UnitBarang::query()->whereKey($this->unitIds)->orderBy('kode')->get();
    }

    /**
     * Unit kandidat untuk dipilih dari daftar (tanpa scanner).
     *
     * @return Collection<int, UnitBarang>
     */
    protected function unitKandidat(): Collection
    {
        $jenisId = $this->jenisBarangTerpilih();

        if (! $jenisId) {
            return new Collection;
        }

        return UnitBarang::query()
            ->where('jenis_barang_id', $jenisId)
            ->whereIn('status', $this->statusUnitDipilih())
            ->whereKeyNot($this->unitIds)
            ->orderBy('kode')
            ->limit(200)
            ->get();
    }
}
