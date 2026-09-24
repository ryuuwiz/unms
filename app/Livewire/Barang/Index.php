<?php

namespace App\Livewire\Barang;

use App\Exports\DataBarangExport;
use App\Models\Barang;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('layouts.app')]
#[Title('Daftar Barang')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $stokMin = '';

    #[Url]
    public string $stokMax = '';

    #[Url]
    public bool $stokMenipis = false;

    #[Url]
    public string $exportStartDate = '';

    #[Url]
    public string $exportEndDate = '';

    /**
     * Ambang batas "stok menipis" jika toggle diaktifkan tanpa mengisi rentang manual.
     */
    public const AMBANG_STOK_MENIPIS = 5;

    public ?int $deletingId = null;

    public function mount(): void
    {
        $this->exportStartDate = Carbon::now()->startOfMonth()->toDateString();
        $this->exportEndDate = Carbon::now()->endOfMonth()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStokMin(): void
    {
        $this->resetPage();
    }

    public function updatingStokMax(): void
    {
        $this->resetPage();
    }

    public function updatedStokMenipis(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
    }

    public function deleteBarang(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $barang = Barang::findOrFail($this->deletingId);
        $this->authorize('delete', $barang);

        $barang->delete();
        $this->deletingId = null;
        Flux::toast(variant: 'success', text: "Barang {$barang->kode_barang} berhasil dihapus.");
    }

    public function toggleStatus(int $id): void
    {
        $barang = Barang::findOrFail($id);
        $this->authorize('update', $barang);

        $barang->update(['is_active' => ! $barang->is_active]);

        $statusText = $barang->is_active ? 'diaktifkan' : 'dinonaktifkan';
        Flux::toast(variant: 'success', text: "Barang {$barang->kode_barang} berhasil {$statusText}.");
    }

    public function exportExcel(): BinaryFileResponse
    {
        $this->authorize('viewAny', Barang::class);

        $fileName = 'Data-Barang-'.Carbon::now()->format('YmdHis').'.xlsx';

        return Excel::download(
            new DataBarangExport(
                startDate: Carbon::parse($this->exportStartDate)->startOfDay(),
                endDate: Carbon::parse($this->exportEndDate)->endOfDay(),
            ),
            $fileName
        );
    }

    public function render(): View
    {
        $this->authorize('viewAny', Barang::class);

        $barangs = Barang::query()
            ->with(['jenisBarang', 'kondisiBarang', 'cabangBarang'])
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->stokMin !== '', fn ($q) => $q->where('stok', '>=', (int) $this->stokMin))
            ->when($this->stokMax !== '', fn ($q) => $q->where('stok', '<=', (int) $this->stokMax))
            ->when($this->stokMenipis, fn ($q) => $q->where('stok', '<', self::AMBANG_STOK_MENIPIS))
            ->orderBy('nama_barang')
            ->paginate(15);

        return view('livewire.barang.index', [
            'barangs' => $barangs,
        ]);
    }
}
