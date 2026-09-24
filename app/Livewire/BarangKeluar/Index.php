<?php

namespace App\Livewire\BarangKeluar;

use App\Exports\BarangKeluarExport;
use App\Models\BarangKeluar;
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
#[Title('Barang Keluar')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $startDate = '';

    #[Url]
    public string $endDate = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStartDate(): void
    {
        $this->resetPage();
    }

    public function updatingEndDate(): void
    {
        $this->resetPage();
    }

    public function exportExcel(): BinaryFileResponse
    {
        $this->authorize('viewAny', BarangKeluar::class);

        $fileName = 'Barang-Keluar-'.Carbon::now()->format('YmdHis').'.xlsx';

        return Excel::download(
            new BarangKeluarExport(
                search: $this->search ?: null,
                startDate: $this->startDate ?: null,
                endDate: $this->endDate ?: null,
            ),
            $fileName
        );
    }

    public function render(): View
    {
        $this->authorize('viewAny', BarangKeluar::class);

        $items = BarangKeluar::query()
            ->with(['barang', 'dicatatOleh'])
            ->when($this->search, fn ($q) => $q->whereHas('barang', fn ($bq) => $bq->search($this->search)))
            ->when($this->startDate, fn ($q) => $q->whereDate('tanggal', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('tanggal', '<=', $this->endDate))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.barang-keluar.index', [
            'items' => $items,
        ]);
    }
}
