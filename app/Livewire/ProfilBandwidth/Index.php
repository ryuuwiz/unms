<?php

namespace App\Livewire\ProfilBandwidth;

use App\Models\ProfilBandwidth;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Profil Bandwidth')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
    }

    public function deleteProfilBandwidth(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $profil = ProfilBandwidth::findOrFail($this->deletingId);
        $this->authorize('delete', $profil);

        if ($profil->pakets()->exists()) {
            Flux::toast(variant: 'danger', text: 'Profil bandwidth masih digunakan oleh paket layanan dan tidak dapat dihapus.');
            $this->deletingId = null;

            return;
        }

        $profil->delete();
        $this->deletingId = null;
        Flux::toast(variant: 'success', text: 'Profil bandwidth berhasil dihapus.');
    }

    public function render(): View
    {
        $profils = ProfilBandwidth::query()
            ->when($this->search, fn ($q) => $q->where('nama_bandwidth', 'like', "%{$this->search}%"))
            ->withCount('pakets')
            ->orderBy('max_limit_tx')
            ->paginate(15);

        return view('livewire.profil-bandwidth.index', ['profils' => $profils]);
    }
}
