<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class MikrotikException extends Exception
{
    /**
     * Galat koneksi layak dicoba lagi; galat konfigurasi/data (profil tidak valid, Secret Manual NOC
     * bernama sama) tidak. MikrotikService membungkus semua galat jadi MikrotikException, jadi galat
     * koneksi dicari di rantai getPrevious().
     */
    public static function bisaDicobaLagi(Throwable $e): bool
    {
        for ($galat = $e; $galat !== null; $galat = $galat->getPrevious()) {
            if ($galat instanceof MikrotikConnectionException || ! $galat instanceof self) {
                return true;
            }
        }

        return false;
    }
}
