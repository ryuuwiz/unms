<?php

namespace App\Models;

use App\Enums\Ticket\StatusTicket;
use App\Enums\Ticket\StatusUsulanOdp;
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
 * @property int|null $odp_usulan_id
 * @property StatusUsulanOdp|null $status_usulan_odp
 * @property bool $tanpa_odp_dalam_jangkauan
 * @property Carbon|null $diaktivasi_pada
 * @property int|null $diaktivasi_oleh
 * @property-read Ticket $ticket
 * @property-read OdpPort|null $odpPort
 * @property-read Odp|null $odpUsulan
 * @property-read User|null $diaktivasiOleh
 */
#[Fillable(['ticket_id', 'odp_port_id', 'odp_usulan_id', 'status_usulan_odp', 'tanpa_odp_dalam_jangkauan', 'diaktivasi_pada', 'diaktivasi_oleh'])]
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
            'status_usulan_odp' => StatusUsulanOdp::class,
            'tanpa_odp_dalam_jangkauan' => 'boolean',
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
     * @return BelongsTo<Odp, $this>
     */
    public function odpUsulan(): BelongsTo
    {
        return $this->belongsTo(Odp::class, 'odp_usulan_id');
    }

    /**
     * Port yang sedang Dipesan (dipilih Teknisi pada Ticket Pemasangan lain yang masih terbuka
     * dan belum diaktivasi) -- lihat CONTEXT.md "Port ODP". Tidak disimpan sebagai status port.
     *
     * @return array<int, string> odp_port_id => nomor tiket
     */
    public static function portDipesan(?int $kecualiTicketId = null): array
    {
        return static::query()
            ->whereNotNull('odp_port_id')
            ->whereNull('diaktivasi_pada')
            ->when($kecualiTicketId, fn ($query) => $query->where('ticket_id', '!=', $kecualiTicketId))
            ->whereHas('ticket', fn ($query) => $query->whereNotIn('status', [StatusTicket::Selesai, StatusTicket::Batal]))
            ->with('ticket:id,nomor_ticket')
            ->get()
            ->mapWithKeys(fn (self $pemasangan): array => [$pemasangan->odp_port_id => $pemasangan->ticket->nomor_ticket])
            ->all();
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
