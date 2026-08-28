<?php

namespace Database\Factories;

use App\Enums\Sysblas\SysblasProvider;
use App\Models\Sysblas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sysblas>
 */
class SysblasFactory extends Factory
{
    protected $model = Sysblas::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => 'WABLAS '.$this->faker->words(2, true),
            'provider' => SysblasProvider::Wablas,
            'nomor' => '08'.$this->faker->numerify('##########'),
            'url_api' => 'https://tegal.wablas.com',
            'api_token' => 'token_'.$this->faker->sha1(),
            'api_secret' => 'secret_'.$this->faker->sha1(),
            'limit_per_menit' => 25,
            'is_default' => false,
            'is_aktif' => true,
            'keterangan' => $this->faker->sentence(),
        ];
    }

    public function defaultConnection(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'is_aktif' => true,
        ]);
    }
}
