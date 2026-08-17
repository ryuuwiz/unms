<?php

namespace App\Livewire\Pembayaran;

use App\Enums\MetodePembayaran;
use App\Models\Pembayaran;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Riwayat Pembayaran')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $metode = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMetode(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Pembayaran::class);

        /** @var LengthAwarePaginator<Pembayaran> $pembayarans */
        $pembayarans = Pembayaran::query()
            ->with(['invoice.pelanggan', 'invoice.layananPelanggan.paketLayanan', 'dicatatOleh'])
            ->when($this->search, function ($q) {
                $term = trim($this->search);
                $q->where('referensi_transaksi', 'like', "%{$term}%")
                    ->orWhereHas('invoice', function ($invQ) use ($term) {
                        $invQ->where('no_invoice', 'like', "%{$term}%")
                            ->orWhereHas('pelanggan', fn ($pelQ) => $pelQ->where('nama_depan', 'like', "%{$term}%")->orWhere('nama_belakang', 'like', "%{$term}%")->orWhere('no_reg', 'like', "%{$term}%"));
                    });
            })
            ->when($this->metode, fn ($q) => $q->where('metode', $this->metode))
            ->orderByDesc('dibayar_pada')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.pembayaran.index', [
            'pembayarans' => $pembayarans,
            'metodes' => MetodePembayaran::cases(),
        ]);
    }
}
