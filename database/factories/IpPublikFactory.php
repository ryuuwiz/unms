<?php

namespace Database\Factories;

use App\Models\IpPublik;
use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpPublik>
 */
class IpPublikFactory extends Factory
{
    protected $model = IpPublik::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $host = fake()->unique()->numberBetween(2, 250);

        return [
            'router_id' => Router::factory(),
            'alamat_ip' => "203.0.113.{$host}",
            'gateway' => '203.0.113.1',
            'harga_bulanan' => 50000,
            'harga_ditagih' => null,
            'layanan_pelanggan_id' => null,
            'keterangan' => null,
        ];
    }
}
