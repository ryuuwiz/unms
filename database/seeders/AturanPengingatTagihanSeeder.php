<?php

namespace Database\Seeders;

use App\Enums\Wa\TipePengingatTagihan;
use App\Models\AturanPengingatTagihan;
use App\Models\WaTemplate;
use Illuminate\Database\Seeder;

class AturanPengingatTagihanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $h3Template = WaTemplate::where('kode', 'pengingat_tagihan_h3')->first();
        $h1Template = WaTemplate::where('kode', 'pengingat_tagihan_h1')->first();
        $h0Template = WaTemplate::where('kode', 'pengingat_tagihan_h0')->first();
        $tunggakanTemplate = WaTemplate::where('kode', 'pengingat_tagihan_tunggakan')->first();

        if (! $h3Template || ! $h1Template || ! $h0Template || ! $tunggakanTemplate) {
            $this->call(WaTemplateSeeder::class);
            $h3Template = WaTemplate::where('kode', 'pengingat_tagihan_h3')->first();
            $h1Template = WaTemplate::where('kode', 'pengingat_tagihan_h1')->first();
            $h0Template = WaTemplate::where('kode', 'pengingat_tagihan_h0')->first();
            $tunggakanTemplate = WaTemplate::where('kode', 'pengingat_tagihan_tunggakan')->first();
        }

        $aturanList = [
            [
                'nama_aturan' => 'Pengingat H-3 Tagihan Baru',
                'tipe_pengingat' => TipePengingatTagihan::SebelumJatuhTempo,
                'hari_offset' => 3,
                'jam_eksekusi' => '08:30:00',
                'template_id' => $h3Template->id,
                'kirim_ulang_berkala' => false,
                'interval_hari' => null,
                'is_aktif' => true,
            ],
            [
                'nama_aturan' => 'Pengingat H-1 Jatuh Tempo Besok',
                'tipe_pengingat' => TipePengingatTagihan::SebelumJatuhTempo,
                'hari_offset' => 1,
                'jam_eksekusi' => '08:30:00',
                'template_id' => $h1Template->id,
                'kirim_ulang_berkala' => false,
                'interval_hari' => null,
                'is_aktif' => true,
            ],
            [
                'nama_aturan' => 'Pengingat Hari-H Jatuh Tempo',
                'tipe_pengingat' => TipePengingatTagihan::HariH,
                'hari_offset' => 0,
                'jam_eksekusi' => '08:30:00',
                'template_id' => $h0Template->id,
                'kirim_ulang_berkala' => false,
                'interval_hari' => null,
                'is_aktif' => true,
            ],
            [
                'nama_aturan' => 'Peringatan Tunggakan H+3 & Isolir',
                'tipe_pengingat' => TipePengingatTagihan::SetelahJatuhTempo,
                'hari_offset' => 3,
                'jam_eksekusi' => '09:00:00',
                'template_id' => $tunggakanTemplate->id,
                'kirim_ulang_berkala' => true,
                'interval_hari' => 3,
                'is_aktif' => true,
            ],
        ];

        foreach ($aturanList as $aturan) {
            AturanPengingatTagihan::updateOrCreate(
                ['nama_aturan' => $aturan['nama_aturan']],
                $aturan
            );
        }
    }
}
