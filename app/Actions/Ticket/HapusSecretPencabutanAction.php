<?php

namespace App\Actions\Ticket;

use App\Enums\Ticket\JenisTicket;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
use App\Support\PppDeletionContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HapusSecretPencabutanAction
{
    public function __construct(private MikrotikService $mikrotikService) {}

    /**
     * Proses NOC pada tiket Pencabutan: hapus PPP Secret di router (satu-satunya jalur yang sah,
     * lihat CONTEXT.md "Penghapusan PPP Secret"), lalu tandai gerbang Selesai tiket ini terbuka.
     *
     * @throws InvalidArgumentException
     */
    public function execute(Ticket $ticket, User $actor): Ticket
    {
        if ($ticket->jenis !== JenisTicket::Pencabutan) {
            throw new InvalidArgumentException('Aksi ini hanya berlaku untuk tiket Pencabutan.');
        }

        if ($ticket->secretSudahDihapus()) {
            return $ticket;
        }

        $layanan = $ticket->layananPelanggan;

        if (! $layanan || ! $layanan->router_id || ! $layanan->ppp_username) {
            throw new InvalidArgumentException('Layanan tiket ini belum punya router/PPP username -- tidak ada secret untuk dihapus.');
        }

        $this->mikrotikService->deletePppoeSecret(
            router: $layanan->router,
            target: $layanan,
            context: PppDeletionContext::forUser($actor->id, "Pencabutan tiket {$ticket->nomor_ticket}"),
        );

        DB::transaction(function () use ($ticket, $actor) {
            $ticket->update([
                'secret_dihapus_pada' => now(),
                'secret_dihapus_oleh' => $actor->id,
            ]);

            TicketHistori::create([
                'ticket_id' => $ticket->id,
                'status_lama' => $ticket->status,
                'status_baru' => $ticket->status,
                'catatan' => '[NOC] PPP Secret berhasil dihapus dari router. Tiket sudah bisa ditandai Selesai.',
                'oleh_pengguna_id' => $actor->id,
            ]);
        });

        return $ticket->refresh();
    }
}
