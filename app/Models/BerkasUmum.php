<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Anchor kosong untuk berkas yang diunggah staf lewat Media Library tanpa terkait
 * record bisnis manapun (bukan foto tiket, KTP pelanggan, dll). Spatie MediaLibrary
 * mewajibkan setiap media punya owner polimorfik -- model ini ada semata sebagai
 * owner itu, lihat ADR-0047.
 *
 * @property int $id
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $pengunggah
 */
#[Fillable(['uploaded_by'])]
class BerkasUmum extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'berkas_umum';

    /**
     * @return BelongsTo<User, $this>
     */
    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
