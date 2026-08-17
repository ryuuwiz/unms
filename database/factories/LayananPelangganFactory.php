<?php

namespace Database\Factories;

use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<LayananPelanggan>
 */
class LayananPelangganFactory extends Factory
{
    protected $model = LayananPelanggan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mulai = Carbon::now()->subDays(fake()->numberBetween(0, 60));

        return [
            'pelanggan_id' => Pelanggan::factory(),
            'paket_layanan_id' => PaketLayanan::factory(),
            'router_id' => Router::factory(),
            'ppp_username' => 'ppp-'.fake()->unique()->numerify('######'),
            'ppp_password_terenkripsi' => fake()->password(8, 16),
            'jenis_koneksi' => JenisKoneksi::Pppoe,
            'status' => StatusLayanan::Aktif,
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_expired' => $mulai->copy()->addMonth()->toDateString(),
        ];
    }

    /**
     * State untuk layanan yang masih dalam proses pemasangan.
     */
    public function proses(): static
    {
        return $this->state(['status' => StatusLayanan::Proses]);
    }

    /**
     * State untuk layanan yang di-suspend.
     */
    public function suspend(): static
    {
        return $this->state([
            'status' => StatusLayanan::Suspend,
            'tanggal_expired' => Carbon::yesterday()->toDateString(),
        ]);
    }
}
