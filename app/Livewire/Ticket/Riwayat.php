<?php

namespace App\Livewire\Ticket;

use App\Enums\Ticket\StatusTicket;
use App\Models\Ticket;
use App\Models\TicketHistori;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Riwayat Tiket: entri Histori Tiket lintas tiket -- lihat CONTEXT.md "Riwayat Tiket".
 */
#[Layout('layouts.app')]
#[Title('Riwayat Tiket')]
class Riwayat extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $dari = '';

    #[Url]
    public string $sampai = '';

    #[Url]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Ticket::class);

        $user = auth('web')->user();
        abort_unless($user !== null, 403);

        $histori = TicketHistori::query()
            ->with(['ticket.pelanggan', 'olehPengguna'])
            ->whereHas('ticket', function (Builder $q) use ($user) {
                $q->terlihatOleh($user)
                    ->when($this->search, fn (Builder $tq) => $tq->search($this->search));
            })
            ->when(StatusTicket::tryFrom($this->status), fn (Builder $q, StatusTicket $status) => $q->where('status_baru', $status))
            ->when($this->dari, fn (Builder $q) => $q->whereDate('created_at', '>=', $this->dari))
            ->when($this->sampai, fn (Builder $q) => $q->whereDate('created_at', '<=', $this->sampai))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.ticket.riwayat', [
            'histori' => $histori,
            'statuses' => StatusTicket::cases(),
        ]);
    }
}
