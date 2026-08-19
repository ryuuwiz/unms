<?php

namespace App\Models;

use App\Enums\Ticket\StatusTicket;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property StatusTicket|null $status_lama
 * @property StatusTicket $status_baru
 * @property string|null $catatan
 * @property int|null $oleh_pengguna_id
 * @property Carbon $created_at
 * @property-read Ticket $ticket
 * @property-read User|null $olehPengguna
 */
#[Fillable([
    'ticket_id',
    'status_lama',
    'status_baru',
    'catatan',
    'oleh_pengguna_id',
])]
class TicketHistori extends Model
{
    use HasFactory;

    protected $table = 'ticket_histori';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status_lama' => StatusTicket::class,
            'status_baru' => StatusTicket::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function olehPengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh_pengguna_id');
    }
}
