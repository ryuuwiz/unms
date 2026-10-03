<?php

namespace App\Services\PaymentGateway\Drivers;

use App\Contracts\PaymentGateway\PaymentGatewayContract;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

abstract class AbstractPaymentDriver implements PaymentGatewayContract
{
    /**
     * Hitung durasi detik kedaluwarsa payment link berbasis tanggal jatuh tempo (minimal 24 jam / 86400 detik).
     */
    public function hitungDurasiDetik(Invoice $invoice): int
    {
        $minDuration = 86400; // 24 jam
        $due = Carbon::parse($invoice->tanggal_jatuh_tempo)->endOfDay();
        $now = Carbon::now();

        if ($due->isPast() || $due->diffInSeconds($now) < $minDuration) {
            return $minDuration;
        }

        return (int) $due->diffInSeconds($now);
    }

    /**
     * Generate format external ID unik per sesi pembuatan invoice. Akhiran acak karena satu invoice
     * bisa mendapat beberapa link (satu per metode) dalam detik yang sama.
     */
    public function generateExternalId(Invoice $invoice): string
    {
        return sprintf('%s-%s-%s', $invoice->no_invoice, now()->timestamp, Str::lower(Str::random(4)));
    }

    /**
     * Format nomor HP ke format E.164 Indonesia (+628xxxxxxxxxx).
     */
    public static function formatNomorHpE164(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $clean = preg_replace('/[^\d]/', '', $phone);
        if (empty($clean)) {
            return null;
        }

        if (str_starts_with($clean, '08')) {
            $clean = '628'.substr($clean, 2);
        } elseif (str_starts_with($clean, '8')) {
            $clean = '628'.substr($clean, 1);
        }

        return '+'.$clean;
    }

    /**
     * Format nomor HP tanpa tanda plus (628xxxxxxxxxx) untuk gateway yang tidak menerima tanda plus.
     */
    public static function formatNomorHpNumeric(?string $phone): ?string
    {
        $e164 = static::formatNomorHpE164($phone);

        return $e164 ? ltrim($e164, '+') : null;
    }
}
