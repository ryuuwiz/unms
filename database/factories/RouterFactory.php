<?php

namespace Database\Factories;

use App\Enums\StatusRouter;
use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Router>
 */
class RouterFactory extends Factory
{
    protected $model = Router::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_router' => 'ROUTER-'.fake()->unique()->numerify('###'),
            'ip_address' => fake()->localIpv4(),
            'port' => 8728,
            'username' => 'admin',
            'password_terenkripsi' => 'admin123',
            'deskripsi' => null,
            'status_koneksi' => StatusRouter::Unknown,
            'last_sync_at' => null,
        ];
    }

    /**
     * State untuk router yang online.
     */
    public function online(): static
    {
        return $this->state(['status_koneksi' => StatusRouter::Online]);
    }
}
