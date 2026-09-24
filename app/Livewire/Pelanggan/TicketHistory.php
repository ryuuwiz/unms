<?php

namespace App\Livewire\Pelanggan;

use App\Enums\Ticket\StatusTicket;
use App\Models\Pelanggan;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TicketHistory extends Component
{
    use WithPagination;

    public Pelanggan $pelanggan;

    #[Url(as: 'ticket_status')]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Ticket::class);

        /** @var LengthAwarePaginator<Ticket> $tickets */
        $tickets = $this->pelanggan->tickets()
            ->with('pic')
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest('id')
            ->paginate(10);

        return view('livewire.pelanggan.ticket-history', [
            'tickets' => $tickets,
            'statuses' => StatusTicket::cases(),
        ]);
    }
}
