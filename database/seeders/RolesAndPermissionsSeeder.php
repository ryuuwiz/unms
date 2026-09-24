<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Mendefinisikan SEMUA permissions untuk seluruh modul (termasuk modul future),
     * sehingga tidak perlu update seeder lagi saat fase berikutnya dibangun.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ─────────────────────────────────────────────────────
        // DEFINISI PERMISSIONS — dikelompokkan per modul
        // ─────────────────────────────────────────────────────

        // Pelanggan
        $p = [];
        foreach ([
            // Pelanggan
            'pelanggan.lihat', 'pelanggan.buat', 'pelanggan.ubah', 'pelanggan.hapus',
            'pelanggan.lihat_ktp', 'pelanggan.lihat_dokumen', 'pelanggan.unggah_dokumen', 'pelanggan.hapus_dokumen',
            // Layanan Pelanggan
            'layanan_pelanggan.lihat', 'layanan_pelanggan.buat', 'layanan_pelanggan.ubah', 'layanan_pelanggan.hapus',
            'layanan_pelanggan.lihat_ppp_password', 'layanan_pelanggan.aktivasi',
            // Invoice & Pembayaran (Fase 2)
            'invoice.lihat', 'invoice.buat', 'invoice.hapus', 'invoice.cetak',
            'siklus_tagihan.ubah',
            'pembayaran.catat', 'pembayaran.lihat',
            // Router & IP Pool
            'router.lihat', 'router.buat', 'router.ubah', 'router.hapus',
            'router.provision', 'router.sync',
            'ip_pool.lihat', 'ip_pool.buat', 'ip_pool.ubah', 'ip_pool.hapus',
            'ip_publik.lihat', 'ip_publik.buat', 'ip_publik.ubah', 'ip_publik.hapus',
            // ODP
            'odp.lihat', 'odp.buat', 'odp.ubah', 'odp.hapus',
            // Paket Layanan & Profil Bandwidth
            'paket_layanan.lihat', 'paket_layanan.buat', 'paket_layanan.ubah', 'paket_layanan.hapus',
            'profil_bandwidth.lihat', 'profil_bandwidth.buat', 'profil_bandwidth.ubah', 'profil_bandwidth.hapus',
            // Wilayah
            'wilayah.lihat', 'wilayah.buat', 'wilayah.ubah', 'wilayah.hapus',
            // Promo (Fase 3)
            'promo.lihat', 'promo.buat', 'promo.ubah', 'promo.hapus',
            // Ticket (Fase 5)
            'ticket.lihat', 'ticket.buat', 'ticket.ubah', 'ticket.hapus', 'ticket.assign',
            // Laporan
            'laporan.lihat', 'laporan.ekspor',
            // Pengguna & Peran
            'pengguna.lihat', 'pengguna.buat', 'pengguna.ubah', 'pengguna.hapus',
            'peran.lihat', 'peran.buat', 'peran.ubah', 'peran.hapus',
            // WA Gateway (Fase 5)
            'wa_gateway.lihat', 'wa_gateway.buat', 'wa_gateway.ubah', 'wa_gateway.hapus',
            // Prefix Registrasi
            'prefix_registrasi.lihat', 'prefix_registrasi.buat', 'prefix_registrasi.ubah',
            // Payment Gateway
            'payment_gateway.lihat', 'payment_gateway.buat', 'payment_gateway.ubah', 'payment_gateway.hapus',
            // Media Library (termasuk Storage & S3 Monitoring)
            'media_library.lihat', 'media_library.hapus', 'media_library.unggah',
        ] as $permName) {
            $p[$permName] = Permission::firstOrCreate(['name' => $permName]);
        }

        // ─────────────────────────────────────────────────────
        // DEFINISI ROLES
        // ─────────────────────────────────────────────────────

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $salesRole = Role::firstOrCreate(['name' => 'sales']);
        $nocRole = Role::firstOrCreate(['name' => 'noc']);
        $teknisiRole = Role::firstOrCreate(['name' => 'teknisi']);
        $customerServiceRole = Role::firstOrCreate(['name' => 'customer_service']);

        // super_admin memiliki SEMUA permissions + bypass via Gate::before() di AppServiceProvider.
        $superAdmin->syncPermissions(Permission::all());

        // admin: operasional harian — semua kecuali router provision/sync, wilayah config, WA gateway
        $adminRole->syncPermissions([
            $p['pelanggan.lihat'], $p['pelanggan.buat'], $p['pelanggan.ubah'], $p['pelanggan.hapus'],
            $p['pelanggan.lihat_ktp'], $p['pelanggan.lihat_dokumen'], $p['pelanggan.unggah_dokumen'], $p['pelanggan.hapus_dokumen'],
            $p['layanan_pelanggan.lihat'], $p['layanan_pelanggan.buat'], $p['layanan_pelanggan.ubah'], $p['layanan_pelanggan.hapus'],
            $p['layanan_pelanggan.aktivasi'],
            $p['invoice.lihat'], $p['invoice.buat'], $p['invoice.hapus'], $p['invoice.cetak'],
            $p['siklus_tagihan.ubah'],
            $p['pembayaran.catat'], $p['pembayaran.lihat'],
            $p['router.lihat'], $p['router.buat'], $p['router.ubah'], $p['router.hapus'],
            $p['ip_pool.lihat'], $p['ip_pool.buat'], $p['ip_pool.ubah'], $p['ip_pool.hapus'],
            $p['ip_publik.lihat'], $p['ip_publik.buat'], $p['ip_publik.ubah'], $p['ip_publik.hapus'],
            $p['odp.lihat'], $p['odp.buat'], $p['odp.ubah'], $p['odp.hapus'],
            $p['paket_layanan.lihat'], $p['paket_layanan.buat'], $p['paket_layanan.ubah'], $p['paket_layanan.hapus'],
            $p['profil_bandwidth.lihat'], $p['profil_bandwidth.buat'], $p['profil_bandwidth.ubah'], $p['profil_bandwidth.hapus'],
            $p['wilayah.lihat'],
            $p['promo.lihat'], $p['promo.buat'], $p['promo.ubah'], $p['promo.hapus'],
            $p['ticket.lihat'], $p['ticket.buat'], $p['ticket.ubah'], $p['ticket.hapus'], $p['ticket.assign'],
            $p['laporan.lihat'], $p['laporan.ekspor'],
            $p['pengguna.lihat'], $p['pengguna.buat'], $p['pengguna.ubah'],
            $p['prefix_registrasi.lihat'], $p['prefix_registrasi.buat'], $p['prefix_registrasi.ubah'],
        ]);

        // sales: akuisisi pelanggan baru & tiket pemasangan
        $salesRole->syncPermissions([
            $p['pelanggan.lihat'], $p['pelanggan.buat'], $p['pelanggan.ubah'],
            $p['pelanggan.lihat_ktp'], $p['pelanggan.lihat_dokumen'], $p['pelanggan.unggah_dokumen'], $p['pelanggan.hapus_dokumen'],
            $p['layanan_pelanggan.lihat'],
            $p['paket_layanan.lihat'],
            $p['wilayah.lihat'],
            $p['ticket.lihat'], $p['ticket.buat'], $p['ticket.ubah'],
        ]);

        // noc: jaringan, router, ODP, tiket gangguan
        $nocRole->syncPermissions([
            $p['pelanggan.lihat'],
            $p['layanan_pelanggan.lihat'], $p['layanan_pelanggan.ubah'], $p['layanan_pelanggan.aktivasi'],
            $p['layanan_pelanggan.lihat_ppp_password'],
            $p['router.lihat'], $p['router.buat'], $p['router.ubah'], $p['router.hapus'],
            $p['router.provision'], $p['router.sync'],
            $p['ip_pool.lihat'], $p['ip_pool.buat'], $p['ip_pool.ubah'], $p['ip_pool.hapus'],
            $p['ip_publik.lihat'], $p['ip_publik.buat'], $p['ip_publik.ubah'], $p['ip_publik.hapus'],
            $p['odp.lihat'], $p['odp.buat'], $p['odp.ubah'], $p['odp.hapus'],
            $p['profil_bandwidth.lihat'], $p['profil_bandwidth.buat'], $p['profil_bandwidth.ubah'], $p['profil_bandwidth.hapus'],
            $p['paket_layanan.lihat'],
            $p['ticket.lihat'], $p['ticket.buat'], $p['ticket.ubah'], $p['ticket.hapus'], $p['ticket.assign'],
        ]);

        // teknisi: hanya tiket yang di-assign + lihat data yang relevan
        $teknisiRole->syncPermissions([
            $p['pelanggan.lihat'],
            $p['layanan_pelanggan.lihat'], $p['layanan_pelanggan.lihat_ppp_password'],
            $p['ticket.lihat'], $p['ticket.ubah'],
        ]);

        // customer_service: sign-off tiket pemasangan sisi layanan pelanggan, tidak menyentuh jaringan
        $customerServiceRole->syncPermissions([
            $p['pelanggan.lihat'],
            $p['layanan_pelanggan.lihat'],
            $p['invoice.lihat'],
            $p['pembayaran.lihat'],
            $p['ticket.lihat'], $p['ticket.buat'], $p['ticket.ubah'],
        ]);
    }
}
