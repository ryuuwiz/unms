<?php

namespace App\Utils;

class IpNetworkHelper
{
    /**
     * Calculate the suggested IP range (start and end) for a given network and CIDR.
     * Start IP is network + 1 (e.g. .1)
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

        // Network + 1
        $startLong = $networkLong + 1;
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
