<?php

namespace App\Livewire;

use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusTicket;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Papan Antrean Teknisi (CONTEXT.md): layar kantor publik tanpa login.
 * Hanya nomor tiket, nama disamarkan, jenis, status, dan PIC yang sampai ke view.
 */
#[Layout('layouts.papan')]
class PapanAntrean extends Component
{
    public const BATAS_BARIS_PER_KELOMPOK = 10;

    public function render(): View
    {
        // ponytail: memuat semua tiket terbuka lalu dikelompokkan di PHP; pindah ke query per kelompok bila ratusan.
        $tiket = Ticket::query()
            ->whereIn('jenis', [JenisTicket::Pemasangan, JenisTicket::Gangguan])
            ->whereIn('status', [StatusTicket::Baru, StatusTicket::Diproses, StatusTicket::MenungguKonfirmasi])
            ->whereHas('pelanggan')
            ->with(['pelanggan', 'pic.media'])
            ->oldest()
            ->get()
            ->groupBy(fn (Ticket $ticket) => $this->kelompok($ticket));

        return view('livewire.papan-antrean', [
            'kelompok' => collect(['Sedang Dikerjakan', 'Sudah Ada Teknisi', 'Menunggu Teknisi'])
                ->mapWithKeys(fn (string $judul) => [$judul => $tiket->get($judul, new Collection)]),
        ]);
    }

    private function kelompok(Ticket $ticket): string
    {
        if ($ticket->status !== StatusTicket::Baru) {
            return 'Sedang Dikerjakan';
        }

        return $ticket->pic_id ? 'Sudah Ada Teknisi' : 'Menunggu Teknisi';
    }
}
