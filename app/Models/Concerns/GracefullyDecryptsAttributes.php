<?php

namespace App\Models\Concerns;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;

trait GracefullyDecryptsAttributes
{
    /**
     * Dekripsi string terenkripsi dengan penanganan kegagalan secara aman.
     *
     * Jika terjadi kegagalan dekripsi (misal rotasi key tanpa previous key atau payload rusak),
     * tangani secara graceful agar tidak menyebabkan 500 error / crash pada View exception renderer.
     *
     * @param  string  $value
     * @return mixed
     */
    public function fromEncryptedString($value)
    {
        try {
            return parent::fromEncryptedString($value);
        } catch (DecryptException $e) {
            Log::warning(sprintf(
                'Gagal mendekripsi atribut terenkripsi pada model %s (ID: %s): %s',
                static::class,
                $this->getKey() ?? 'baru',
                $e->getMessage()
            ));

            return null;
        }
    }
}

