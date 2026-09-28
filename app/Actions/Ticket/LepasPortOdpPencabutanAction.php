<?php

namespace App\Actions\Ticket;

use App\Enums\StatusOdpPort;
use App\Enums\Ticket\JenisTicket;
use App\Models\OdpPort;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LepasPortOdpPencabutanAction
{
    /**
     * Teknisi mencabut perangkat dan melepas port ODP pada tiket Pencabutan (CONTEXT.md "Pencabutan").
     * Port kembali Kosong dan bisa dipakai layanan lain.
     *
     * @throws InvalidArgumentException
     */
    public function execute(Ticket $ticket, User $actor): Ticket
    {
        if ($ticket->jenis !== JenisTicket::Pencabutan) {
            throw new InvalidArgumentException('Aksi ini hanya berlaku untuk tiket Pencabutan.');
        }

        $layanan = $ticket->layananPelanggan;
        $odpPortId = $layanan?->odp_port_id;

        if (! $layanan || ! $odpPortId) {
            throw new InvalidArgumentException('Layanan tiket ini tidak punya port ODP terpasang.');
        }

        DB::transaction(function () use ($layanan, $odpPortId, $ticket, $actor) {
            OdpPort::whereKey($odpPortId)->update([
                'status' => StatusOdpPort::Kosong,
                'layanan_pelanggan_id' => null,
            ]);

            $layanan->update(['odp_port_id' => null]);

            TicketHistori::create([
                'ticket_id' => $ticket->id,
                'status_lama' => $ticket->status,
                'status_baru' => $ticket->status,
                'catatan' => '[Teknisi] Perangkat dicabut, port ODP dilepas.',
                'oleh_pengguna_id' => $actor->id,
            ]);
        });

        return $ticket->refresh();
    }
}
