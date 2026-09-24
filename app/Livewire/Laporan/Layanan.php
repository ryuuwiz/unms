<?php

namespace App\Livewire\Laporan;

use App\Enums\StatusLayanan;
use App\Exports\LaporanLayananExport;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('layouts.app')]
#[Title('Laporan Data Layanan')]
class Layanan extends Component
{
    #[Url]
    public ?int $paketLayananId = null;

    #[Url]
    public string $status = '';

    #[Url]
    public bool $hanyaExpired = false;

    public function mount(): void
    {
        $this->authorize('viewAny', LayananPelanggan::class);
    }

    public function exportExcel(): BinaryFileResponse
    {
        $this->authorize('laporan.ekspor');

        $nama = $this->hanyaExpired ? 'Client-Expired' : 'Data-Layanan';

        return Excel::download($this->export(), "Laporan-{$nama}-".Carbon::now()->format('YmdHis').'.xlsx');
    }

    protected function export(): LaporanLayananExport
    {
        return new LaporanLayananExport(
            paketLayananId: $this->paketLayananId ?: null,
            status: StatusLayanan::tryFrom($this->status)?->value,
            hanyaExpired: $this->hanyaExpired,
        );
    }

    public function render(): View
    {
        $query = $this->export()->query();

        return view('livewire.laporan.layanan', [
            'total' => (clone $query)->count(),
            'layanans' => $query->limit(50)->get(),
            'pakets' => PaketLayanan::query()->orderBy('nama_paket')->get(['id', 'nama_paket']),
            'statuses' => StatusLayanan::cases(),
        ]);
    }
}
