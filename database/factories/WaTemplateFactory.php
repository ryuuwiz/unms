<?php

namespace Database\Factories;

use App\Enums\Wa\KategoriTemplateWa;
use App\Models\WaTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaTemplate>
 */
class WaTemplateFactory extends Factory
{
    protected $model = WaTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => 'template_'.$this->faker->unique()->slug(2),
            'nama' => 'Template '.$this->faker->words(3, true),
            'kategori' => $this->faker->randomElement(KategoriTemplateWa::cases()),
            'konten' => 'Halo {nama_pelanggan}, ini adalah pesan tagihan {no_invoice} sebesar {total_tagihan}. Salam, {nama_brand}',
            'keterangan' => $this->faker->sentence(),
            'is_aktif' => true,
        ];
    }
}
