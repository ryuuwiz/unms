<?php

namespace App\Livewire\Invoice;

use App\Enums\StatusInvoice;
use App\Models\Invoice;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Daftar Invoice')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public ?int $deletingId = null;

    public string $keteranganHapus = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->keteranganHapus = '';
    }

    public function deleteInvoice(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $invoice = Invoice::findOrFail($this->deletingId);
        $this->authorize('delete', $invoice);

        if ($invoice->isLunas()) {
            Flux::toast(variant: 'danger', text: 'Invoice yang sudah lunas tidak dapat dihapus.');
            $this->deletingId = null;

            return;
        }

        if ($invoice->isDigabung()) {
            Flux::toast(variant: 'danger', text: 'Invoice yang sudah digabung tidak dapat dibatalkan; batalkan invoice penggabungnya.');
            $this->deletingId = null;

            return;
        }

        // Tunggakan yang diserap invoice ini kembali menjadi invoice terbuka sendiri.
        $invoice->invoiceDigabung->each->update([
            'status' => StatusInvoice::Kadaluarsa,
            'digabung_ke_invoice_id' => null,
        ]);

        $invoice->update([
            'status' => StatusInvoice::Dibatalkan,
            'dihapus_oleh' => auth()->id(),
            'keterangan_hapus' => $this->keteranganHapus ?: 'Dibatalkan oleh admin',
        ]);

        $invoice->delete();
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: "Invoice {$invoice->no_invoice} berhasil dibatalkan/dihapus.");
    }

    public function render(): View
    {
        $this->authorize('viewAny', Invoice::class);

        /** @var LengthAwarePaginator<Invoice> $invoices */
        $invoices = Invoice::query()
            ->with(['pelanggan', 'layananPelanggan.paketLayanan', 'promo'])
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->status, fn ($q) => $this->status === 'belum_dibayar'
                ? $q->whereIn('status', StatusInvoice::terbuka())
                : $q->where('status', $this->status))
            ->orderByDesc('tanggal_terbit')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.invoice.index', [
            'invoices' => $invoices,
            'statuses' => StatusInvoice::cases(),
        ]);
    }
}
