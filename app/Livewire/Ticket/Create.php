<?php

namespace App\Livewire\Ticket;

use App\Enums\StatusOdpPort;
use App\Enums\StatusPelanggan;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Ticket\StatusUsulanOdp;
use App\Enums\Ticket\SumberTicket;
use App\Livewire\Concerns\HasSearchableOptions;
use App\Models\LayananPelanggan;
use App\Models\Odp;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\TicketPemasangan;
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
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Buat Tiket Baru')]
class Create extends Component
{
    use HasSearchableOptions, WithFileUploads;

    public string $jenis = 'pemasangan';

    public ?int $pelanggan_id = null;

    public ?int $layanan_pelanggan_id = null;

    /** Usulan ODP -- lihat CONTEXT.md "Usulan ODP". */
    public ?int $odp_usulan_id = null;

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
        $this->odp_usulan_id = null;
        $this->autoSetDivisi();
    }

    public function updatedPelangganId(): void
    {
        $this->layanan_pelanggan_id = null;
        $this->autoSetDivisi();
    }

    public function updatedLayananPelangganId(): void
    {
        $this->odp_usulan_id = null;
        $this->autoSetDivisi();
    }

    /**
     * @return array<string, array{model: class-string, query: \Closure, label: \Closure, cap?: int}>
     */
    protected function searchableFields(): array
    {
        return [
            'pelanggan_id' => [
                'model' => Pelanggan::class,
                'query' => fn() => Pelanggan::query()->with('perumahan'),
                'label' => fn(Pelanggan $p) => $p->labelSelector(),
                'cap' => 20,
            ],
        ];
    }

    protected function autoSetDivisi(): void
    {
        $this->divisis = match ($this->jenis) {
            JenisTicket::Gangguan->value => [DivisiTicket::Noc->value, DivisiTicket::Teknisi->value],
            // Pemasangan yang sudah merujuk layanan yang ada (alur baru) wajib keempat divisi:
            // masing-masing sign-off status-nya sendiri (lihat CONTEXT.md "Status Per-Divisi
            // Tiket") menggerakkan status tiket keseluruhan otomatis -- tidak boleh diubah staf,
            // lihat ticket/create.blade.php. Pemasangan tanpa layanan (alur lama, ticket_id
            // dituju dari Tambah Layanan nanti) tetap Teknisi saja seperti sebelumnya.
            JenisTicket::Pemasangan->value => $this->layanan_pelanggan_id
                ? array_map(fn($d) => $d->value, Ticket::DIVISI_WAJIB_PEMASANGAN)
                : [DivisiTicket::Teknisi->value],
            // NOC menghapus PPP Secret, Teknisi mencabut perangkat & melepas port ODP -- CONTEXT.md "Pencabutan".
            JenisTicket::Pencabutan->value => [DivisiTicket::Noc->value, DivisiTicket::Teknisi->value],
            JenisTicket::PindahAlamat->value => [DivisiTicket::Noc->value, DivisiTicket::Teknisi->value],
            default => [DivisiTicket::Teknisi->value],
        };
    }

    public function save(): void
    {
        $this->authorize('create', [Ticket::class, JenisTicket::tryFrom($this->jenis)]);

        // Pemasangan yang merujuk layanan wajib keempat divisi -- paksa di server, jangan
        // percaya state checkbox client (lihat autoSetDivisi() dan CONTEXT.md "Status Per-Divisi Tiket").
        if ($this->jenis === JenisTicket::Pemasangan->value) {
            $this->autoSetDivisi();
        }

        $this->validate([
            'jenis' => ['required', Rule::enum(JenisTicket::class)],
            'pelanggan_id' => ['required', 'integer', 'exists:pelanggan,id'],
            'layanan_pelanggan_id' => ['nullable', 'required_if:jenis,pencabutan,pindah_alamat', 'integer', 'exists:layanan_pelanggan,id'],
            'prioritas' => ['required', Rule::enum(PrioritasTicket::class)],
            'divisis' => ['required', 'array', 'min:1'],
            'divisis.*' => ['required', Rule::enum(DivisiTicket::class)],
            'pic_id' => ['nullable', 'integer', 'exists:users,id'],
            'dijadwalkan_pada' => ['nullable', 'date'],
            'deskripsi' => ['required', 'string', 'min:5', 'max:3000'],
            'fotoKendala' => ['nullable', 'image', 'max:5120'],
        ], [
            'pelanggan_id.required' => 'Pelanggan / Prospek wajib dipilih.',
            'layanan_pelanggan_id.required_if' => 'Layanan terkait wajib dipilih untuk tiket Pencabutan dan Pindah Alamat.',
            'divisis.required' => 'Minimal satu divisi harus dipilih.',
            'divisis.min' => 'Minimal satu divisi harus dipilih.',
            'deskripsi.required' => 'Deskripsi tiket wajib diisi.',
            'deskripsi.min' => 'Deskripsi tiket minimal 5 karakter.',
            'fotoKendala.image' => 'Lampiran foto harus berupa format gambar (jpg, png, webp).',
            'fotoKendala.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        $layananUsulan = $this->layananUntukUsulanOdp();
        $kandidatOdp = new Collection;

        if ($layananUsulan) {
            if ($layananUsulan->latitude === null || $layananUsulan->longitude === null) {
                $this->addError('layanan_pelanggan_id', 'Layanan ini belum punya koordinat. Lengkapi koordinat layanan dulu agar ODP terdekat bisa dicari.');

                return;
            }

            $kandidatOdp = $this->kandidatUsulanOdp($layananUsulan);

            if ($kandidatOdp->isNotEmpty()) {
                $this->validate([
                    'odp_usulan_id' => ['required', 'integer', Rule::in($kandidatOdp->modelKeys())],
                ], [
                    'odp_usulan_id.required' => 'Pilih Usulan ODP dari ODP terdekat.',
                    'odp_usulan_id.in' => 'Usulan ODP harus salah satu ODP terdekat yang masih punya port kosong.',
                ]);
            }
        }

        $authUserId = Auth::id();
        /** @var User $authUser */
        $authUser = Auth::user();

        $ticket = DB::transaction(function () use ($authUserId, $layananUsulan, $kandidatOdp) {
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
            $divisiRows = array_map(fn(string $d) => ['ticket_id' => $ticket->id, 'divisi' => $d], $this->divisis);
            DB::table('ticket_divisi')->insert($divisiRows);

            $catatanUsulan = '';
            if ($layananUsulan) {
                $adaKandidat = $kandidatOdp->isNotEmpty();

                TicketPemasangan::create([
                    'ticket_id' => $ticket->id,
                    'odp_usulan_id' => $adaKandidat ? $this->odp_usulan_id : null,
                    'status_usulan_odp' => $adaKandidat ? StatusUsulanOdp::Menunggu : null,
                    'tanpa_odp_dalam_jangkauan' => ! $adaKandidat,
                ]);

                $catatanUsulan = $adaKandidat
                    ? ' Usulan ODP: ' . $kandidatOdp->find($this->odp_usulan_id)?->nama_odp . ' (menunggu validasi Teknisi).'
                    : ' Tanpa ODP dalam jangkauan; Teknisi memilih ODP sendiri.';
            }

            TicketHistori::create([
                'ticket_id' => $ticket->id,
                'status_lama' => null,
                'status_baru' => StatusTicket::Baru,
                'catatan' => 'Tiket baru dibuat.' . ($this->pic_id ? ' PIC ditugaskan pada saat pembuatan.' : '') . $catatanUsulan,
                'oleh_pengguna_id' => $authUserId,
            ]);

            return $ticket;
        });

        if ($ticket->jenis === JenisTicket::Pemasangan) {
            $ticket->pelanggan?->ubahStatusPemasangan(StatusPelanggan::ReqPemasangan);
        }

        // Simpan lampiran media foto jika diunggah
        if ($this->fotoKendala) {
            try {
                $ticket->addMediaFromDisk(
                    FileUploadConfiguration::path($this->fotoKendala->getFilename(), false),
                    FileUploadConfiguration::disk()
                )
                    ->usingFileName($this->fotoKendala->getClientOriginalName())
                    ->toMediaCollection('foto_kendala');
            } catch (\Throwable $e) {
                Log::error('Gagal menyimpan foto kendala tiket: ' . $e->getMessage());
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
                    /** @var WhatsappService $whatsappService */
                    $whatsappService = app(WhatsappService::class);
                    $params = $whatsappService->buildTicketParams($ticket);
                    $whatsappService->antrikanPesan(
                        noHp: $ticket->pic->phone,
                        kodeTemplate: 'tiket_penugasan_teknisi',
                        params: $params,
                        referensi: $ticket,
                        jenis: 'tiket_assign_pic_create'
                    );
                } catch (\Throwable $e) {
                    Log::error('Gagal kirim WA penugasan teknisi: ' . $e->getMessage());
                }
            }
        }

        // Pelanggan hanya diberi tahu saat jadwal kunjungan teknisi sudah ditetapkan.
        $pelanggan = $ticket->pelanggan;
        if ($ticket->pic_id && $ticket->dijadwalkan_pada && $pelanggan && ! empty($pelanggan->no_hp)) {
            try {
                /** @var WhatsappService $whatsappService */
                $whatsappService = app(WhatsappService::class);
                $params = $whatsappService->buildTicketParams($ticket);
                $whatsappService->antrikanPesan(
                    noHp: $pelanggan->no_hp,
                    kodeTemplate: 'tiket_penjadwalan_teknisi',
                    params: $params,
                    referensi: $ticket,
                    jenis: 'tiket_penjadwalan_teknisi'
                );
            } catch (\Throwable $e) {
                Log::error('Gagal kirim WA penjadwalan teknisi ke pelanggan: ' . $e->getMessage());
            }
        }

        Flux::toast(variant: 'success', text: "Tiket {$ticket->nomor_ticket} berhasil dibuat.");

        $this->redirectRoute('ticket.show', $ticket, navigate: true);
    }

    /**
     * Usulan ODP hanya untuk Ticket Pemasangan yang merujuk layanan (CONTEXT.md "Usulan ODP").
     */
    protected function layananUntukUsulanOdp(): ?LayananPelanggan
    {
        if ($this->jenis !== JenisTicket::Pemasangan->value || ! $this->layanan_pelanggan_id) {
            return null;
        }

        return LayananPelanggan::find($this->layanan_pelanggan_id);
    }

    /**
     * Maksimal 3 ODP Terdekat dari koordinat layanan yang masih punya port kosong dan tidak Dipesan.
     *
     * @return Collection<int, Odp>
     */
    protected function kandidatUsulanOdp(LayananPelanggan $layanan): Collection
    {
        $portDipesan = array_keys(TicketPemasangan::portDipesan());

        return Odp::query()
            ->terdekat((float) $layanan->latitude, (float) $layanan->longitude, Odp::RADIUS_PELANGGAN_METER)
            ->withCount(['ports as port_tersedia_count' => fn($query) => $query
                ->where('status', StatusOdpPort::Kosong)
                ->whereNotIn('id', $portDipesan)])
            ->get()
            ->where('port_tersedia_count', '>', 0)
            ->take(3)
            ->values();
    }

    public function render(): View
    {
        $layananUsulan = $this->layananUntukUsulanOdp();
        $layananTanpaKoordinat = $layananUsulan && ($layananUsulan->latitude === null || $layananUsulan->longitude === null);

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
            'layanans' => $layanans,
            'butuhUsulanOdp' => (bool) $layananUsulan,
            'layananTanpaKoordinat' => $layananTanpaKoordinat,
            'kandidatUsulanOdp' => $layananUsulan && ! $layananTanpaKoordinat ? $this->kandidatUsulanOdp($layananUsulan) : new Collection,
            'staffList' => $staffList,
            'selectedPelanggan' => $selectedPelanggan,
            'prioritasEnum' => $prioritasEnum,
            'jenisList' => array_filter(JenisTicket::cases(), fn(JenisTicket $j) => Auth::user()->can('create', [Ticket::class, $j])),
            'prioritasList' => PrioritasTicket::cases(),
            'divisiList' => DivisiTicket::cases(),
        ]);
    }
}
