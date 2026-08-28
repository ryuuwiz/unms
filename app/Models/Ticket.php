<?php

namespace App\Models;

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Ticket\SumberTicket;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $nomor_ticket
 * @property JenisTicket $jenis
 * @property int $pelanggan_id
 * @property int|null $layanan_pelanggan_id
 * @property PrioritasTicket $prioritas
 * @property int|null $pic_id
 * @property StatusTicket $status
 * @property SumberTicket $sumber
 * @property Carbon|null $sla_target_selesai
 * @property bool $perlu_aktivasi_manual
 * @property string $deskripsi
 * @property Carbon|null $dijadwalkan_pada
 * @property int|null $dibuat_oleh
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pelanggan $pelanggan
 * @property-read LayananPelanggan|null $layananPelanggan
 * @property-read User|null $pic
 * @property-read User|null $dibuatOleh
 * @property-read Collection<int, TicketHistori> $histori
 * @property-read Collection<int, \stdClass> $divisis
 */
#[Fillable([
    'nomor_ticket',
    'jenis',
    'pelanggan_id',
    'layanan_pelanggan_id',
    'prioritas',
    'pic_id',
    'status',
    'sumber',
    'sla_target_selesai',
    'perlu_aktivasi_manual',
    'deskripsi',
    'dijadwalkan_pada',
    'dibuat_oleh',
])]
class Ticket extends Model implements HasMedia
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $table = 'ticket';

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->nomor_ticket)) {
                $ticket->nomor_ticket = static::generateNomorTicket();
            }

            if (empty($ticket->sla_target_selesai) && $ticket->prioritas instanceof PrioritasTicket) {
                $ticket->sla_target_selesai = Carbon::now()->addHours($ticket->prioritas->durasiSlaHours());
            }
        });
    }

    /**
     * Konfigurasi logging aktivitas.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nomor_ticket', 'jenis', 'status', 'prioritas', 'pic_id', 'perlu_aktivasi_manual'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('ticket');
    }

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisTicket::class,
            'prioritas' => PrioritasTicket::class,
            'status' => StatusTicket::class,
            'sumber' => SumberTicket::class,
            'sla_target_selesai' => 'datetime',
            'dijadwalkan_pada' => 'datetime',
            'perlu_aktivasi_manual' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Generate nomor tiket berurutan per tahun: TCK-YYYY-NNNNNN
     */
    public static function generateNomorTicket(): string
    {
        $year = Carbon::now()->format('Y');
        $prefix = "TCK-{$year}-";

        $last = DB::table('ticket')
            ->where('nomor_ticket', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('nomor_ticket');

        if ($last) {
            $lastNum = (int) substr($last, strlen($prefix));
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix.str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Relasi ke daftar divisi penanganan tiket via `TicketDivisi`.
     *
     * @return HasMany<TicketDivisi, $this>
     */
    public function divisis(): HasMany
    {
        return $this->hasMany(TicketDivisi::class, 'ticket_id');
    }

    /**
     * Cek apakah tiket ditangani oleh divisi tertentu.
     */
    public function hasDivisi(DivisiTicket $divisi): bool
    {
        if ($this->relationLoaded('divisis')) {
            return $this->divisis->contains(fn (TicketDivisi $item) => $item->divisi === $divisi);
        }

        return DB::table('ticket_divisi')
            ->where('ticket_id', $this->id)
            ->where('divisi', $divisi->value)
            ->exists();
    }

    /**
     * Ambil nilai divisi sebagai array string dari pivot.
     *
     * @return array<int, string>
     */
    public function getDivisValues(): array
    {
        if ($this->relationLoaded('divisis')) {
            return $this->divisis->map(fn (TicketDivisi $item) => $item->divisi->value)->values()->toArray();
        }

        return DB::table('ticket_divisi')
            ->where('ticket_id', $this->id)
            ->pluck('divisi')
            ->toArray();
    }

    /**
     * @return BelongsTo<Pelanggan, $this>
     */
    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    /**
     * @return BelongsTo<LayananPelanggan, $this>
     */
    public function layananPelanggan(): BelongsTo
    {
        return $this->belongsTo(LayananPelanggan::class, 'layanan_pelanggan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * @return HasMany<TicketHistori, $this>
     */
    public function histori(): HasMany
    {
        return $this->hasMany(TicketHistori::class, 'ticket_id')->orderByDesc('id');
    }

    public function isSelesai(): bool
    {
        return $this->status === StatusTicket::Selesai;
    }

    public function isBatal(): bool
    {
        return $this->status === StatusTicket::Batal;
    }

    public function isOverdue(): bool
    {
        if ($this->isSelesai() || $this->isBatal() || ! $this->sla_target_selesai) {
            return false;
        }

        return Carbon::now()->isAfter($this->sla_target_selesai);
    }

    /**
     * Sisa waktu SLA dalam format yang ramah manusia.
     */
    public function sisaWaktuSla(): string
    {
        if (! $this->sla_target_selesai) {
            return '-';
        }

        if ($this->isSelesai()) {
            return 'Selesai';
        }

        if ($this->isBatal()) {
            return 'Dibatalkan';
        }

        if ($this->isOverdue()) {
            return 'Lewat '.Carbon::now()->diffForHumans($this->sla_target_selesai, true);
        }

        return Carbon::now()->diffForHumans($this->sla_target_selesai, true);
    }

    /**
     * Scope filter tiket yang di-assign ke user tertentu.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('pic_id', $userId);
    }

    /**
     * Scope filter divisi.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeDivisi(Builder $query, DivisiTicket|string $divisi): Builder
    {
        $val = $divisi instanceof DivisiTicket ? $divisi->value : $divisi;

        return $query->whereHas('divisis', function (Builder $q) use ($val) {
            $q->where('ticket_divisi.divisi', $val);
        });
    }

    /**
     * Scope filter status.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeStatus(Builder $query, StatusTicket|string $status): Builder
    {
        $val = $status instanceof StatusTicket ? $status->value : $status;

        return $query->where('status', $val);
    }

    /**
     * Scope filter jenis.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeJenis(Builder $query, JenisTicket|string $jenis): Builder
    {
        $val = $jenis instanceof JenisTicket ? $jenis->value : $jenis;

        return $query->where('jenis', $val);
    }

    /**
     * Scope tiket overdue SLA.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotIn('status', [StatusTicket::Selesai->value, StatusTicket::Batal->value])
            ->whereNotNull('sla_target_selesai')
            ->where('sla_target_selesai', '<', Carbon::now());
    }

    /**
     * Scope pencarian teks komprehensif (No. Ticket, Nama Pelanggan, No. Reg, PPP Username, Deskripsi).
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('nomor_ticket', 'like', "%{$term}%")
                ->orWhere('deskripsi', 'like', "%{$term}%")
                ->orWhereHas('pelanggan', function (Builder $customerQuery) use ($term) {
                    $customerQuery->where('nama_depan', 'like', "%{$term}%")
                        ->orWhere('nama_belakang', 'like', "%{$term}%")
                        ->orWhere('no_reg', 'like', "%{$term}%")
                        ->orWhere('no_hp', 'like', "%{$term}%");
                })
                ->orWhereHas('layananPelanggan', function (Builder $layananQuery) use ($term) {
                    $layananQuery->where('site_id', 'like', "%{$term}%")
                        ->orWhere('ppp_username', 'like', "%{$term}%");
                })
                ->orWhereHas('pic', function (Builder $picQuery) use ($term) {
                    $picQuery->where('name', 'like', "%{$term}%");
                });
        });
    }
}
