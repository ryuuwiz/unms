<?php

namespace Database\Factories;

use App\Enums\RouterStatus;
use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Router>
 */
class RouterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Router '.$this->faker->words(2, true),
            'ip_address' => $this->faker->ipv4(),
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'password',
            'description' => $this->faker->sentence(),
            'status' => $this->faker->randomElement([RouterStatus::Online, RouterStatus::Offline, RouterStatus::Unknown]),
        ];
    }
}
