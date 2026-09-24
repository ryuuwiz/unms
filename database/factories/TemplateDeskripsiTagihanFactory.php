<?php

namespace Database\Factories;

use App\Models\TemplateDeskripsiTagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateDeskripsiTagihan>
 */
class TemplateDeskripsiTagihanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(2, true),
            'konten' => TemplateDeskripsiTagihan::KONTEN_DEFAULT,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
