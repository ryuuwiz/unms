<?php

namespace Database\Factories;

use App\Models\PengaturanSiklusTagihan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PengaturanSiklusTagihan>
 */
class PengaturanSiklusTagihanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hari_jatuh_tempo' => 10,
            'hari_terbit_invoice' => 24,
        ];
    }
}
