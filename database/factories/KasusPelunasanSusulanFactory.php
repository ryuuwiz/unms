<?php

namespace Database\Factories;

use App\Enums\AksiPelunasanSusulan;
use App\Models\KasusPelunasanSusulan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KasusPelunasanSusulan>
 */
class KasusPelunasanSusulanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => 'BFINV-'.fake()->unique()->numberBetween(1, 99999).'-'.fake()->unixTime(),
            'alasan' => 'external_id tidak dikenal di sistem.',
            'aksi' => AksiPelunasanSusulan::Dilaporkan,
            'xendit_id' => 'inv_'.fake()->uuid(),
            'koneksi' => 'Xendit Utama',
            'nominal' => fake()->randomElement([150000, 200000, 250000]),
            'dibayar_pada' => now()->subDay(),
        ];
    }

    public function ditangani(?User $penangan = null): static
    {
        return $this->state(fn () => [
            'ditangani_pada' => now(),
            'ditangani_oleh' => $penangan?->id ?? User::factory(),
            'catatan_penanganan' => 'Sudah dikembalikan ke pelanggan.',
        ]);
    }
}
