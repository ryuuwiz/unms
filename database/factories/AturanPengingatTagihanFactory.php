<?php

namespace Database\Factories;

use App\Enums\Wa\TipePengingatTagihan;
use App\Models\AturanPengingatTagihan;
use App\Models\WaTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AturanPengingatTagihan>
 */
class AturanPengingatTagihanFactory extends Factory
{
    protected $model = AturanPengingatTagihan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_aturan' => 'Aturan '.$this->faker->words(3, true),
            'tipe_pengingat' => TipePengingatTagihan::SebelumJatuhTempo,
            'hari_offset' => 3,
            'jam_eksekusi' => '08:30:00',
            'template_id' => WaTemplate::factory(),
            'kirim_ulang_berkala' => false,
            'interval_hari' => null,
            'is_aktif' => true,
        ];
    }
}
