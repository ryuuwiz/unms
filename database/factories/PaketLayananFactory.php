<?php

namespace Database\Factories;

use App\Enums\MasaAktifSatuan;
use App\Enums\StatusPaket;
use App\Models\PaketLayanan;
use App\Models\ProfilBandwidth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaketLayanan>
 */
class PaketLayananFactory extends Factory
{
    protected $model = PaketLayanan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_paket' => 'Paket '.fake()->unique()->word().' '.fake()->numerify('##'),
            'profil_bandwidth_id' => ProfilBandwidth::factory(),
            'harga' => fake()->randomElement([75000, 100000, 150000, 200000, 250000, 350000]),
            'masa_aktif_nilai' => 1,
            'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
            'keterangan' => null,
            'status' => StatusPaket::Aktif,
        ];
    }

    /**
     * State untuk paket nonaktif.
     */
    public function nonaktif(): static
    {
        return $this->state(['status' => StatusPaket::Nonaktif]);
    }
}
