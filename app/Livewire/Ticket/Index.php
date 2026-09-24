<?php

namespace App\Livewire\Ticket;

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Models\Ticket;
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
    public string $jenis = '';

    #[Url]
    public string $prioritas = '';

    #[Url]
    public string $divisi = '';

    #[Url]
    public string $tab = 'semua';

    /** 'prioritas' (overdue -> prioritas -> SLA) atau 'terbaru'. */
    #[Url]
    public string $urut = 'prioritas';

    /**
     * Buka di "Tiket Saya" bagi user yang masih memegang tiket terbuka sebagai PIC (Teknisi), kecuali
     * URL sudah menyebut tab.
     */
    public function mount(): void
    {
        $user = auth('web')->user();

        if ($user && ! request()->has('tab') && Ticket::query()->where('pic_id', $user->id)
            ->whereNotIn('status', [StatusTicket::Selesai->value, StatusTicket::Batal->value])->exists()) {
            $this->tab = 'saya';
        }
    }

    public function updatedUrut(): void
    {
        $this->resetPage();
    }

    public function resetFilter(): void
    {
        $this->reset(['search', 'jenis', 'prioritas', 'divisi']);
        $this->resetPage();
    }

    public function updatedSearch(): void
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

        $user = auth('web')->user();
        abort_unless($user !== null, 403);

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
            ])
            ->terlihatOleh($user);

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
            ->when($this->jenis, fn (Builder $q) => $q->where('jenis', $this->jenis))
            ->when($this->prioritas, fn (Builder $q) => $q->where('prioritas', $this->prioritas))
            ->when($this->divisi, fn (Builder $q) => $q->whereHas('divisis', function (Builder $dq) {
                $dq->where('ticket_divisi.divisi', $this->divisi);
            }));

        $tickets = ($this->urut === 'terbaru' ? $query->orderByDesc('id') : $query->urutkanPrioritas())->paginate(15);

        return view('livewire.ticket.index', [
            'tickets' => $tickets,
            'totalAktif' => $totalAktif,
            'menungguPic' => $menungguPic,
            'dalamProses' => $dalamProses,
            'overdueCount' => $overdueCount,
            'jumlahFilterAktif' => count(array_filter([$this->jenis, $this->prioritas, $this->divisi])),
            'jenisList' => JenisTicket::cases(),
            'prioritasList' => PrioritasTicket::cases(),
            'divisiList' => DivisiTicket::cases(),
        ]);
    }
}
