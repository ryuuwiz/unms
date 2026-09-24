<?php

namespace App\Models;

use Database\Factories\TemplateDeskripsiTagihanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Template `description` yang dikirim ke payment gateway -- lihat CONTEXT.md
 * "Template Deskripsi Tagihan Gateway".
 *
 * @property int $id
 * @property string $nama
 * @property string $konten
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nama', 'konten', 'is_default'])]
class TemplateDeskripsiTagihan extends Model
{
    /** @use HasFactory<TemplateDeskripsiTagihanFactory> */
    use HasFactory, LogsActivity;

    public const KONTEN_DEFAULT = '{brand} ({site_id}) Pembayaran Internet Periode {bulan} {nama_paket_pelanggan} hingga {hingga}';

    /** Teks tetap untuk invoice tanpa periode (tagihan pertama, manual). */
    public const KONTEN_TANPA_PERIODE = '{brand} - {keterangan} - Invoice {no_invoice}';

    /** @var list<string> */
    public const PLACEHOLDERS = [
        'brand', 'site_id', 'bulan', 'nama_paket_pelanggan', 'hingga', 'no_invoice',
        'nama_pelanggan', 'no_reg', 'total_tagihan', 'jatuh_tempo', 'keterangan',
    ];

    protected $table = 'template_deskripsi_tagihan';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama', 'konten', 'is_default'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('template_deskripsi_tagihan');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    /**
     * Placeholder `{...}` pada teks yang bukan bagian dari PLACEHOLDERS.
     *
     * @return list<string>
     */
    public static function placeholderTakDikenal(string $konten): array
    {
        preg_match_all('/\{([^{}]*)\}/', $konten, $cocok);

        return array_values(array_unique(array_filter(
            $cocok[1],
            fn (string $nama): bool => ! in_array($nama, self::PLACEHOLDERS, true),
        )));
    }
}
