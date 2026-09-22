<?php

namespace Database\Factories;

use App\Models\PengaturanPrefixRegistrasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PengaturanPrefixRegistrasi>
 */
class PengaturanPrefixRegistrasiFactory extends Factory
{
    protected $model = PengaturanPrefixRegistrasi::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->lexify('??')),
            'nama' => fake()->company(),
            'is_active' => true,
        ];
    }

    /**
     * State untuk prefix nonaktif.
     */
    public function nonaktif(): static
    {
        return $this->state(['is_active' => false]);
    }
}
