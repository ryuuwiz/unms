<?php

namespace App\Models;

use App\Enums\Ticket\DivisiTicket;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $ticket_id
 * @property DivisiTicket $divisi
 */
class TicketDivisi extends Pivot
{
    protected $table = 'ticket_divisi';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'divisi' => DivisiTicket::class,
        ];
    }
}
