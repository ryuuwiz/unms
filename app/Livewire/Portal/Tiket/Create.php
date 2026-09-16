<?php

namespace App\Livewire\Portal\Tiket;

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Ticket\SumberTicket;
use App\Models\AkunPelanggan;
use App\Models\LayananPelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use App\Notifications\TicketBaruDariPortalNotification;
use App\Services\Whatsapp\WhatsappService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\WithFileUploads;

#[Layout('layouts.portal')]
#[Title('Ajukan Tiket Baru')]
class Create extends Component
{
    use WithFileUploads;

    public string $jenis = 'gangguan';

    public ?int $layanan_pelanggan_id = null;

    public string $deskripsi = '';

    /** @var mixed */
    public $fotoKendala = null;

    /** Konfirmasi khusus untuk jenis Pencabutan */
    public bool $showKonfirmasiPencabutan = false;

    /**
     * Jenis yang diizinkan di portal pelanggan (Pemasangan dikecualikan).
     *
     * @var array<string, string>
     */
    public function jenisYangDiizinkan(): array
    {
        return [
            JenisTicket::Gangguan->value => JenisTicket::Gangguan->label(),
            JenisTicket::Pencabutan->value => JenisTicket::Pencabutan->label(),
            JenisTicket::PindahAlamat->value => JenisTicket::PindahAlamat->label(),
        ];
    }

    public function updatedJenis(): void
    {
        $this->layanan_pelanggan_id = null;
        $this->showKonfirmasiPencabutan = false;
    }

    public function submit(): void
    {
        // Cek apakah perlu konfirmasi Pencabutan terlebih dahulu
        if ($this->jenis === JenisTicket::Pencabutan->value && ! $this->showKonfirmasiPencabutan) {
            $this->showKonfirmasiPencabutan = true;

            return;
        }

        $this->simpan();
    }

    public function batalKonfirmasi(): void
    {
        $this->showKonfirmasiPencabutan = false;
    }

    public function simpan(): void
    {
        /** @var AkunPelanggan $akun */
        $akun = Auth::guard('pelanggan')->user();

        $this->validate([
            'jenis' => ['required', 'in:gangguan,pencabutan,pindah_alamat'],
            'layanan_pelanggan_id' => ['required', 'integer', 'exists:layanan_pelanggan,id'],
            'deskripsi' => ['required', 'string', 'min:5', 'max:3000'],
            'fotoKendala' => ['nullable', 'image', 'max:5120'],
        ], [
            'layanan_pelanggan_id.required' => 'Layanan wajib dipilih.',
            'deskripsi.required' => 'Deskripsi keluhan wajib diisi.',
            'deskripsi.min' => 'Deskripsi minimal 5 karakter.',
            'fotoKendala.image' => 'Lampiran foto harus berupa format gambar (jpg, png, webp).',
            'fotoKendala.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        // Pastikan layanan milik pelanggan yang login
        $layanan = LayananPelanggan::where('id', $this->layanan_pelanggan_id)
            ->where('pelanggan_id', $akun->pelanggan_id)
            ->first();

        abort_unless($layanan !== null, 403);

        [$prioritas, $divisis] = $this->autoMapping();

        $ticket = DB::transaction(function () use ($akun, $divisis, $prioritas) {
            $ticket = Ticket::create([
                'jenis' => $this->jenis,
                'pelanggan_id' => $akun->pelanggan_id,
                'layanan_pelanggan_id' => $this->layanan_pelanggan_id,
                'prioritas' => $prioritas,
                'status' => StatusTicket::Baru,
                'sumber' => SumberTicket::Portal,
                'deskripsi' => trim($this->deskripsi),
                'dibuat_oleh' => null,
            ]);

            // Sync divisi ke pivot
            $rows = array_map(fn (string $d) => ['ticket_id' => $ticket->id, 'divisi' => $d], $divisis);
            DB::table('ticket_divisi')->insert($rows);

            TicketHistori::create([
                'ticket_id' => $ticket->id,
                'status_lama' => null,
                'status_baru' => StatusTicket::Baru,
                'catatan' => 'Tiket diajukan oleh pelanggan melalui Portal Pelanggan.',
                'is_internal' => false,
                'oleh_pengguna_id' => null,
            ]);

            return $ticket;
        });

        // Simpan foto kendala jika diunggah
        if ($this->fotoKendala) {
            try {
                $ticket->addMediaFromDisk(
                    FileUploadConfiguration::path($this->fotoKendala->getFilename(), false),
                    FileUploadConfiguration::disk()
                )
                    ->usingFileName($this->fotoKendala->getClientOriginalName())
                    ->toMediaCollection('foto_kendala');
            } catch (\Throwable $e) {
                Log::error('Gagal simpan foto kendala portal: '.$e->getMessage());
            }
        }

        // Notifikasi ke semua staf dengan permission ticket.lihat
        $staffDenganAkses = User::permission('ticket.lihat')->get();
        Notification::send($staffDenganAkses, new TicketBaruDariPortalNotification($ticket->load('pelanggan')));

        // Kirim WhatsApp konfirmasi ke Pelanggan
        $pelanggan = $ticket->pelanggan;
        if ($pelanggan && ! empty($pelanggan->no_hp)) {
            try {
                /** @var WhatsappService $whatsappService */
                $whatsappService = app(WhatsappService::class);
                $params = $whatsappService->buildTicketParams($ticket);
                $whatsappService->antrikanPesan(
                    noHp: $pelanggan->no_hp,
                    kodeTemplate: 'tiket_dibuat',
                    params: $params,
                    referensi: $ticket,
                    jenis: 'tiket_dibuat_portal'
                );
            } catch (\Throwable $e) {
                Log::error('Gagal kirim WA tiket portal ke pelanggan: '.$e->getMessage());
            }
        }

        Flux::toast(variant: 'success', text: "Tiket {$ticket->nomor_ticket} berhasil diajukan. Tim kami akan segera menindaklanjuti.");

        $this->redirectRoute('portal.tiket.show', $ticket, navigate: true);
    }

    /**
     * Auto-mapping prioritas dan divisi berdasarkan jenis tiket portal.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    protected function autoMapping(): array
    {
        return match ($this->jenis) {
            JenisTicket::Gangguan->value => [
                PrioritasTicket::Sedang->value,
                [DivisiTicket::Noc->value, DivisiTicket::Teknisi->value],
            ],
            JenisTicket::Pencabutan->value => [
                PrioritasTicket::Rendah->value,
                [DivisiTicket::Teknisi->value, DivisiTicket::CustomerService->value],
            ],
            JenisTicket::PindahAlamat->value => [
                PrioritasTicket::Sedang->value,
                [DivisiTicket::Noc->value, DivisiTicket::Teknisi->value],
            ],
            default => [PrioritasTicket::Sedang->value, [DivisiTicket::Teknisi->value]],
        };
    }

    public function render(): View
    {
        /** @var AkunPelanggan $akun */
        $akun = Auth::guard('pelanggan')->user();

        /** @var Collection<int, LayananPelanggan> $layanans */
        $layanans = LayananPelanggan::query()
            ->where('pelanggan_id', $akun->pelanggan_id)
            ->whereIn('status', ['aktif', 'suspend'])
            ->with(['paketLayanan'])
            ->get();

        return view('livewire.portal.tiket.create', [
            'layanans' => $layanans,
            'jenisYangDiizinkan' => $this->jenisYangDiizinkan(),
        ]);
    }
}
