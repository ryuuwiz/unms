<?php

namespace Database\Factories;

use App\Enums\PackageStatus;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $download = fake()->randomElement([10, 20, 30, 50, 100]);
        $upload = fake()->randomElement([$download, (int) round($download / 2)]);

        return [
            'name' => 'Paket '.fake()->unique()->words(2, true).' '.$download.' Mbps',
            'download_speed_mbps' => $download,
            'upload_speed_mbps' => $upload,
            'price' => fake()->randomElement([150000, 200000, 250000, 350000, 500000, 750000]),
            'description' => fake()->optional()->sentence(),
            'status' => PackageStatus::Active,
        ];
    }

    /**
     * Indicate that the package is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PackageStatus::Inactive,
        ]);
    }
}
