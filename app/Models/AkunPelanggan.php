<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Model untuk akun portal pelanggan — guard terpisah diimplementasi di Fase 3.
 *
 * @property int $id
 * @property int $pelanggan_id
 * @property string $email
 * @property string $password
 * @property Carbon|null $email_verified_at
 */
#[Fillable(['pelanggan_id', 'email', 'password', 'email_verified_at'])]
class AkunPelanggan extends Authenticatable
{
    protected $table = 'akun_pelanggan';

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relasi ke pelanggan pemilik akun ini.
     *
     * @return BelongsTo<Pelanggan, $this>
     */
    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }
}
