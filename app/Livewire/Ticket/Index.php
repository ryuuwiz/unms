<?php

namespace App\Livewire\Ticket;

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Daftar Tiket')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $jenis = '';

    #[Url]
    public string $prioritas = '';

    #[Url]
    public string $divisi = '';

    #[Url]
    public string $tab = 'semua';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedJenis(): void
    {
        $this->resetPage();
    }

    public function updatedPrioritas(): void
    {
        $this->resetPage();
    }

    public function updatedDivisi(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tabName): void
    {
        $this->tab = $tabName;
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Ticket::class);

        $user = auth()->user();

        // Base query with eager loading for data_unms.md specifications
        $query = Ticket::query()
            ->with([
                'pelanggan.perumahan.kelurahan.kecamatan.kota',
                'layananPelanggan.paketLayanan',
                'layananPelanggan.router',
                'layananPelanggan.odpPort.odp',
                'pic',
                'dibuatOleh',
                'divisis',
            ]);

        // Role-based scoping
        if ($user->hasRole('teknisi')) {
            $query->where('pic_id', $user->id);
        } elseif ($user->hasRole('sales')) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('dibuat_oleh', $user->id)
                    ->orWhereHas('pelanggan', fn (Builder $cq) => $cq->where('dibuat_oleh', $user->id));
            });
        }

        // Metrik cards
        $metricsQuery = clone $query;
        $totalAktif = (clone $metricsQuery)->whereNotIn('status', [StatusTicket::Selesai->value, StatusTicket::Batal->value])->count();
        $menungguPic = (clone $metricsQuery)->whereNotIn('status', [StatusTicket::Selesai->value, StatusTicket::Batal->value])->whereNull('pic_id')->count();
        $dalamProses = (clone $metricsQuery)->where('status', StatusTicket::Diproses->value)->count();
        $overdueCount = (clone $metricsQuery)->overdue()->count();

        // Tab filters
        match ($this->tab) {
            'saya' => $query->where('pic_id', $user->id),
            'baru' => $query->where('status', StatusTicket::Baru->value),
            'diproses' => $query->where('status', StatusTicket::Diproses->value),
            'menunggu_konfirmasi' => $query->where('status', StatusTicket::MenungguKonfirmasi->value),
            'selesai' => $query->where('status', StatusTicket::Selesai->value),
            'batal' => $query->where('status', StatusTicket::Batal->value),
            'overdue' => $query->overdue(),
            default => null,
        };

        // Standard filters
        $query->when($this->search, fn (Builder $q) => $q->search($this->search))
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->jenis, fn (Builder $q) => $q->where('jenis', $this->jenis))
            ->when($this->prioritas, fn (Builder $q) => $q->where('prioritas', $this->prioritas))
            ->when($this->divisi, fn (Builder $q) => $q->whereHas('divisis', function (Builder $dq) {
                $dq->where('ticket_divisi.divisi', $this->divisi);
            }));

        /** @var LengthAwarePaginator<Ticket> $tickets */
        $tickets = $query->orderByDesc('id')->paginate(15);

        return view('livewire.ticket.index', [
            'tickets' => $tickets,
            'totalAktif' => $totalAktif,
            'menungguPic' => $menungguPic,
            'dalamProses' => $dalamProses,
            'overdueCount' => $overdueCount,
            'statuses' => StatusTicket::cases(),
            'jenisList' => JenisTicket::cases(),
            'prioritasList' => PrioritasTicket::cases(),
            'divisiList' => DivisiTicket::cases(),
        ]);
    }
}
