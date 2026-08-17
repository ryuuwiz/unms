<?php

namespace Database\Factories;

use App\Enums\StatusPelanggan;
use App\Enums\TipePelanggan;
use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pelanggan>
 */
class PelangganFactory extends Factory
{
    protected $model = Pelanggan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipe_pelanggan' => fake()->randomElement(TipePelanggan::cases()),
            'nama_depan' => fake()->firstName(),
            'nama_belakang' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'no_hp' => '628'.fake()->numerify('##########'),
            'telepon_rumah' => null,
            'alamat_lengkap' => fake()->address(),
            'latitude' => fake()->latitude(-8, -5),
            'longitude' => fake()->longitude(106, 115),
            'status' => StatusPelanggan::Aktif,
            'dibuat_oleh' => User::factory(),
        ];
    }

    /**
     * State untuk pelanggan prospek.
     */
    public function prospek(): static
    {
        return $this->state(['status' => StatusPelanggan::Prospek]);
    }

    /**
     * State untuk pelanggan tidak aktif.
     */
    public function tidakAktif(): static
    {
        return $this->state(['status' => StatusPelanggan::TidakAktif]);
    }
}
