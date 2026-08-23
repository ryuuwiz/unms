<?php

namespace App\Livewire\LayananPelanggan;

use App\Actions\LayananPelanggan\UbahStatusLayananAction;
use App\Enums\StatusLayanan;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Models\LayananPelanggan;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Layanan Pelanggan')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public ?int $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $layanan = LayananPelanggan::findOrFail($id);
        $this->authorize('delete', $layanan);
        $this->deletingId = $id;
    }

    public function deleteLayanan(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $layanan = LayananPelanggan::findOrFail($this->deletingId);
        $this->authorize('delete', $layanan);

        $layanan->delete();
        $this->deletingId = null;

        Flux::toast(variant: 'success', text: 'Layanan berhasil dihapus.');
    }

    public function provisionLayanan(int $id): void
    {
        $layanan = LayananPelanggan::findOrFail($id);
        $this->authorize('update', $layanan);

        ProvisionPppoeAccountJob::dispatch($layanan);

        Flux::toast(variant: 'success', text: "Job provisi PPPoE untuk {$layanan->ppp_username} telah dikirim ke antrean.");
    }

    public function toggleIsolir(int $id, UbahStatusLayananAction $ubahStatusAction): void
    {
        $layanan = LayananPelanggan::findOrFail($id);
        $this->authorize('update', $layanan);

        $statusBaru = $layanan->status === StatusLayanan::Suspend
            ? StatusLayanan::Aktif
            : StatusLayanan::Suspend;

        $catatan = $statusBaru === StatusLayanan::Suspend
            ? 'Isolir manual oleh admin'
            : 'Un-isolir / aktivasi manual oleh admin';

        $ubahStatusAction->execute(
            layanan: $layanan,
            statusBaru: $statusBaru,
            actor: auth()->user(),
            catatan: $catatan
        );

        Flux::toast(
            variant: 'success',
            text: "Status layanan {$layanan->ppp_username} berhasil diubah menjadi {$statusBaru->label()}."
        );
    }

    public function render(): View
    {
        $layanans = LayananPelanggan::query()
            ->with(['pelanggan', 'paketLayanan', 'router'])
            ->when($this->search, fn ($q) => $q->whereHas('pelanggan', fn ($pq) => $pq->search($this->search)))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(15);

        return view('livewire.layanan-pelanggan.index', [
            'layanans' => $layanans,
            'statuses' => StatusLayanan::cases(),
        ]);
    }
}
