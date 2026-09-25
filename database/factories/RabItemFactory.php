<?php

namespace Database\Factories;

use App\Enums\Ticket\DivisiTicket;
use App\Models\RabItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RabItem>
 */
class RabItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'periode' => now()->startOfMonth()->toDateString(),
            'uraian' => fake()->sentence(3),
            'qty' => fake()->numberBetween(1, 10),
            'harga' => fake()->numberBetween(10, 500) * 1000,
            'divisi' => fake()->randomElement(DivisiTicket::cases()),
            'divisi_lainnya' => null,
        ];
    }
}
