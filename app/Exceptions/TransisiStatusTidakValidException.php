<?php

namespace App\Exceptions;

use App\Enums\Ticket\StatusTicket;
use Exception;

class TransisiStatusTidakValidException extends Exception
{
    public function __construct(StatusTicket $statusLama, StatusTicket $statusBaru)
    {
        parent::__construct(
            "Transisi status tiket dari '{$statusLama->label()}' ke '{$statusBaru->label()}' tidak valid sesuai aturan sistem."
        );
    }
}
