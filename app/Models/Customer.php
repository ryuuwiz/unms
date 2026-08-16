<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $customer_code
 * @property string $name
 * @property string|null $email
 * @property string $phone
 * @property string|null $address
 * @property string $installation_address
 * @property float|null $lat
 * @property float|null $lng
 * @property CustomerStatus $status
 * @property int $created_by
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $creator
 */
#[Fillable([
    'customer_code',
    'name',
    'email',
    'phone',
    'address',
    'installation_address',
    'lat',
    'lng',
    'status',
    'created_by',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->customer_code)) {
                $customer->customer_code = static::generateNextCustomerCode();
            }

            if (! empty($customer->phone)) {
                $customer->phone = static::normalizePhone($customer->phone);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'lat' => 'float',
            'lng' => 'float',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke user pembuat data pelanggan.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope filter pelanggan yang berstatus aktif.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::Active);
    }

    /**
     * Scope filter pelanggan berdasarkan pembuat data (sales owner).
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeCreatedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope pencarian berdasarkan nama, no. HP, atau kode pelanggan.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $cleanSearch = trim($search);

        return $query->where(function (Builder $q) use ($cleanSearch) {
            $q->where('name', 'like', "%{$cleanSearch}%")
                ->orWhere('phone', 'like', "%{$cleanSearch}%")
                ->orWhere('customer_code', 'like', "%{$cleanSearch}%")
                ->orWhere('email', 'like', "%{$cleanSearch}%");
        });
    }

    /**
     * Cek apakah pelanggan aktif.
     */
    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    /**
     * Normalisasi nomor HP Indonesia ke format standar 628xxxxxxxxxx.
     */
    public static function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        if (str_starts_with($cleaned, '+62')) {
            return substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '08')) {
            return '628'.substr($cleaned, 2);
        }

        if (str_starts_with($cleaned, '628')) {
            return $cleaned;
        }

        if (str_starts_with($cleaned, '8')) {
            return '62'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Generate sequential unik 6-digit customer code (e.g. CUST-000001).
     */
    public static function generateNextCustomerCode(): string
    {
        return DB::transaction(function () {
            $latest = static::withTrashed()
                ->lockForUpdate()
                ->where('customer_code', 'like', 'CUST-%')
                ->orderByRaw('CAST(SUBSTRING(customer_code, 6) AS UNSIGNED) DESC')
                ->first();

            $nextNumber = 1;

            if ($latest && preg_match('/^CUST-(\d+)$/', $latest->customer_code, $matches)) {
                $nextNumber = ((int) $matches[1]) + 1;
            }

            return sprintf('CUST-%06d', $nextNumber);
        });
    }
}
