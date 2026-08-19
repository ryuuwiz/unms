<?php

namespace App\Notifications;

use App\Enums\Ticket\StatusTicket;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketStatusBerubahNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public StatusTicket $statusLama,
        public StatusTicket $statusBaru,
        public User $changedBy,
        public ?string $catatan = null,
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
        $catatanInfo = $this->catatan ? " Catatan: {$this->catatan}" : '';

        return [
            'ticket_id' => $this->ticket->id,
            'nomor_ticket' => $this->ticket->nomor_ticket,
            'status_lama' => $this->statusLama->value,
            'status_baru' => $this->statusBaru->value,
            'title' => "Status Tiket {$this->ticket->nomor_ticket} Berubah",
            'message' => "Status tiket {$this->ticket->nomor_ticket} telah diubah dari '{$this->statusLama->label()}' menjadi '{$this->statusBaru->label()}' oleh {$this->changedBy->name}.{$catatanInfo}",
            'url' => route('ticket.show', $this->ticket),
        ];
    }
}
