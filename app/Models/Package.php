<?php

namespace App\Models;

use App\Enums\PackageStatus;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $download_speed_mbps
 * @property int $upload_speed_mbps
 * @property int $price
 * @property string|null $description
 * @property PackageStatus $status
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'download_speed_mbps',
    'upload_speed_mbps',
    'price',
    'description',
    'status',
])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PackageStatus::class,
            'download_speed_mbps' => 'integer',
            'upload_speed_mbps' => 'integer',
            'price' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Scope filter paket yang berstatus aktif.
     *
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PackageStatus::Active);
    }

    /**
     * Scope pencarian berdasarkan nama paket atau deskripsi.
     *
     * @param  Builder<Package>  $query
     * @return Builder<Package>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $cleanSearch = trim($search);

        return $query->where(function (Builder $q) use ($cleanSearch) {
            $q->where('name', 'like', "%{$cleanSearch}%")
                ->orWhere('description', 'like', "%{$cleanSearch}%");
        });
    }

    /**
     * Cek apakah paket aktif.
     */
    public function isActive(): bool
    {
        return $this->status === PackageStatus::Active;
    }

    /**
     * Label representasi kecepatan bandwidth (misal: "20/10 Mbps" atau "20 Mbps (1:1)").
     */
    public function speedLabel(): string
    {
        if ($this->download_speed_mbps === $this->upload_speed_mbps) {
            return "{$this->download_speed_mbps} Mbps (1:1)";
        }

        return "{$this->download_speed_mbps} / {$this->upload_speed_mbps} Mbps";
    }

    /**
     * Format harga bulanan dalam Rupiah (misal: "Rp 250.000 / bln").
     */
    public function formattedPrice(): string
    {
        return 'Rp '.number_format($this->price, 0, ',', '.').' / bln';
    }
}
