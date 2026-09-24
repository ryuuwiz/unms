<?php

namespace Database\Seeders;

use App\Models\TemplateDeskripsiTagihan;
use Illuminate\Database\Seeder;

class TemplateDeskripsiTagihanSeeder extends Seeder
{
    /**
     * Seed template default deskripsi tagihan gateway bila belum ada.
     */
    public function run(): void
    {
        if (! TemplateDeskripsiTagihan::query()->where('is_default', true)->exists()) {
            TemplateDeskripsiTagihan::create([
                'nama' => 'Default Pembayaran Internet',
                'konten' => TemplateDeskripsiTagihan::KONTEN_DEFAULT,
                'is_default' => true,
            ]);
        }
    }
}
