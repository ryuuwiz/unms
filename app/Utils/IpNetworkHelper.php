<?php

namespace App\Utils;

class IpNetworkHelper
{
    /**
     * Validasi rentang IP berada di dalam network/CIDR dan awal tidak melebihi akhir.
     * Mengembalikan pesan galat, atau null jika valid (atau input belum berupa IPv4 valid).
     */
    public static function rangeError(string $network, int $cidr, string $awal, string $akhir): ?string
    {
        $networkLong = ip2long($network);
        $awalLong = ip2long($awal);
        $akhirLong = ip2long($akhir);

        if ($networkLong === false || $awalLong === false || $akhirLong === false || $cidr < 1 || $cidr > 32) {
            return null;
        }

        $mask = ~((1 << (32 - $cidr)) - 1);
        $first = $networkLong & $mask;
        $last = $first | (~$mask);

        if ($awalLong > $akhirLong) {
            return 'Rentang IP awal tidak boleh lebih besar dari rentang IP akhir.';
        }

        if ($awalLong < $first || $akhirLong > $last) {
            return 'Rentang IP harus berada di dalam network '.long2ip($first)."/{$cidr}.";
        }

        return null;
    }

    /**
     * Calculate the suggested IP range (start and end) for a given network and CIDR.
     * Start IP is network + 2 (e.g. .2): network + 1 dipakai sebagai gateway/local-address PPP dan tidak boleh masuk pool.
     * End IP is broadcast - 1 (e.g. .254)
     *
     * @param  string  $network  e.g., '192.168.88.0'
     * @param  int  $cidr  e.g., 24
     * @return array{start: string|null, end: string|null}
     */
    public static function calculateSuggestedRange(string $network, int $cidr): array
    {
        // Simple validation
        if (! filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $cidr < 0 || $cidr > 32) {
            return ['start' => null, 'end' => null];
        }

        if ($cidr === 32 || $cidr === 31) {
            return ['start' => null, 'end' => null];
        }

        $ipLong = ip2long($network);
        $mask = ~((1 << (32 - $cidr)) - 1);

        $networkLong = $ipLong & $mask;
        $broadcastLong = $networkLong | (~$mask);

        // Network + 2 (network + 1 adalah gateway)
        $startLong = $networkLong + 2;
        // Broadcast - 1
        $endLong = $broadcastLong - 1;

        if ($startLong > $endLong) {
            return ['start' => null, 'end' => null];
        }

        return [
            'start' => long2ip($startLong),
            'end' => long2ip($endLong),
        ];
    }
}
