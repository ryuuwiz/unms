<?php

namespace App\Livewire\Laporan;

use App\Enums\StatusLayanan;
use App\Exports\DataPelangganExpiredExport;
use App\Exports\DataPelangganPerPaketExport;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('layouts.app')]
#[Title('Laporan Data Pelanggan')]
class DataPelanggan extends Component
{
    #[Url]
    public string $paketLayananId = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Pelanggan::class);
    }

    public function exportPerPaket(): BinaryFileResponse
    {
        $this->authorize('viewAny', Pelanggan::class);

        $fileName = 'Data-Pelanggan-Per-Paket-'.Carbon::now()->format('YmdHis').'.xlsx';

        return Excel::download(
            new DataPelangganPerPaketExport(
                paketLayananId: $this->paketLayananId !== '' ? (int) $this->paketLayananId : null,
                status: $this->status ?: null,
            ),
            $fileName
        );
    }

    public function exportExpired(): BinaryFileResponse
    {
        $this->authorize('viewAny', Pelanggan::class);

        $fileName = 'Data-Client-Expired-'.Carbon::now()->format('YmdHis').'.xlsx';

        return Excel::download(new DataPelangganExpiredExport, $fileName);
    }

    public function render(): View
    {
        $this->authorize('viewAny', Pelanggan::class);

        $baseQuery = LayananPelanggan::query()
            ->when($this->paketLayananId !== '', fn ($q) => $q->where('paket_layanan_id', $this->paketLayananId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status));

        $totalLayanan = (clone $baseQuery)->count();
        $totalExpired = (clone $baseQuery)
            ->whereIn('status', [StatusLayanan::Aktif, StatusLayanan::Suspend])
            ->expiredSebelum(Carbon::now())
            ->count();

        $layanans = (clone $baseQuery)
            ->with(['pelanggan', 'paketLayanan'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('livewire.laporan.data-pelanggan', [
            'layanans' => $layanans,
            'totalLayanan' => $totalLayanan,
            'totalExpired' => $totalExpired,
            'paketList' => PaketLayanan::aktif()->orderBy('nama_paket')->get(),
            'statuses' => StatusLayanan::cases(),
        ]);
    }
}
