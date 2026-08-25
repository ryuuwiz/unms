<?php

namespace App\Livewire\Portal\Tiket;

use App\Enums\Ticket\StatusTicket;
use App\Models\AkunPelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Detail Tiket')]
class Show extends Component
{
    public Ticket $ticket;

    /** State modal batalkan tiket */
    public bool $showBatalkanModal = false;

    public string $alasanBatalkan = '';

    public function mount(Ticket $ticket): void
    {
        /** @var AkunPelanggan $akun */
        $akun = Auth::guard('pelanggan')->user();

        // Pastikan tiket milik pelanggan yang sedang login
        abort_unless($ticket->pelanggan_id === $akun->pelanggan_id, 403);

        $this->ticket = $ticket;
        $this->loadTicket();

        // Auto-mark notifikasi terkait tiket ini sebagai sudah dibaca
        $akun->unreadNotifications()
            ->where('data->ticket_id', $ticket->id)
            ->update(['read_at' => now()]);
    }

    protected function loadTicket(): void
    {
        $this->ticket->load([
            'pelanggan',
            'layananPelanggan.paketLayanan',
            'histori' => fn ($q) => $q->where('is_internal', false)->orderByDesc('id'),
        ]);
    }

    public function openBatalkanModal(): void
    {
        $this->alasanBatalkan = '';
        $this->showBatalkanModal = true;
    }

    public function batalkanTiket(): void
    {
        /** @var AkunPelanggan $akun */
        $akun = Auth::guard('pelanggan')->user();

        // Re-fetch untuk memastikan status terbaru
        $this->ticket->refresh();

        abort_unless($this->ticket->pelanggan_id === $akun->pelanggan_id, 403);

        if ($this->ticket->status !== StatusTicket::Baru) {
            Flux::toast(variant: 'danger', text: 'Tiket hanya dapat dibatalkan selama masih berstatus Baru.');

            return;
        }

        $this->validate([
            'alasanBatalkan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'alasanBatalkan.required' => 'Alasan pembatalan wajib diisi.',
            'alasanBatalkan.min' => 'Alasan pembatalan minimal 5 karakter.',
        ]);

        DB::transaction(function () {
            $this->ticket->update(['status' => StatusTicket::Batal]);

            TicketHistori::create([
                'ticket_id' => $this->ticket->id,
                'status_lama' => StatusTicket::Baru,
                'status_baru' => StatusTicket::Batal,
                'catatan' => 'Dibatalkan oleh pelanggan. Alasan: '.trim($this->alasanBatalkan),
                'is_internal' => false,
                'oleh_pengguna_id' => null,
            ]);
        });

        Flux::toast(variant: 'success', text: "Tiket {$this->ticket->nomor_ticket} berhasil dibatalkan.");
        $this->showBatalkanModal = false;
        $this->loadTicket();
    }

    public function render(): View
    {
        return view('livewire.portal.tiket.show');
    }
}
