<?php

namespace App\Livewire\Invoice;

use App\Actions\Invoice\BatalkanInvoiceLunasAction;
use App\Enums\StatusInvoice;
use App\Models\Invoice;
use Flux\Flux;
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
            $this->batalkanInvoiceLunas($invoice);

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
            'dihapus_oleh' => auth('web')->user()?->id,
            'keterangan_hapus' => $this->keteranganHapus ?: 'Dibatalkan oleh admin',
        ]);

        $invoice->delete();
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: "Invoice {$invoice->no_invoice} berhasil dibatalkan/dihapus.");
    }

    protected function batalkanInvoiceLunas(Invoice $invoice): void
    {
        $this->authorize('voidLunas', $invoice);

        $this->validate(
            ['keteranganHapus' => ['required', 'string', 'min:5', 'max:500']],
            ['keteranganHapus.required' => 'Alasan pembatalan invoice lunas wajib diisi.', 'keteranganHapus.min' => 'Alasan minimal 5 karakter.'],
        );

        try {
            app(BatalkanInvoiceLunasAction::class)->execute($invoice, auth('web')->user(), $this->keteranganHapus);
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage(), duration: 10000);
            $this->deletingId = null;

            return;
        }

        $this->deletingId = null;

        Flux::toast(variant: 'success', text: "Invoice lunas {$invoice->no_invoice} dibatalkan; pembayaran dan masa aktif layanan dikembalikan.");
    }

    public function render(): View
    {
        $this->authorize('viewAny', Invoice::class);

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
            'deletingLunas' => $this->deletingId
                && Invoice::whereKey($this->deletingId)->where('status', StatusInvoice::Lunas)->exists(),
        ]);
    }
}
