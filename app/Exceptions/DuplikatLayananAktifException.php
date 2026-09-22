<?php

namespace App\Exceptions;

use Exception;

class DuplikatLayananAktifException extends Exception
{
    public function __construct()
    {
        parent::__construct(
            'Pelanggan ini sudah memiliki Data Registrasi Billing aktif dengan paket yang sama pada router ini. Untuk pemasangan site baru, gunakan paket atau router yang sesuai.'
        );
    }
}
