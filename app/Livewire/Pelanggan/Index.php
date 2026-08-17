<?php

namespace App\Livewire\Pelanggan;

use App\Enums\StatusPelanggan;
use App\Models\Pelanggan;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Pelanggan')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public string $filterScope = 'all'; // 'all' or 'my'

    public ?int $deletingCustomerId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterScope(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingCustomerId = $id;
    }

    public function deleteCustomer(): void
    {
        if (! $this->deletingCustomerId) {
            return;
        }

        $pelanggan = Pelanggan::findOrFail($this->deletingCustomerId);

        $this->authorize('delete', $pelanggan);

        /** @var User $actor */
        $actor = Auth::user();
        // Activity logged via Spatie LogsActivity trait

        $pelanggan->delete();

        $this->deletingCustomerId = null;
        Flux::toast(variant: 'success', text: 'Data pelanggan berhasil dihapus.');
    }

    public function toggleStatus(int $id): void
    {
        $pelanggan = Pelanggan::findOrFail($id);

        $this->authorize('update', $pelanggan);

        $oldStatus = $pelanggan->status;
        $newStatus = $pelanggan->status === StatusPelanggan::Active
            ? StatusPelanggan::Inactive
            : StatusPelanggan::Active;

        $pelanggan->update(['status' => $newStatus]);

        /** @var User $actor */
        $actor = Auth::user();
        // Activity logged via Spatie LogsActivity trait

        Flux::toast(variant: 'success', text: "Status pelanggan diubah menjadi {$newStatus->label()}.");
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $isSales = $user->hasRole('sales');

        $pelanggans = Pelanggan::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterScope === 'my', fn ($q) => $q->where('dibuat_oleh', $user->id))
            ->with('pembuat')
            ->latest()
            ->paginate(15);

        return view('livewire.pelanggan.index', [
            'pelanggans' => $pelanggans,
            'statuses' => StatusPelanggan::cases(),
            'isSales' => $isSales,
            'user' => $user,
        ]);
    }
}
