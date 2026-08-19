<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketDiassignNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public User $assignedBy,
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
        return [
            'ticket_id' => $this->ticket->id,
            'nomor_ticket' => $this->ticket->nomor_ticket,
            'jenis' => $this->ticket->jenis->value,
            'prioritas' => $this->ticket->prioritas->value,
            'title' => "Penugasan Tiket {$this->ticket->nomor_ticket}",
            'message' => "Anda telah ditugaskan sebagai PIC untuk tiket {$this->ticket->jenis->label()} ({$this->ticket->nomor_ticket}) oleh {$this->assignedBy->name}.",
            'url' => route('ticket.show', $this->ticket),
        ];
    }
}
