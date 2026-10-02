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

    /**
     * Alamat pertama dan terakhir (sebagai long) dari `a-b`, alamat tunggal, atau `alamat/cidr` (format `ranges`
     * RouterOS dan `address` interface); null bila tidak valid.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function batasRentang(string $rentang): ?array
    {
        if (str_contains($rentang, '/')) {
            [$ip, $cidr] = explode('/', $rentang, 2);
            $dari = ip2long($ip);

            if ($dari === false || ! ctype_digit($cidr) || (int) $cidr > 32) {
                return null;
            }

            $host = (1 << (32 - (int) $cidr)) - 1;

            return [$dari & ~$host, ($dari & ~$host) | $host];
        }

        [$dari, $sampai] = array_map(ip2long(...), array_pad(explode('-', $rentang, 2), 2, $rentang));

        return $dari === false || $sampai === false ? null : [$dari, $sampai];
    }

    /**
     * @param  array{0: int, 1: int}|null  $a
     * @param  array{0: int, 1: int}  $b
     */
    public static function beririsan(?array $a, array $b): bool
    {
        return $a !== null && $a[0] <= $b[1] && $b[0] <= $a[1];
    }
}
