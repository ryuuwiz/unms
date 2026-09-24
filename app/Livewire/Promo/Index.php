<?php

namespace App\Livewire\Promo;

use App\Models\Promo;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Katalog Promo & Diskon')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?int $deletingId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleStatus(int $id): void
    {
        $promo = Promo::findOrFail($id);
        $this->authorize('update', $promo);

        $promo->update(['aktif' => ! $promo->aktif]);
        Flux::toast(variant: 'success', text: "Status promo {$promo->kode_promo} berhasil diperbarui.");
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
    }

    public function deletePromo(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $promo = Promo::findOrFail($this->deletingId);
        $this->authorize('delete', $promo);

        if ($promo->penggunaans()->exists()) {
            Flux::toast(variant: 'danger', text: 'Promo yang sudah pernah digunakan pada invoice tidak dapat dihapus.');
            $this->deletingId = null;

            return;
        }

        $promo->delete();
        $this->deletingId = null;
        Flux::toast(variant: 'success', text: "Promo {$promo->kode_promo} berhasil dihapus.");
    }

    public function render(): View
    {
        $this->authorize('viewAny', Promo::class);

        $promos = Promo::query()
            ->when($this->search, function ($q) {
                $term = trim($this->search);
                $q->where('kode_promo', 'like', "%{$term}%")
                    ->orWhere('nama_promo', 'like', "%{$term}%");
            })
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.promo.index', [
            'promos' => $promos,
        ]);
    }
}
