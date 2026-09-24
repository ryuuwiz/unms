<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DevUsersSeeder::class,
            WilayahSeeder::class,
            RouterSeeder::class,
            IpPoolSeeder::class,
            ProfilBandwidthSeeder::class,
            PaketLayananSeeder::class,
            OdpSeeder::class,
            PromoSeeder::class,
            PengaturanPrefixRegistrasiSeeder::class,
            PelangganSeeder::class,
            TicketSeeder::class,
            InventarisSeeder::class,
            PerusahaanSeeder::class,
            SysblasSeeder::class,
            WaTemplateSeeder::class,
            AturanPengingatTagihanSeeder::class,
        ]);
    }
}
