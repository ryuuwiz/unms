<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Detail teknis khusus Ticket Pemasangan (1:1 dengan Ticket) -- lihat
 * docs/plan/ticket-pemasangan-workflow.md dan CONTEXT.md "Aktivasi Pemasangan".
 *
 * @property int $ticket_id
 * @property int|null $odp_port_id
 * @property Carbon|null $diaktivasi_pada
 * @property int|null $diaktivasi_oleh
 * @property-read Ticket $ticket
 * @property-read OdpPort|null $odpPort
 * @property-read User|null $diaktivasiOleh
 */
#[Fillable(['ticket_id', 'odp_port_id', 'diaktivasi_pada', 'diaktivasi_oleh'])]
class TicketPemasangan extends Model
{
    protected $table = 'ticket_pemasangan';

    protected $primaryKey = 'ticket_id';

    public $incrementing = false;

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diaktivasi_pada' => 'datetime',
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
     * @return BelongsTo<OdpPort, $this>
     */
    public function odpPort(): BelongsTo
    {
        return $this->belongsTo(OdpPort::class, 'odp_port_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function diaktivasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diaktivasi_oleh');
    }

    public function sudahDiaktivasi(): bool
    {
        return $this->diaktivasi_pada !== null;
    }
}
