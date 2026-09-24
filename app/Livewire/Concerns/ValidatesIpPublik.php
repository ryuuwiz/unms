<?php

namespace App\Livewire\Concerns;

use App\Models\IpPublik;
use App\Models\LayananPelanggan;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Aturan validasi bersama form Create/Edit IP Publik. Alamat publik tidak boleh berada di dalam
 * rentang IP Pool router yang sama, karena pool RouterOS bisa membagikannya ke pelanggan lain.
 */
trait ValidatesIpPublik
{
    /**
     * @return array<string, mixed>
     */
    protected function ipPublikRules(?int $ignoreId = null): array
    {
        return [
            'router_id' => ['required', 'integer', 'exists:router,id'],
            'alamat_ip' => [
                'bail', 'required', 'ipv4',
                Rule::unique('ip_publik', 'alamat_ip')->ignore($ignoreId),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $bentrok = $this->router_id ? IpPublik::bentrokDenganPool($this->router_id, (string) $value) : null;
                    if ($bentrok !== null) {
                        $fail($bentrok);
                    }

                    if ($this->router_id && LayananPelanggan::where('router_id', $this->router_id)->where('ip_static', (string) $value)->exists()) {
                        $fail("Alamat {$value} sudah dipakai sebagai IP Statis sebuah layanan pada router yang sama.");
                    }
                },
            ],
            'gateway' => ['required', 'ipv4'],
            'harga_bulanan' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function ipPublikMessages(): array
    {
        return [
            'alamat_ip.required' => 'Alamat IP wajib diisi.',
            'alamat_ip.ipv4' => 'Format alamat IPv4 tidak valid.',
            'alamat_ip.unique' => 'Alamat IP ini sudah terdaftar di inventaris.',
            'gateway.required' => 'Gateway wajib diisi.',
            'gateway.ipv4' => 'Format gateway IPv4 tidak valid.',
            'harga_bulanan.required' => 'Harga bulanan wajib diisi.',
        ];
    }
}
