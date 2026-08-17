<?php

namespace Database\Factories;

use App\Enums\DiskonTipe;
use App\Enums\JenisPromo;
use App\Models\Promo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promo>
 */
class PromoFactory extends Factory
{
    protected $model = Promo::class;

    public function definition(): array
    {
        return [
            'kode_promo' => 'PROMO-'.strtoupper($this->faker->bothify('??##')),
            'nama_promo' => 'Promo '.$this->faker->words(2, true),
            'jenis' => JenisPromo::Diskon,
            'deskripsi' => $this->faker->sentence(),
            'aturan' => $this->faker->paragraph(),
            'bayar_bulan' => null,
            'bonus_bulan' => null,
            'diskon_tipe' => DiskonTipe::Nominal,
            'diskon_nilai' => 25000,
            'minimal_nominal_invoice' => 100000,
            'kuota_global' => 100,
            'kuota_per_pelanggan' => 1,
            'terpakai_global' => 0,
            'berlaku_dari' => now()->subDays(5),
            'berlaku_sampai' => now()->addMonths(2),
            'aktif' => true,
            'tampil_ke_customer' => true,
        ];
    }
}
