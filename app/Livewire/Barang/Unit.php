<?php

namespace App\Livewire\Barang;

use App\Enums\Barang\StatusUnitBarang;
use App\Http\Controllers\LabelBarangPdfController;
use App\Models\JenisBarang;
use App\Models\UnitBarang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar Unit Barang berkode + cetak label -- lihat CONTEXT.md "Unit Barang".
 */
#[Layout('layouts.app')]
#[Title('Unit Barang')]
class Unit extends Component
{
    use WithPagination;

    #[Url]
    public ?int $jenisId = null;

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    /** @var list<int> */
    public array $dipilih = [];

    public function mount(): void
    {
        $this->authorize('barang.lihat');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['jenisId', 'status', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Pilih semua unit hasil filter di semua halaman (dibatasi batas cetak label).
     */
    public function pilihSemua(): void
    {
        $this->dipilih = array_values($this->queryUnit()->limit(LabelBarangPdfController::MAKS_LABEL)->get(['id'])->map(fn (UnitBarang $unit): int => $unit->id)->all());
    }

    public function batalPilih(): void
    {
        $this->dipilih = [];
    }

    /**
     * @return Builder<UnitBarang>
     */
    private function queryUnit(): Builder
    {
        return UnitBarang::query()
            ->when($this->jenisId, fn (Builder $q) => $q->where('jenis_barang_id', $this->jenisId))
            ->when(StatusUnitBarang::tryFrom($this->status), fn (Builder $q, StatusUnitBarang $status) => $q->where('status', $status))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('kode', 'like', '%'.strtoupper(trim($this->search)).'%')
                ->orWhere('serial_number', 'like', '%'.trim($this->search).'%')))
            ->orderBy('kode');
    }

    public function render(): View
    {
        $units = $this->queryUnit()->with(['jenisBarang', 'kondisi'])->paginate(50);

        return view('livewire.barang.unit', [
            'units' => $units,
            'jenisList' => JenisBarang::query()->where('dilacak_per_unit', true)->orderBy('nama')->get(),
            'statuses' => StatusUnitBarang::cases(),
        ]);
    }
}
