<?php

namespace App\Livewire\Concerns;

use App\Models\IpPool;
use App\Models\IpPublik;
use App\Models\LayananPelanggan;
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

            if ($error === null) {
                $error = $this->gatewayDalamRentang($this->ip_network, (int) $this->cidr, $this->rentang_ip_awal, (string) $value);
            }

            if ($error === null && $this->router_id) {
                $error = $this->alamatLiteralDalamRentang((int) $this->router_id, $this->rentang_ip_awal, (string) $value);
            }

            if ($error !== null) {
                $fail($error);
            }
        };
    }

    /**
     * IP Network harus alamat network yang sebenarnya (gateway/local-address dihitung dari network + 1).
     */
    protected function ipNetworkRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $long = ip2long((string) $value);
            $cidr = (int) $this->cidr;

            if ($long === false || $cidr < 1 || $cidr > 32) {
                return;
            }

            $mask = ~((1 << (32 - $cidr)) - 1);

            if (($long & $mask) !== $long) {
                $fail('IP Network harus berupa alamat network, bukan alamat host. Untuk CIDR /'.$cidr.' gunakan '.long2ip($long & $mask).'.');
            }
        };
    }

    /**
     * Gateway (network + 1) dipakai sebagai local-address PPP dan tidak boleh berada di rentang pool.
     */
    private function gatewayDalamRentang(string $network, int $cidr, string $awal, string $akhir): ?string
    {
        $networkLong = ip2long($network);
        $start = ip2long($awal);
        $end = ip2long($akhir);

        if ($networkLong === false || $start === false || $end === false || $cidr < 1 || $cidr > 30) {
            return null;
        }

        $gateway = $networkLong + 1;

        return $gateway >= $start && $gateway <= $end
            ? 'Rentang IP tidak boleh memuat gateway '.long2ip($gateway).' (network + 1, dipakai sebagai local-address PPP). Mulai rentang dari '.long2ip($gateway + 1).'.'
            : null;
    }

    /**
     * Rentang pool tidak boleh memuat IP Publik atau ip_static router yang sama: pool RouterOS bisa
     * membagikannya ke pelanggan lain (lihat CONTEXT.md "IP Publik Dedicated").
     */
    private function alamatLiteralDalamRentang(int $routerId, string $awal, string $akhir): ?string
    {
        $start = ip2long($awal);
        $end = ip2long($akhir);

        if ($start === false || $end === false) {
            return null;
        }

        $dalamRentang = fn (?string $ip): bool => $ip !== null && ($long = ip2long($ip)) !== false && $long >= $start && $long <= $end;

        $publik = IpPublik::where('router_id', $routerId)->pluck('alamat_ip')->first($dalamRentang);
        $statis = LayananPelanggan::where('router_id', $routerId)->whereNotNull('ip_static')->pluck('ip_static')->first($dalamRentang);
        $bentrok = $publik ?? $statis;

        return $bentrok ? "Rentang IP memuat alamat {$bentrok} yang sudah dialokasikan sebagai IP Publik/IP Statis pada router yang sama." : null;
    }
}
