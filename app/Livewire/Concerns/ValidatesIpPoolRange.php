<?php

namespace App\Livewire\Concerns;

use App\Models\IpPool;
use App\Utils\IpNetworkHelper;
use Closure;

/**
 * Aturan validasi rentang IP Pool bersama untuk form Create/Edit: rentang di dalam
 * network/CIDR, awal <= akhir, dan tidak beririsan dengan pool lain pada router yang sama.
 */
trait ValidatesIpPoolRange
{
    protected function rentangIpAkhirRule(?int $ignorePoolId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignorePoolId): void {
            $error = IpNetworkHelper::rangeError($this->ip_network, (int) $this->cidr, $this->rentang_ip_awal, (string) $value);

            if ($error === null && $this->router_id && filter_var($this->rentang_ip_awal, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $bentrok = IpPool::findOverlapping($this->router_id, $this->rentang_ip_awal, (string) $value, $ignorePoolId);
                $error = $bentrok ? "Rentang IP beririsan dengan IP Pool {$bentrok->nama_pool} pada router yang sama." : null;
            }

            if ($error !== null) {
                $fail($error);
            }
        };
    }
}
