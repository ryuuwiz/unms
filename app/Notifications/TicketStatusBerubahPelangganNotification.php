<?php

namespace App\Notifications;

use App\Enums\Ticket\StatusTicket;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketStatusBerubahPelangganNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public StatusTicket $statusLama,
        public StatusTicket $statusBaru,
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
        return [
            'ticket_id' => $this->ticket->id,
            'nomor_ticket' => $this->ticket->nomor_ticket,
            'status_lama' => $this->statusLama->value,
            'status_baru' => $this->statusBaru->value,
            'title' => "Status Tiket {$this->ticket->nomor_ticket} Diperbarui",
            'message' => "Status tiket Anda ({$this->ticket->nomor_ticket}) telah berubah dari '{$this->statusLama->label()}' menjadi '{$this->statusBaru->label()}'."
                .($this->catatan ? " Catatan: {$this->catatan}" : ''),
            'url' => route('portal.tiket.show', $this->ticket),
        ];
    }
}
