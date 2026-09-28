<?php

namespace Database\Factories;

use App\Models\IpPool;
use App\Models\PaketLayanan;
use App\Models\RouterPaket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouterPaket>
 */
class RouterPaketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paket_layanan_id' => PaketLayanan::factory(),
            'ip_pool_id' => IpPool::factory(),
            'router_id' => fn (array $attributes) => IpPool::findOrFail($attributes['ip_pool_id'])->router_id,
            'deskripsi' => null,
        ];
    }
}
