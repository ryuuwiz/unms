<?php

namespace App\Livewire\Laporan;

use App\Enums\StatusInvoice;
use App\Exports\LaporanBillingExport;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('layouts.app')]
#[Title('Laporan Billing & Penagihan')]
class Billing extends Component
{
    #[Url]
    public string $startDate = '';

    #[Url]
    public string $endDate = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);

        if (empty($this->startDate)) {
            $this->startDate = Carbon::now()->startOfMonth()->toDateString();
        }
        if (empty($this->endDate)) {
            $this->endDate = Carbon::now()->endOfMonth()->toDateString();
        }
    }

    public function exportExcel(): BinaryFileResponse
    {
        $fileName = 'Laporan-Billing-'.Carbon::now()->format('YmdHis').'.xlsx';

        return Excel::download(
            new LaporanBillingExport(
                status: $this->status ?: null,
                startDate: $this->startDate ?: null,
                endDate: $this->endDate ?: null
            ),
            $fileName
        );
    }

    public function render(): View
    {
        $this->authorize('viewAny', Invoice::class);

        $baseQuery = Invoice::query()
            ->when($this->startDate, fn ($q) => $q->whereDate('tanggal_terbit', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('tanggal_terbit', '<=', $this->endDate));

        $totalTagihan = (float) (clone $baseQuery)->sum('jumlah_setelah_promo');
        $totalLunas = (float) (clone $baseQuery)->where('status', StatusInvoice::Lunas)->sum('jumlah_setelah_promo');
        $totalPiutang = (float) (clone $baseQuery)->where('status', StatusInvoice::MenungguPembayaran)->sum('jumlah_setelah_promo');

        $countTotal = (clone $baseQuery)->count();
        $countLunas = (clone $baseQuery)->where('status', StatusInvoice::Lunas)->count();
        $countMenunggu = (clone $baseQuery)->where('status', StatusInvoice::MenungguPembayaran)->count();
        $countKadaluarsa = (clone $baseQuery)->where('status', StatusInvoice::Kadaluarsa)->count();

        $invoices = (clone $baseQuery)
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->with(['pelanggan', 'layananPelanggan.paketLayanan', 'promo'])
            ->orderByDesc('tanggal_terbit')
            ->limit(50)
            ->get();

        return view('livewire.laporan.billing', [
            'totalTagihan' => $totalTagihan,
            'totalLunas' => $totalLunas,
            'totalPiutang' => $totalPiutang,
            'countTotal' => $countTotal,
            'countLunas' => $countLunas,
            'countMenunggu' => $countMenunggu,
            'countKadaluarsa' => $countKadaluarsa,
            'invoices' => $invoices,
            'statuses' => StatusInvoice::cases(),
        ]);
    }
}
