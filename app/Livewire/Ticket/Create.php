<?php

namespace App\Livewire\Ticket;

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Ticket\SumberTicket;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use App\Notifications\TicketDiassignNotification;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Buat Tiket Baru')]
class Create extends Component
{
    public string $jenis = 'pemasangan';

    public ?int $pelanggan_id = null;

    public ?int $layanan_pelanggan_id = null;

    public string $prioritas = 'sedang';

    public string $divisi = 'teknisi';

    public ?int $pic_id = null;

    public string $dijadwalkan_pada = '';

    public string $deskripsi = '';

    public function mount(): void
    {
        $this->authorize('create', Ticket::class);

        // Preselect jenis from query string if available
        if (request()->has('jenis') && JenisTicket::tryFrom(request()->query('jenis'))) {
            $this->jenis = request()->query('jenis');
            $this->autoSetDivisi();
        }

        // Preselect pelanggan from query string if available
        if (request()->has('pelanggan_id')) {
            $this->pelanggan_id = (int) request()->query('pelanggan_id');
        }

        // Preselect layanan from query string if available
        if (request()->has('layanan_id')) {
            $this->layanan_pelanggan_id = (int) request()->query('layanan_id');
        }
    }

    public function updatedJenis(): void
    {
        $this->autoSetDivisi();
    }

    public function updatedPelangganId(): void
    {
        $this->layanan_pelanggan_id = null;
    }

    protected function autoSetDivisi(): void
    {
        $this->divisi = match ($this->jenis) {
            JenisTicket::Gangguan->value => DivisiTicket::Noc->value,
            JenisTicket::Pemasangan->value => DivisiTicket::Teknisi->value,
            JenisTicket::Pencabutan->value => DivisiTicket::Teknisi->value,
            JenisTicket::PindahAlamat->value => DivisiTicket::Teknisi->value,
            default => DivisiTicket::Teknisi->value,
        };
    }

    public function save(): void
    {
        $this->authorize('create', Ticket::class);

        $this->validate([
            'jenis' => ['required', Rule::enum(JenisTicket::class)],
            'pelanggan_id' => ['required', 'integer', 'exists:pelanggan,id'],
            'layanan_pelanggan_id' => ['nullable', 'integer', 'exists:layanan_pelanggan,id'],
            'prioritas' => ['required', Rule::enum(PrioritasTicket::class)],
            'divisi' => ['required', Rule::enum(DivisiTicket::class)],
            'pic_id' => ['nullable', 'integer', 'exists:users,id'],
            'dijadwalkan_pada' => ['nullable', 'date'],
            'deskripsi' => ['required', 'string', 'min:5', 'max:3000'],
        ], [
            'pelanggan_id.required' => 'Pelanggan / Prospek wajib dipilih.',
            'deskripsi.required' => 'Deskripsi tiket wajib diisi.',
            'deskripsi.min' => 'Deskripsi tiket minimal 5 karakter.',
        ]);

        $ticket = DB::transaction(function () {
            $ticket = Ticket::create([
                'jenis' => $this->jenis,
                'pelanggan_id' => $this->pelanggan_id,
                'layanan_pelanggan_id' => $this->layanan_pelanggan_id ?: null,
                'prioritas' => $this->prioritas,
                'divisi' => $this->divisi,
                'pic_id' => $this->pic_id ?: null,
                'status' => StatusTicket::Baru,
                'sumber' => SumberTicket::Manual,
                'deskripsi' => trim($this->deskripsi),
                'dijadwalkan_pada' => $this->dijadwalkan_pada ? Carbon::parse($this->dijadwalkan_pada) : null,
                'dibuat_oleh' => auth()->id(),
            ]);

            TicketHistori::create([
                'ticket_id' => $ticket->id,
                'status_lama' => null,
                'status_baru' => StatusTicket::Baru,
                'catatan' => 'Tiket baru dibuat.'.($this->pic_id ? ' PIC ditugaskan pada saat pembuatan.' : ''),
                'oleh_pengguna_id' => auth()->id(),
            ]);

            return $ticket;
        });

        // Kirim notifikasi ke PIC jika ditugaskan
        if ($ticket->pic_id && $ticket->pic_id !== auth()->id() && $ticket->pic) {
            $ticket->pic->notify(new TicketDiassignNotification(
                ticket: $ticket,
                assignedBy: auth()->user(),
            ));
        }

        Flux::toast(variant: 'success', text: "Tiket {$ticket->nomor_ticket} berhasil dibuat.");

        $this->redirectRoute('ticket.show', $ticket, navigate: true);
    }

    public function render(): View
    {
        /** @var Collection<int, Pelanggan> $pelanggans */
        $pelanggans = Pelanggan::query()
            ->with('perumahan')
            ->orderBy('nama_depan')
            ->get();

        /** @var Collection<int, LayananPelanggan> $layanans */
        $layanans = $this->pelanggan_id
            ? LayananPelanggan::query()
                ->with(['paketLayanan', 'router'])
                ->where('pelanggan_id', $this->pelanggan_id)
                ->get()
            : collect();

        /** @var Collection<int, User> $staffList */
        $staffList = User::query()
            ->active()
            ->orderBy('name')
            ->get();

        $selectedPelanggan = $this->pelanggan_id
            ? Pelanggan::with(['perumahan.kelurahan.kecamatan.kota', 'dibuatOleh'])->find($this->pelanggan_id)
            : null;

        $prioritasEnum = PrioritasTicket::tryFrom($this->prioritas);

        return view('livewire.ticket.create', [
            'pelanggans' => $pelanggans,
            'layanans' => $layanans,
            'staffList' => $staffList,
            'selectedPelanggan' => $selectedPelanggan,
            'prioritasEnum' => $prioritasEnum,
            'jenisList' => JenisTicket::cases(),
            'prioritasList' => PrioritasTicket::cases(),
            'divisiList' => DivisiTicket::cases(),
        ]);
    }
}
