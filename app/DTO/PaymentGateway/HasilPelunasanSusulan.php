<?php

namespace App\DTO\PaymentGateway;

use App\Enums\AksiPelunasanSusulan;
use App\Models\Invoice;

readonly class HasilPelunasanSusulan
{
    public function __construct(
        public AksiPelunasanSusulan $aksi,
        public string $koneksi,
        public ?PaymentCallbackData $pembayaran = null,
        public ?Invoice $invoice = null,
        public ?string $statusSebelum = null,
        public string $keterangan = '',
    ) {}
}
