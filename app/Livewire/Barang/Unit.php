<?php

namespace App\Livewire\Barang;

use App\Enums\Barang\StatusUnitBarang;
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

    public function render(): View
    {
        $units = UnitBarang::query()
            ->with(['jenisBarang', 'kondisi'])
            ->when($this->jenisId, fn (Builder $q) => $q->where('jenis_barang_id', $this->jenisId))
            ->when(StatusUnitBarang::tryFrom($this->status), fn (Builder $q, StatusUnitBarang $status) => $q->where('status', $status))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('kode', 'like', '%'.strtoupper(trim($this->search)).'%')
                ->orWhere('serial_number', 'like', '%'.trim($this->search).'%')))
            ->orderBy('kode')
            ->paginate(50);

        return view('livewire.barang.unit', [
            'units' => $units,
            'jenisList' => JenisBarang::query()->where('dilacak_per_unit', true)->orderBy('nama')->get(),
            'statuses' => StatusUnitBarang::cases(),
        ]);
    }
}
