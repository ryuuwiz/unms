<?php

namespace Database\Factories;

use App\Enums\GatewayChannel;
use App\Models\ChannelPembayaran;
use App\Models\PengaturanGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelPembayaran>
 */
class ChannelPembayaranFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pengaturan_gateway_id' => fn () => PengaturanGateway::firstOrCreate(
                ['provider' => 'ipaymu'],
                ['nama' => 'iPaymu', 'gateway' => 'ipaymu', 'credentials' => ['va' => '1179000899', 'api_key' => 'SANDBOX-KEY'], 'is_active' => true],
            )->id,
            'tipe' => GatewayChannel::VirtualAccount,
            'kode' => 'bca',
            'fee_admin' => 4000,
            'fee_persen' => false,
            'is_active' => true,
        ];
    }

    public function qris(float $persen = 0.7): static
    {
        return $this->state(['tipe' => GatewayChannel::Qris, 'kode' => 'mpm', 'fee_admin' => $persen, 'fee_persen' => true]);
    }
}
