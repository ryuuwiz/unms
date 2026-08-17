<?php

namespace App\Models;

use App\Enums\RouterStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $ip_address
 * @property int $api_port
 * @property string $username
 * @property string|null $password
 * @property string|null $description
 * @property RouterStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'ip_address', 'api_port', 'username', 'password', 'description', 'status'])]
#[Hidden(['password'])]
class Router extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => RouterStatus::class,
            'password' => 'encrypted',
            'api_port' => 'integer',
        ];
    }

    /**
     * Get the IP pools associated with the router.
     */
    public function ipPools(): HasMany
    {
        return $this->hasMany(IpPool::class, 'router_id');
    }
}
