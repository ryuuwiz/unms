<?php

use App\Models\WaTemplate;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seeder tidak menjangkau DB produksi -- lihat ADR-0056. firstOrCreate agar hasil
     * edit admin pada template ini tidak tertimpa.
     */
    public function up(): void
    {
        $template = WaTemplateSeeder::invoiceTerbit();

        WaTemplate::firstOrCreate(['kode' => $template['kode']], $template);
    }

    public function down(): void
    {
        WaTemplate::where('kode', 'invoice_terbit')->delete();
    }
};
