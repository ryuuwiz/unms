<?php

namespace App\Actions\Ticket;

use App\Enums\StatusInvoice;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\StatusDivisiTicket;
use App\Enums\Ticket\StatusTicket;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UbahStatusDivisiTicketAction
{
    public function __construct(private UbahStatusTicketAction $ubahStatusTicketAction) {}

    /**
     * Ubah status sign-off satu divisi pada Ticket Pemasangan, lalu otomatis derive status
     * keseluruhan tiket ke Selesai begitu seluruh divisi wajib (Ticket::DIVISI_WAJIB_PEMASANGAN)
     * sudah Selesai -- lihat CONTEXT.md "Status Per-Divisi Tiket".
     *
     * @throws InvalidArgumentException jika divisi Admin ditandai Selesai sebelum invoice pertama lunas
     */
    public function execute(Ticket $ticket, DivisiTicket $divisi, StatusDivisiTicket $statusBaru, User $actor): Ticket
    {
        if ($divisi === DivisiTicket::Admin && $statusBaru === StatusDivisiTicket::Selesai) {
            $this->assertInvoicePertamaLunas($ticket);
        }

        DB::table('ticket_divisi')
            ->where('ticket_id', $ticket->id)
            ->where('divisi', $divisi->value)
            ->update(['status' => $statusBaru->value]);

        $ticket->unsetRelation('divisis')->load('divisis');

        if ($ticket->semuaDivisiWajibSelesai() && $ticket->status !== StatusTicket::Selesai) {
            $this->ubahStatusTicketAction->execute(
                ticket: $ticket,
                statusBaru: StatusTicket::Selesai,
                actor: $actor,
                catatan: 'Otomatis: seluruh divisi wajib (Teknisi, NOC, Customer Service, Admin) sudah menandai selesai.',
                otomatis: true,
            );
        }

        return $ticket->fresh(['divisis']);
    }

    /**
     * Gate divisi Admin: invoice pertama (ad-hoc, periode_tagihan NULL) layanan terkait harus
     * sudah Lunas -- lihat CONTEXT.md "Status Per-Divisi Tiket".
     */
    private function assertInvoicePertamaLunas(Ticket $ticket): void
    {
        $layanan = $ticket->layananPelanggan;

        if (! $layanan) {
            throw new InvalidArgumentException('Tiket ini belum terhubung ke Data Registrasi Billing.');
        }

        $lunas = $layanan->invoices()
            ->whereNull('periode_tagihan')
            ->where('status', StatusInvoice::Lunas)
            ->exists();

        if (! $lunas) {
            throw new InvalidArgumentException('Invoice pertama layanan ini belum lunas -- Admin belum bisa menandai selesai.');
        }
    }
}
