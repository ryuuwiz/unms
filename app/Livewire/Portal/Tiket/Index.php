<?php

namespace App\Livewire\Portal\Tiket;

use App\Models\AkunPelanggan;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
#[Title('Tiket Saya')]
class Index extends Component
{
    use WithPagination;

    public function render(): View
    {
        /** @var AkunPelanggan $akun */
        $akun = Auth::guard('pelanggan')->user();

        /** @var LengthAwarePaginator<Ticket> $tickets */
        $tickets = Ticket::query()
            ->where('pelanggan_id', $akun->pelanggan_id)
            ->with(['layananPelanggan.paketLayanan'])
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.portal.tiket.index', [
            'tickets' => $tickets,
        ]);
    }
}
