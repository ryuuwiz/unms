<?php

namespace App\Livewire\Pembayaran\TransaksiGateway;

use App\Enums\GatewayChannel;
use App\Enums\StatusTransaksiGateway;
use App\Models\Pembayaran;
use App\Models\TransaksiPaymentGateway;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Daftar Transaksi Payment Gateway')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $channel = '';

    #[Url]
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedChannel(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Pembayaran::class);

        $query = TransaksiPaymentGateway::with(['invoice.pelanggan'])
            ->latest('id');

        if (! empty($this->search)) {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('external_id', 'like', "%{$term}%")
                    ->orWhere('nomor_pembayaran', 'like', "%{$term}%")
                    ->orWhereHas('invoice', function ($invQ) use ($term) {
                        $invQ->where('no_invoice', 'like', "%{$term}%")
                            ->orWhereHas('pelanggan', function ($custQ) use ($term) {
                                $custQ->where('nama_depan', 'like', "%{$term}%")
                                    ->orWhere('nama_belakang', 'like', "%{$term}%")
                                    ->orWhere('no_reg', 'like', "%{$term}%");
                            });
                    });
            });
        }

        if (! empty($this->channel)) {
            $query->where('channel', $this->channel);
        }

        if (! empty($this->status)) {
            $query->where('status', $this->status);
        }

        $transaksis = $query->paginate(15);

        return view('livewire.pembayaran.transaksi-gateway.index', [
            'transaksis' => $transaksis,
            'channels' => GatewayChannel::cases(),
            'statuses' => StatusTransaksiGateway::cases(),
        ]);
    }
}
