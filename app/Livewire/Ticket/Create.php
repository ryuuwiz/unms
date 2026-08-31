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
use App\Services\Whatsapp\WhatsappService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Buat Tiket Baru')]
class Create extends Component
{
    use WithFileUploads;

    public string $jenis = 'pemasangan';

    public ?int $pelanggan_id = null;

    public ?int $layanan_pelanggan_id = null;

    public string $prioritas = 'sedang';

    /** @var array<int, string> */
    public array $divisis = [];

    public ?int $pic_id = null;

    public string $dijadwalkan_pada = '';

    public string $deskripsi = '';

    /** @var mixed */
    public $fotoKendala = null;

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
        $this->divisis = match ($this->jenis) {
            JenisTicket::Gangguan->value => [DivisiTicket::Noc->value, DivisiTicket::Teknisi->value],
            JenisTicket::Pemasangan->value => [DivisiTicket::Teknisi->value],
            JenisTicket::Pencabutan->value => [DivisiTicket::Teknisi->value, DivisiTicket::CustomerService->value],
            JenisTicket::PindahAlamat->value => [DivisiTicket::Noc->value, DivisiTicket::Teknisi->value],
            default => [DivisiTicket::Teknisi->value],
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
            'divisis' => ['required', 'array', 'min:1'],
            'divisis.*' => ['required', Rule::enum(DivisiTicket::class)],
            'pic_id' => ['nullable', 'integer', 'exists:users,id'],
            'dijadwalkan_pada' => ['nullable', 'date'],
            'deskripsi' => ['required', 'string', 'min:5', 'max:3000'],
            'fotoKendala' => ['nullable', 'image', 'max:5120'],
        ], [
            'pelanggan_id.required' => 'Pelanggan / Prospek wajib dipilih.',
            'divisis.required' => 'Minimal satu divisi harus dipilih.',
            'divisis.min' => 'Minimal satu divisi harus dipilih.',
            'deskripsi.required' => 'Deskripsi tiket wajib diisi.',
            'deskripsi.min' => 'Deskripsi tiket minimal 5 karakter.',
            'fotoKendala.image' => 'Lampiran foto harus berupa format gambar (jpg, png, webp).',
            'fotoKendala.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        $authUserId = Auth::id();
        /** @var User $authUser */
        $authUser = Auth::user();

        $ticket = DB::transaction(function () use ($authUserId) {
            $ticket = Ticket::create([
                'jenis' => $this->jenis,
                'pelanggan_id' => $this->pelanggan_id,
                'layanan_pelanggan_id' => $this->layanan_pelanggan_id ?: null,
                'prioritas' => $this->prioritas,
                'pic_id' => $this->pic_id ?: null,
                'status' => StatusTicket::Baru,
                'sumber' => SumberTicket::Manual,
                'deskripsi' => trim($this->deskripsi),
                'dijadwalkan_pada' => $this->dijadwalkan_pada ? Carbon::parse($this->dijadwalkan_pada) : null,
                'dibuat_oleh' => $authUserId,
            ]);

            // Sync divisi ke pivot
            $divisiRows = array_map(fn (string $d) => ['ticket_id' => $ticket->id, 'divisi' => $d], $this->divisis);
            DB::table('ticket_divisi')->insert($divisiRows);

            TicketHistori::create([
                'ticket_id' => $ticket->id,
                'status_lama' => null,
                'status_baru' => StatusTicket::Baru,
                'catatan' => 'Tiket baru dibuat.'.($this->pic_id ? ' PIC ditugaskan pada saat pembuatan.' : ''),
                'oleh_pengguna_id' => $authUserId,
            ]);

            return $ticket;
        });

        // Simpan lampiran media foto jika diunggah
        if ($this->fotoKendala) {
            try {
                $ticket->addMedia($this->fotoKendala->getRealPath())
                    ->usingFileName($this->fotoKendala->getClientOriginalName())
                    ->toMediaCollection('foto_kendala');
            } catch (\Throwable $e) {
                Log::error('Gagal menyimpan foto kendala tiket: '.$e->getMessage());
            }
        }

        // Kirim notifikasi ke PIC jika ditugaskan
        if ($ticket->pic_id && $ticket->pic_id !== $authUserId && $ticket->pic) {
            $ticket->pic->notify(new TicketDiassignNotification(
                ticket: $ticket,
                assignedBy: $authUser,
            ));

            if (! empty($ticket->pic->phone)) {
                try {
                    /** @var WhatsappService $wablasService */
                    $wablasService = app(WhatsappService::class);
                    $params = $wablasService->buildTicketParams($ticket);
                    $wablasService->antrikanPesan(
                        noHp: $ticket->pic->phone,
                        kodeTemplate: 'tiket_penugasan_teknisi',
                        params: $params,
                        referensi: $ticket,
                        jenis: 'tiket_assign_pic_create'
                    );
                } catch (\Throwable $e) {
                    Log::error('Gagal kirim WA penugasan teknisi: '.$e->getMessage());
                }
            }
        }

        // Kirim konfirmasi WhatsApp ke Pelanggan
        $pelanggan = $ticket->pelanggan;
        if ($pelanggan && ! empty($pelanggan->no_hp)) {
            try {
                /** @var WhatsappService $wablasService */
                $wablasService = app(WhatsappService::class);
                $params = $wablasService->buildTicketParams($ticket);
                $wablasService->antrikanPesan(
                    noHp: $pelanggan->no_hp,
                    kodeTemplate: 'tiket_dibuat',
                    params: $params,
                    referensi: $ticket,
                    jenis: 'tiket_dibuat'
                );
            } catch (\Throwable $e) {
                Log::error('Gagal kirim WA tiket dibuat ke pelanggan: '.$e->getMessage());
            }
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
