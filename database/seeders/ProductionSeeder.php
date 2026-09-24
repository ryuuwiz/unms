<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Run the initial production master data seeds.
     * Seeds essential RBAC, company profile, WhatsApp templates, and billing reminder rules.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PerusahaanSeeder::class,
            WaTemplateSeeder::class,
            AturanPengingatTagihanSeeder::class,
            TemplateDeskripsiTagihanSeeder::class,
        ]);
    }
}
