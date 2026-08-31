<?php

namespace App\Livewire\Ticket;

use App\Actions\Ticket\AssignPicAction;
use App\Actions\Ticket\UbahStatusTicketAction;
use App\Enums\Ticket\StatusTicket;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use App\Services\Whatsapp\WhatsappService;
use Exception;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Detail Tiket')]
class Show extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    // State Modal Ubah Status
    public bool $showUbahStatusModal = false;

    public string $statusBaru = '';

    public string $catatanStatus = '';

    // State Modal Assign PIC
    public bool $showAssignPicModal = false;

    public ?int $selectedPicId = null;

    public string $catatanAssign = '';

    // State Modal Tambah Catatan
    public bool $showCatatanModal = false;

    public string $catatanProses = '';

    public bool $catatanIsInternal = false;

    /** @var mixed */
    public $fotoPengerjaan = null;

    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->ticket = $ticket;
        $this->loadTicket();
    }

    protected function loadTicket(): void
    {
        $this->ticket->load([
            'pelanggan.perumahan.kelurahan.kecamatan.kota',
            'pelanggan.dibuatOleh',
            'layananPelanggan.paketLayanan.profilBandwidth',
            'layananPelanggan.router',
            'layananPelanggan.odpPort.odp',
            'pic',
            'dibuatOleh',
            'divisis',
            'histori.olehPengguna',
            'media',
        ]);
    }

    public function openUbahStatusModal(): void
    {
        $transisiValid = $this->ticket->status->transisiValid();
        $this->statusBaru = ! empty($transisiValid) ? $transisiValid[0]->value : '';
        $this->catatanStatus = '';
        $this->showUbahStatusModal = true;
    }

    public function prosesUbahStatus(UbahStatusTicketAction $action): void
    {
        $this->validate([
            'statusBaru' => ['required', 'string'],
            'catatanStatus' => ['nullable', 'string', 'max:1000'],
        ]);

        $statusBaruEnum = StatusTicket::tryFrom($this->statusBaru);
        if (! $statusBaruEnum) {
            Flux::toast(variant: 'danger', text: 'Status tujuan tidak valid.');

            return;
        }

        try {
            /** @var User $actor */
            $actor = Auth::user();
            $action->execute(
                ticket: $this->ticket,
                statusBaru: $statusBaruEnum,
                actor: $actor,
                catatan: $this->catatanStatus ?: null,
            );

            Flux::toast(variant: 'success', text: "Status tiket {$this->ticket->nomor_ticket} berhasil diubah ke {$statusBaruEnum->label()}.");
            $this->showUbahStatusModal = false;
            $this->loadTicket();
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openAssignPicModal(): void
    {
        $this->selectedPicId = $this->ticket->pic_id;
        $this->catatanAssign = '';
        $this->showAssignPicModal = true;
    }

    public function prosesAssignPic(AssignPicAction $action): void
    {
        $this->validate([
            'selectedPicId' => ['nullable', 'integer', 'exists:users,id'],
            'catatanAssign' => ['nullable', 'string', 'max:500'],
        ]);

        $newPic = $this->selectedPicId ? User::find($this->selectedPicId) : null;

        try {
            /** @var User $actor */
            $actor = Auth::user();
            $action->execute(
                ticket: $this->ticket,
                pic: $newPic,
                actor: $actor,
                catatan: $this->catatanAssign ?: null,
            );

            $picName = $newPic ? $newPic->name : 'Belum Ditugaskan';
            Flux::toast(variant: 'success', text: "PIC tiket {$this->ticket->nomor_ticket} berhasil diperbarui: {$picName}.");
            $this->showAssignPicModal = false;
            $this->loadTicket();
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openCatatanModal(): void
    {
        $this->catatanProses = '';
        $this->catatanIsInternal = false;
        $this->fotoPengerjaan = null;
        $this->showCatatanModal = true;
    }

    public function simpanCatatan(): void
    {
        $this->validate([
            'catatanProses' => ['required', 'string', 'min:3', 'max:1000'],
            'fotoPengerjaan' => ['nullable', 'image', 'max:5120'],
        ], [
            'catatanProses.required' => 'Catatan proses penanganan wajib diisi.',
            'fotoPengerjaan.image' => 'Bukti pengerjaan harus berupa berkas gambar (jpg, png, webp).',
            'fotoPengerjaan.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        $histori = TicketHistori::create([
            'ticket_id' => $this->ticket->id,
            'status_lama' => $this->ticket->status,
            'status_baru' => $this->ticket->status,
            'catatan' => trim($this->catatanProses),
            'is_internal' => $this->catatanIsInternal,
            'oleh_pengguna_id' => Auth::id(),
        ]);

        if ($this->fotoPengerjaan) {
            try {
                $histori->addMedia($this->fotoPengerjaan->getRealPath())
                    ->usingFileName($this->fotoPengerjaan->getClientOriginalName())
                    ->toMediaCollection('foto_pengerjaan');
            } catch (\Throwable $e) {
                Log::error('Gagal menyimpan foto pengerjaan: '.$e->getMessage());
            }
        }

        // Jika catatan publik, kirim notifikasi WhatsApp ke Pelanggan
        if (! $this->catatanIsInternal && $this->ticket->pelanggan && ! empty($this->ticket->pelanggan->no_hp)) {
            try {
                /** @var WhatsappService $wablasService */
                $wablasService = app(WhatsappService::class);
                $params = $wablasService->buildTicketParams($this->ticket, trim($this->catatanProses));
                $wablasService->antrikanPesan(
                    noHp: $this->ticket->pelanggan->no_hp,
                    kodeTemplate: 'tiket_status_update',
                    params: $params,
                    referensi: $this->ticket,
                    jenis: "tiket_catatan_{$histori->id}"
                );
            } catch (\Throwable $e) {
                Log::error('Gagal kirim WA catatan baru: '.$e->getMessage());
            }
        }

        Flux::toast(variant: 'success', text: 'Catatan proses penanganan berhasil ditambahkan.');
        $this->showCatatanModal = false;
        $this->loadTicket();
    }

    public function render(): View
    {
        /** @var Collection<int, User> $staffList */
        $staffList = User::query()->active()->orderBy('name')->get();

        $transisiValid = $this->ticket->status->transisiValid();

        return view('livewire.ticket.show', [
            'staffList' => $staffList,
            'transisiValid' => $transisiValid,
        ]);
    }
}
