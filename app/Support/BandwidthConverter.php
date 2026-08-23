<?php

namespace App\Support;

class BandwidthConverter
{
    /**
     * Faktor pengali standar biner: 1 Mbps = 1024 * 1024 = 1.048.576 bps (Mebibits).
     */
    public const BITS_PER_MBPS = 1048576;

    /**
     * Overhead PPPoE 2%: kompensasi header PPPoE (8 byte) + TCP/IP encapsulation.
     * Contoh: 50 Mbps × 1.048.576 × 1,02 = 53.477.376 bps.
     */
    public const PPPOE_OVERHEAD_FACTOR = 1.02;

    /**
     * Konversi nilai Mbps ke bits per second (bps) numerik murni, termasuk 2% overhead PPPoE.
     */
    public static function mbpsToBps(int $mbps): int
    {
        return (int) round($mbps * self::BITS_PER_MBPS * self::PPPOE_OVERHEAD_FACTOR);
    }

    /**
     * Konversi nilai bps murni ke Mbps dengan presisi 2 desimal.
     */
    public static function bpsToMbps(int|float $bps): float
    {
        return round($bps / self::BITS_PER_MBPS, 2);
    }

    /**
     * Format nilai bps ke representasi string yang mudah dibaca manusia (bps, Kbps, Mbps, Gbps).
     */
    public static function formatHumanReadable(int|float $bps): string
    {
        if ($bps >= 1073741824) {
            return round($bps / 1073741824, 2).' Gbps';
        }

        if ($bps >= self::BITS_PER_MBPS) {
            return round($bps / self::BITS_PER_MBPS, 2).' Mbps';
        }

        if ($bps >= 1024) {
            return round($bps / 1024, 2).' Kbps';
        }

        return "{$bps} bps";
    }
}
