<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketBaruDariPortalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $pelangganNama = $this->ticket->pelanggan?->namaLengkap() ?? '-';

        return [
            'ticket_id' => $this->ticket->id,
            'nomor_ticket' => $this->ticket->nomor_ticket,
            'jenis' => $this->ticket->jenis->value,
            'title' => "Tiket Baru dari Portal: {$this->ticket->nomor_ticket}",
            'message' => "Pelanggan {$pelangganNama} mengajukan tiket {$this->ticket->jenis->label()} melalui Portal Pelanggan.",
            'url' => route('ticket.show', $this->ticket),
        ];
    }
}
