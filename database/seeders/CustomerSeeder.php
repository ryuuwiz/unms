<?php

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $salesUser = User::where('email', 'sales@example.com')->first()
            ?? User::first();

        $adminUser = User::where('email', 'admin@example.com')->first()
            ?? User::first();

        if (! $salesUser || ! $adminUser) {
            return;
        }

        $customersData = [
            [
                'name' => 'Budi Santoso',
                'email' => 'budi.santoso@gmail.com',
                'phone' => '081234567890',
                'address' => 'Jl. Kebon Jeruk Raya No. 12, RT 03/RW 05, Jakarta Barat',
                'installation_address' => 'Jl. Kebon Jeruk Raya No. 12, Jakarta Barat (Rumah Tinggal)',
                'lat' => -6.1925000,
                'lng' => 106.7725000,
                'status' => CustomerStatus::Active,
                'created_by' => $salesUser->id,
            ],
            [
                'name' => 'Siti Nurhaliza',
                'email' => 'siti.nurhaliza@yahoo.com',
                'phone' => '085712345678',
                'address' => 'Jl. Tebet Barat Dalam VII No. 45, Jakarta Selatan',
                'installation_address' => 'Jl. Tebet Barat Dalam VII No. 45, Jakarta Selatan',
                'lat' => -6.2384000,
                'lng' => 106.8521000,
                'status' => CustomerStatus::Active,
                'created_by' => $salesUser->id,
            ],
            [
                'name' => 'PT Sumber Makmur Digital',
                'email' => 'admin@sumbermakmur.co.id',
                'phone' => '081399887766',
                'address' => 'Gedung Wisma Mulia Lt. 15, Jl. Gatot Subroto No. 42, Jakarta Selatan',
                'installation_address' => 'Gedung Wisma Mulia Lt. 15, Jl. Gatot Subroto No. 42, Jakarta Selatan (Server Room)',
                'lat' => -6.2361000,
                'lng' => 106.8286000,
                'status' => CustomerStatus::Active,
                'created_by' => $adminUser->id,
            ],
            [
                'name' => 'Hendra Gunawan (Warung Kopi Senja)',
                'email' => 'kopisenja.jkt@gmail.com',
                'phone' => '087811223344',
                'address' => 'Jl. Kemang Raya No. 88, Mampang Prapatan, Jakarta Selatan',
                'installation_address' => 'Jl. Kemang Raya No. 88, Jakarta Selatan (Ruko 2 Lantai)',
                'lat' => -6.2625000,
                'lng' => 106.8152000,
                'status' => CustomerStatus::Active,
                'created_by' => $salesUser->id,
            ],
            [
                'name' => 'Agus Prasetyo',
                'email' => 'agus.prasetyo88@gmail.com',
                'phone' => '082155667788',
                'address' => 'Perumahan Grand Galaxy City Blok AF No. 10, Bekasi Selatan',
                'installation_address' => 'Perumahan Grand Galaxy City Blok AF No. 10, Bekasi Selatan',
                'lat' => -6.2687000,
                'lng' => 106.9748000,
                'status' => CustomerStatus::Active,
                'created_by' => $salesUser->id,
            ],
            [
                'name' => 'Dewi Anggraini',
                'email' => 'dewi.anggraini@outlook.com',
                'phone' => '081833445566',
                'address' => 'Jl. Margonda Raya No. 200, Beji, Depok',
                'installation_address' => 'Apartemen Margonda Residence Tower 2 Unit 12B, Depok',
                'lat' => -6.3725000,
                'lng' => 106.8327000,
                'status' => CustomerStatus::Active,
                'created_by' => $adminUser->id,
            ],
            [
                'name' => 'CV Karya Cipta Mandiri',
                'email' => 'office@karyacipta.net',
                'phone' => '081288990011',
                'address' => 'Kawasan Industri Pulogadung Blok B No. 7, Jakarta Timur',
                'installation_address' => 'Kawasan Industri Pulogadung Blok B No. 7, Jakarta Timur (Kantor Operasional)',
                'lat' => -6.1956000,
                'lng' => 106.9123000,
                'status' => CustomerStatus::Active,
                'created_by' => $salesUser->id,
            ],
            [
                'name' => 'Rina Marlina',
                'email' => 'rina.marlina@gmail.com',
                'phone' => '085277889900',
                'address' => 'Jl. Bintaro Utama Sektor 3A No. 18, Tangerang Selatan',
                'installation_address' => 'Jl. Bintaro Utama Sektor 3A No. 18, Tangerang Selatan',
                'lat' => -6.2814000,
                'lng' => 106.7268000,
                'status' => CustomerStatus::Active,
                'created_by' => $salesUser->id,
            ],
            [
                'name' => 'Ferry Irawan',
                'email' => 'ferry.irawan99@yahoo.com',
                'phone' => '081344556677',
                'address' => 'Jl. Boulevard Kelapa Gading Blok M No. 5, Jakarta Utara',
                'installation_address' => 'Jl. Boulevard Kelapa Gading Blok M No. 5, Jakarta Utara (Ruko Lt. 1)',
                'lat' => -6.1582000,
                'lng' => 106.9084000,
                'status' => CustomerStatus::Inactive,
                'created_by' => $salesUser->id,
            ],
            [
                'name' => 'Klinik Medika Sehat',
                'email' => 'info@medikasehat.co.id',
                'phone' => '081266778899',
                'address' => 'Jl. Cempaka Putih Tengah No. 22, Jakarta Pusat',
                'installation_address' => 'Jl. Cempaka Putih Tengah No. 22, Jakarta Pusat (Bagian Resepsionis)',
                'lat' => -6.1795000,
                'lng' => 106.8712000,
                'status' => CustomerStatus::Active,
                'created_by' => $adminUser->id,
            ],
        ];

        foreach ($customersData as $data) {
            $normalizedPhone = Customer::normalizePhone($data['phone']);
            $data['phone'] = $normalizedPhone;

            if (! Customer::where('phone', $normalizedPhone)->exists()) {
                Customer::create($data);
            }
        }
    }
}
