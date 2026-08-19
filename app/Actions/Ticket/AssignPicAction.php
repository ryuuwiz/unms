<?php

namespace App\Actions\Ticket;

use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use App\Notifications\TicketDiassignNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AssignPicAction
{
    /**
     * Eksekusi penugasan PIC tiket.
     *
     * @throws AuthorizationException
     */
    public function execute(Ticket $ticket, ?User $pic, User $actor, ?string $catatan = null): Ticket
    {
        if (! Gate::forUser($actor)->allows('assignPic', [$ticket, $pic])) {
            throw new AuthorizationException(
                "Anda tidak memiliki wewenang untuk menugaskan PIC pada tiket {$ticket->nomor_ticket}."
            );
        }

        DB::transaction(function () use ($ticket, $pic, $actor, $catatan) {
            $ticket->update([
                'pic_id' => $pic?->id,
            ]);

            $pesanLog = $pic
                ? "PIC ditugaskan ke: {$pic->name}."
                : 'Penugasan PIC dibatalkan.';

            if ($catatan && trim($catatan) !== '') {
                $pesanLog .= ' Catatan: '.trim($catatan);
            }

            TicketHistori::create([
                'ticket_id' => $ticket->id,
                'status_lama' => $ticket->status,
                'status_baru' => $ticket->status,
                'catatan' => $pesanLog,
                'oleh_pengguna_id' => $actor->id,
            ]);
        });

        // Kirim notifikasi ke PIC baru jika bukan aktor sendiri
        if ($pic && $pic->id !== $actor->id) {
            $pic->notify(new TicketDiassignNotification(
                ticket: $ticket,
                assignedBy: $actor,
            ));
        }

        return $ticket->refresh()->load(['histori.olehPengguna', 'pic', 'dibuatOleh']);
    }
}
