<?php

namespace Database\Seeders;

use App\Enums\DiskonTipe;
use App\Enums\JenisKoneksi;
use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusOdpPort;
use App\Enums\StatusPelanggan;
use App\Enums\TipePelanggan;
use App\Models\AkunPelanggan;
use App\Models\Invoice;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\OdpPort;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Perumahan;
use App\Models\Promo;
use App\Models\PromoPenggunaan;
use App\Models\Router;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PelangganSeeder extends Seeder
{
    /**
     * Seed master pelanggan, akun portal, layanan internet, invoice, dan pembayaran.
     */
    public function run(): void
    {
        $admin = User::first() ?? User::factory()->create();
        $router = Router::where('ip_address', '192.168.80.92')->first() ?? Router::first() ?? Router::factory()->create();
        $perumahans = Perumahan::all();
        $paketList = PaketLayanan::where('status', 'aktif')->get();
        $promoDiskon = Promo::where('kode_promo', 'DISKON20')->first();
        $promoHemat = Promo::where('kode_promo', 'HEMAT50RB')->first();

        // Pastikan pool router tersedia secara idempoten
        $poolRumah = IpPool::where('router_id', $router->id)->where('nama_pool', 'Pool-Rumah')->first();
        $poolBisnis = IpPool::where('router_id', $router->id)->where('nama_pool', 'Pool-Bisnis')->first();

        if (! $poolRumah || ! $poolBisnis) {
            $this->call(IpPoolSeeder::class);
            $poolRumah = IpPool::where('router_id', $router->id)->where('nama_pool', 'Pool-Rumah')->first();
            $poolBisnis = IpPool::where('router_id', $router->id)->where('nama_pool', 'Pool-Bisnis')->first();
        }

        if ($perumahans->isEmpty() || $paketList->isEmpty()) {
            return;
        }

        // Pisahkan katalog paket: Residensial Up To, Residensial 1:1 Dedicated, dan Bisnis Dedicated
        $paketRumahUpTo = PaketLayanan::where('status', 'aktif')
            ->where('nama_paket', 'like', '%Up To%')
            ->get();
        $paketRumahDed = PaketLayanan::where('status', 'aktif')
            ->where('nama_paket', 'like', '%Dedicated%')
            ->where('nama_paket', 'not like', '%SOHO%')
            ->where('nama_paket', 'not like', '%Corporate%')
            ->get();
        $paketBisnis = PaketLayanan::where('status', 'aktif')
            ->where(function ($q) {
                $q->where('nama_paket', 'like', '%SOHO%')
                    ->orWhere('nama_paket', 'like', '%Corporate%');
            })
            ->get();

        if ($paketRumahUpTo->isEmpty()) {
            $paketRumahUpTo = $paketList;
        }
        if ($paketRumahDed->isEmpty()) {
            $paketRumahDed = $paketList;
        }
        if ($paketBisnis->isEmpty()) {
            $paketBisnis = $paketList;
        }

        $pelangganData = $this->daftarPelanggan();

        foreach ($pelangganData as $index => $item) {
            $perumahan = $perumahans[$index % $perumahans->count()];

            $customPrefixes = ['BF', 'WG', 'ARS', 'BF', 'WG'];
            $prefix = $customPrefixes[$index % count($customPrefixes)];
            $noReg = sprintf('%s%s%02d', $prefix, now()->format('dmY'), $index + 1);

            // 1. Buat Master Pelanggan (Idempotent)
            $pelanggan = Pelanggan::updateOrCreate(
                ['email' => $item['email']],
                [
                    'no_reg' => $noReg,
                    'tipe_pelanggan' => $item['tipe'],
                    'nik' => sprintf('3273%012d', 100000000000 + $index + 1),
                    'nama_depan' => $item['nama_depan'],
                    'nama_belakang' => $item['nama_belakang'],
                    'no_hp' => $item['no_hp'],
                    'perumahan_id' => $perumahan->id,
                    'rt' => sprintf('%02d', ($index % 8) + 1),
                    'rw' => sprintf('%02d', ($index % 4) + 1),
                    'no_rumah' => 'No. '.(($index * 3) + 5),
                    'kode_pos' => '40287',
                    'alamat_lengkap' => $item['nama_depan'].' '.$item['nama_belakang'].', '.$perumahan->nama_perumahan.' No. '.(($index * 3) + 5),
                    'latitude' => $perumahan->kelurahan->kecamatan->kota->nama_kota === 'Kota Cimahi' ? -6.872 + ($index * 0.001) : -6.940 + ($index * 0.001),
                    'longitude' => 107.620 + ($index * 0.001),
                    'status' => $item['status_pelanggan'],
                    'dibuat_oleh' => $admin->id,
                ]
            );

            // 2. Buat Akun Portal Pelanggan
            AkunPelanggan::firstOrCreate(
                ['pelanggan_id' => $pelanggan->id],
                [
                    'email' => $pelanggan->email,
                    'password' => 'password', // Auto-hashed
                    'email_verified_at' => now(),
                ]
            );

            // Pelanggan Belum Terpasang belum memiliki layanan
            if ($item['status_pelanggan'] === StatusPelanggan::BelumTerpasang) {
                continue;
            }

            // 3. Alokasikan Port ODP
            $availablePort = OdpPort::whereHas('odp', function ($q) use ($perumahan) {
                $q->where('perumahan_id', $perumahan->id);
            })->where('status', StatusOdpPort::Kosong)->first();

            // Jika port perumahan penuh, ambil port kosong mana saja
            if (! $availablePort) {
                $availablePort = OdpPort::where('status', StatusOdpPort::Kosong)->first();
            }

            // Tentukan paket layanan, jenis koneksi, dan alokasi IP Pool / IP Statis
            if ($item['tipe'] === TipePelanggan::Rumah) {
                // Distribusi Residensial: ~60% Up To, ~40% Residensial 1:1 Dedicated
                if ($index % 5 === 1 || $index % 5 === 3) {
                    $paket = $paketRumahDed[$index % $paketRumahDed->count()];
                } else {
                    $paket = $paketRumahUpTo[$index % $paketRumahUpTo->count()];
                }

                $jenisKoneksi = JenisKoneksi::Pppoe;
                $ipPoolId = $poolRumah?->id;
                $ipStatic = null;
            } else {
                // Jalur Dedicated: Paket Bisnis / Corporate
                $paket = $paketBisnis[$index % $paketBisnis->count()];

                if ($index === 4 || $index === 7) {
                    $jenisKoneksi = JenisKoneksi::IpStatic;
                    $ipPoolId = null;
                    $ipStatic = '10.0.1.'.(20 + $index);
                } else {
                    $jenisKoneksi = JenisKoneksi::Pppoe;
                    $ipPoolId = $poolBisnis?->id;
                    $ipStatic = null;
                }
            }

            $pppUsername = LayananPelanggan::generatePppUsername($pelanggan);

            $isExpired = ($item['status_pelanggan'] === StatusPelanggan::Expired);
            $mulaiTanggal = Carbon::now()->startOfMonth()->subMonths(2)->addDays(2);
            $expiredTanggal = $isExpired
                ? Carbon::now()->subDays(5)
                : Carbon::now()->endOfMonth();

            // 4. Buat / Perbarui Layanan Pelanggan (Idempotent)
            $layanan = LayananPelanggan::updateOrCreate(
                ['pelanggan_id' => $pelanggan->id],
                [
                    'paket_layanan_id' => $paket->id,
                    'router_id' => $router->id,
                    'ip_pool_id' => $ipPoolId,
                    'ppp_username' => $pppUsername,
                    'ppp_password_terenkripsi' => 'unms'.rand(1000, 9999),
                    'ip_static' => $ipStatic,
                    'odp_port_id' => $availablePort?->id,
                    'jenis_koneksi' => $jenisKoneksi,
                    'status' => $item['status_layanan'],
                    'tanggal_mulai' => $mulaiTanggal,
                    'tanggal_expired' => $expiredTanggal,
                ]
            );

            // Tandai port ODP terpakai jika baru
            if ($availablePort && $layanan->wasRecentlyCreated) {
                $availablePort->update([
                    'status' => StatusOdpPort::Terpakai,
                    'layanan_pelanggan_id' => $layanan->id,
                ]);
            }

            // 5. Generate Siklus Invoice & Pembayaran (hanya untuk pelanggan Aktif & Expired)
            if ($item['status_pelanggan'] === StatusPelanggan::Aktif) {
                // Invoice Bulan M-2 (Lunas)
                $tglTerbitM2 = Carbon::now()->startOfMonth()->subMonths(2);
                $invM2 = $this->createInvoiceRecord(
                    $pelanggan,
                    $layanan,
                    $paket->harga,
                    $tglTerbitM2,
                    StatusInvoice::Lunas,
                    $admin->id,
                    $index === 0 ? $promoDiskon : null
                );
                $this->createPaymentRecord($invM2, MetodePembayaran::Transfer, $tglTerbitM2->copy()->addDays(3), $admin->id);

                // Invoice Bulan M-1 (Lunas)
                $tglTerbitM1 = Carbon::now()->startOfMonth()->subMonths(1);
                $invM1 = $this->createInvoiceRecord(
                    $pelanggan,
                    $layanan,
                    $paket->harga,
                    $tglTerbitM1,
                    StatusInvoice::Lunas,
                    $admin->id,
                    ($index === 1 && $promoHemat) ? $promoHemat : null
                );
                $this->createPaymentRecord($invM1, MetodePembayaran::ManualAdmin, $tglTerbitM1->copy()->addDays(4), $admin->id);

                // Invoice Bulan Berjalan M (Menunggu Pembayaran)
                $tglTerbitM0 = Carbon::now()->startOfMonth();
                $this->createInvoiceRecord(
                    $pelanggan,
                    $layanan,
                    $paket->harga,
                    $tglTerbitM0,
                    StatusInvoice::MenungguPembayaran,
                    $admin->id
                );
            } elseif ($item['status_pelanggan'] === StatusPelanggan::Expired) {
                // Pelanggan Expired: Invoice Bulan Lalu Lunas, Invoice Bulan Ini Kadaluarsa / Menunggak
                $tglTerbitM1 = Carbon::now()->startOfMonth()->subMonths(1);
                $invM1 = $this->createInvoiceRecord(
                    $pelanggan,
                    $layanan,
                    $paket->harga,
                    $tglTerbitM1,
                    StatusInvoice::Lunas,
                    $admin->id
                );
                $this->createPaymentRecord($invM1, MetodePembayaran::Transfer, $tglTerbitM1->copy()->addDays(5), $admin->id);

                // Tagihan tertunggak
                $tglTerbitM0 = Carbon::now()->startOfMonth();
                $this->createInvoiceRecord(
                    $pelanggan,
                    $layanan,
                    $paket->harga,
                    $tglTerbitM0,
                    StatusInvoice::Kadaluarsa,
                    $admin->id
                );
            }
        }
    }

    /**
     * Helper untuk membuat invoice tagihan.
     */
    private function createInvoiceRecord(
        Pelanggan $pelanggan,
        LayananPelanggan $layanan,
        float $harga,
        Carbon $tanggalTerbit,
        StatusInvoice $status,
        int $adminId,
        ?Promo $promo = null
    ): Invoice {
        $periode = $tanggalTerbit->format('Y-m');
        $existing = Invoice::where('layanan_pelanggan_id', $layanan->id)
            ->where('periode_tagihan', $periode)
            ->first();

        if ($existing) {
            return $existing;
        }

        $jumlahSetelahPromo = $harga;

        if ($promo) {
            if ($promo->diskon_tipe === DiskonTipe::Persentase && $promo->diskon_nilai) {
                $jumlahSetelahPromo = $harga - ($harga * ($promo->diskon_nilai / 100));
            } elseif ($promo->diskon_tipe === DiskonTipe::Nominal && $promo->diskon_nilai) {
                $jumlahSetelahPromo = max(0, $harga - $promo->diskon_nilai);
            }
        }

        $jatuhTempo = $tanggalTerbit->copy()->addDays(10);
        $lunasAt = ($status === StatusInvoice::Lunas) ? $tanggalTerbit->copy()->addDays(3) : null;
        $metode = ($status === StatusInvoice::Lunas) ? MetodePembayaran::Transfer : null;

        $invoice = Invoice::create([
            'periode_tagihan' => $periode,
            'pelanggan_id' => $pelanggan->id,
            'layanan_pelanggan_id' => $layanan->id,
            'jumlah' => $harga,
            'jumlah_setelah_promo' => $jumlahSetelahPromo,
            'promo_id' => $promo?->id,
            'status' => $status,
            'tanggal_terbit' => $tanggalTerbit->toDateString(),
            'tanggal_jatuh_tempo' => $jatuhTempo->toDateString(),
            'tanggal_lunas' => $lunasAt?->toDateString(),
            'metode_pembayaran' => $metode,
            'dibuat_oleh' => $adminId,
        ]);

        if ($promo) {
            PromoPenggunaan::create([
                'promo_id' => $promo->id,
                'pelanggan_id' => $pelanggan->id,
                'invoice_id' => $invoice->id,
                'digunakan_pada' => $tanggalTerbit,
            ]);

            $promo->increment('terpakai_global');
        }

        return $invoice;
    }

    /**
     * Helper untuk membuat catatan pembayaran invoice.
     */
    private function createPaymentRecord(
        Invoice $invoice,
        MetodePembayaran $metode,
        Carbon $dibayarPada,
        int $adminId
    ): Pembayaran {
        $existing = Pembayaran::where('invoice_id', $invoice->id)->first();
        if ($existing) {
            return $existing;
        }

        return Pembayaran::create([
            'invoice_id' => $invoice->id,
            'metode' => $metode,
            'referensi_transaksi' => 'TRX-'.strtoupper(Str::random(10)),
            'jumlah_dibayar' => $invoice->jumlah_setelah_promo,
            'dibayar_pada' => $dibayarPada,
            'dicatat_oleh' => $adminId,
            'catatan' => 'Pembayaran tagihan invoice '.$invoice->no_invoice,
        ]);
    }

    /**
     * Data realistis 3 pelanggan dummy UNMS untuk testing.
     *
     * @return array<int, array{nama_depan: string, nama_belakang: string, email: string, no_hp: string, tipe: TipePelanggan, status_pelanggan: StatusPelanggan, status_layanan: StatusLayanan}>
     */
    private function daftarPelanggan(): array
    {
        return [
            // 1 Pelanggan Aktif (Siklus Invoice M-2 & M-1 Lunas, M-0 Menunggu Pembayaran)
            ['nama_depan' => 'Ahmad', 'nama_belakang' => 'Fauzi', 'email' => 'ahmad.fauzi@example.com', 'no_hp' => '081314984970', 'tipe' => TipePelanggan::Rumah, 'status_pelanggan' => StatusPelanggan::Aktif, 'status_layanan' => StatusLayanan::Aktif],

            // 1 Pelanggan Expired (Siklus Invoice M-1 Lunas, M-0 Kadaluarsa/Tunggakan Suspend)
            ['nama_depan' => 'Budi', 'nama_belakang' => 'Santoso', 'email' => 'budi.santoso@example.com', 'no_hp' => '081314984970', 'tipe' => TipePelanggan::Rumah, 'status_pelanggan' => StatusPelanggan::Expired, 'status_layanan' => StatusLayanan::Suspend],

            // 1 Pelanggan Req. Pemasangan (Survei / Jadwal Pasang / Tiket Lapangan)
            ['nama_depan' => 'Citra', 'nama_belakang' => 'Lestari', 'email' => 'citra.lestari@example.com', 'no_hp' => '081314984970', 'tipe' => TipePelanggan::Rumah, 'status_pelanggan' => StatusPelanggan::ReqPemasangan, 'status_layanan' => StatusLayanan::Proses],
        ];
    }
}
