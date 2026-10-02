<?php

namespace App\Livewire\Pelanggan;

use App\Enums\StatusPelanggan;
use App\Models\Pelanggan;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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

    /**
     * ID yang akan dihapus. Locked: state buka/tutup modal disimpan terpisah di $showHapusModal,
     * karena flux:modal wire:model menulis true/false ke propertinya (true di-cast jadi ID 1).
     */
    #[Locked]
    public ?int $deletingCustomerId = null;

    public bool $showHapusModal = false;

    public string $konfirmasiHapus = '';

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
        $pelanggan = Pelanggan::findOrFail($id);
        $this->authorize('delete', $pelanggan);

        if ($this->tolakJikaMasihPunyaLayananBerjalan($pelanggan)) {
            return;
        }

        $this->deletingCustomerId = $id;
        $this->konfirmasiHapus = '';
        $this->resetErrorBag('konfirmasiHapus');
        $this->showHapusModal = true;
    }

    public function deleteCustomer(): void
    {
        if (! $this->deletingCustomerId) {
            return;
        }

        if (trim($this->konfirmasiHapus) !== 'HAPUS') {
            $this->addError('konfirmasiHapus', 'Ketik HAPUS untuk mengonfirmasi penghapusan.');

            return;
        }

        $pelanggan = Pelanggan::findOrFail($this->deletingCustomerId);

        $this->authorize('delete', $pelanggan);

        if ($this->tolakJikaMasihPunyaLayananBerjalan($pelanggan)) {
            $this->reset(['deletingCustomerId', 'showHapusModal', 'konfirmasiHapus']);

            return;
        }

        /** @var User $actor */
        $actor = Auth::user();
        // Activity logged via Spatie LogsActivity trait

        $pelanggan->delete();

        $this->reset(['deletingCustomerId', 'showHapusModal', 'konfirmasiHapus']);
        Flux::toast(variant: 'success', text: 'Data pelanggan berhasil dihapus.');
    }

    /**
     * Tampilkan toast penolakan dan kembalikan true bila Pelanggan masih punya layanan yang belum Berhenti.
     */
    private function tolakJikaMasihPunyaLayananBerjalan(Pelanggan $pelanggan): bool
    {
        if (! $pelanggan->masihPunyaLayananBerjalan()) {
            return false;
        }

        Flux::toast(variant: 'danger', text: 'Pelanggan masih punya Data Registrasi Billing yang belum Berhenti. Cabut lewat tiket Pencabutan atau hapus registrasinya lebih dulu.');

        return true;
    }

    public function toggleStatus(int $id): void
    {
        $pelanggan = Pelanggan::findOrFail($id);

        $this->authorize('update', $pelanggan);

        $oldStatus = $pelanggan->status;
        $newStatus = $pelanggan->status === StatusPelanggan::Aktif
            ? StatusPelanggan::Off
            : StatusPelanggan::Aktif;

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
