<?php

namespace App\Models;

use App\Enums\Wa\KategoriTemplateWa;
use Database\Factories\WaTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property KategoriTemplateWa $kategori
 * @property string $konten
 * @property string|null $keterangan
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'kode',
    'nama',
    'kategori',
    'konten',
    'keterangan',
    'is_aktif',
])]
class WaTemplate extends Model
{
    /** @use HasFactory<WaTemplateFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'wa_template';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('wa_template');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kategori' => KategoriTemplateWa::class,
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * @return HasMany<AturanPengingatTagihan, $this>
     */
    public function aturanPengingat(): HasMany
    {
        return $this->hasMany(AturanPengingatTagihan::class, 'template_id');
    }

    /**
     * @param  Builder<WaTemplate>  $query
     * @return Builder<WaTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }

    /**
     * @param  Builder<WaTemplate>  $query
     * @return Builder<WaTemplate>
     */
    public function scopeKategori(Builder $query, KategoriTemplateWa|string $kategori): Builder
    {
        $val = $kategori instanceof KategoriTemplateWa ? $kategori->value : $kategori;

        return $query->where('kategori', $val);
    }

    /**
     * Render konten template dengan substitusi array parameter/placeholder.
     *
     * @param  array<string, mixed>  $params
     */
    public function render(array $params): string
    {
        $text = $this->konten;

        foreach ($params as $key => $value) {
            $placeholder = '{'.trim($key, '{}').'}';
            $strVal = match (true) {
                $value instanceof \BackedEnum => (string) $value->value,
                $value instanceof \UnitEnum => $value->name,
                is_scalar($value) => (string) $value,
                is_null($value) => '',
                default => (string) $value,
            };
            $text = str_replace($placeholder, $strVal, $text);
        }

        return trim($text);
    }
}
