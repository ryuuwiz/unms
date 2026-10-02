<?php

namespace App\DTO\PaymentGateway;

use App\Models\Invoice;
use App\Models\TransaksiPaymentGateway;

/**
 * Invoice (termasuk yang soft-deleted) dan Transaksi Payment Gateway yang cocok dengan satu
 * pembayaran gateway; transaksi kosong bila hanya cocok lewat nomor invoice.
 */
readonly class TargetPelunasan
{
    public function __construct(
        public ?Invoice $invoice,
        public ?TransaksiPaymentGateway $transaksi,
    ) {}
}
