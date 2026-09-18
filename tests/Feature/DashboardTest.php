<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusPelanggan;
use App\Livewire\Dashboard as DashboardComponent;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\PengaturanSiklusTagihan;
use App\Models\User;
use Database\Seeders\PerusahaanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        PerusahaanSeeder::class,
    ]);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');

    $this->buatLayanan = function (string $nama, StatusLayanan $status, int $hariKeExpired, ?StatusInvoice $statusInvoice = null, int $nominal = 250000): LayananPelanggan {
        $layanan = LayananPelanggan::factory()->create([
            'pelanggan_id' => Pelanggan::factory()->create(['nama_depan' => $nama, 'nama_belakang' => 'Uji']),
            'status' => $status,
            'tanggal_expired' => today()->addDays($hariKeExpired)->toDateString(),
        ]);

        if ($statusInvoice) {
            Invoice::factory()->create([
                'pelanggan_id' => $layanan->pelanggan_id,
                'layanan_pelanggan_id' => $layanan->id,
                'jumlah' => $nominal,
                'jumlah_setelah_promo' => $nominal,
                'status' => $statusInvoice,
            ]);
        }

        return $layanan;
    };

    $this->bayar = fn (string $tanggal, int $jumlah): Pembayaran => Pembayaran::factory()->create([
        'jumlah_dibayar' => $jumlah,
        'metode' => MetodePembayaran::ManualAdmin,
        'dibayar_pada' => $tanggal,
    ]);
});

test('tamu diarahkan ke halaman login saat mengakses dashboard', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('super admin melihat judul, subjudul, dan seluruh blok ringkasan', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Operasional & Billing', false)
        ->assertSee('Bulan berjalan · diperbarui '.now()->format('H:i'))
        ->assertSee('Total Pelanggan')
        ->assertSee('Pendapatan Hari Ini')
        ->assertSee('Pendapatan Bulan Ini')
        ->assertSee('Tagihan Periode')
        ->assertSee('Pelanggan Expired & Jatuh Tempo', false)
        ->assertSee('Transaksi Terbaru')
        ->assertSee('Tren Pendapatan Harian');
});

test('total pelanggan memisahkan aktif dari tidak aktif dan mengabaikan pelanggan calon', function () {
    Pelanggan::factory()->count(2)->create(['status' => StatusPelanggan::Aktif]);
    Pelanggan::factory()->create(['status' => StatusPelanggan::Off]);
    Pelanggan::factory()->create(['status' => StatusPelanggan::Expired]);
    Pelanggan::factory()->create(['status' => StatusPelanggan::BelumTerpasang]);

    $pelanggan = Livewire::actingAs($this->user)
        ->test(DashboardComponent::class)
        ->assertSee('2 aktif · 2 tidak aktif')
        ->instance()->pelanggan;

    expect($pelanggan)->toBe(['total' => 4, 'aktif' => 2, 'tidak_aktif' => 2]);
});

test('pendapatan hari ini dibanding kemarin dan bulan ini dibanding rentang hari yang sama bulan lalu', function () {
    $this->travelTo(now()->setDate(2026, 9, 19)->setTime(12, 0));

    ($this->bayar)('2026-09-19 08:00:00', 40000);
    ($this->bayar)('2026-09-18 09:00:00', 25000);
    ($this->bayar)('2026-09-05 09:00:00', 300000);
    ($this->bayar)('2026-08-10 09:00:00', 200000);
    ($this->bayar)('2026-08-25 09:00:00', 999000); // di luar rentang 1-19 bulan lalu

    Livewire::actingAs($this->user)
        ->test(DashboardComponent::class)
        ->assertSee('Rp 40.000')
        ->assertSee('Kemarin: Rp 25.000')
        ->assertSee('Rp 365.000')
        ->assertSee('+82.5%')
        ->assertSee('vs periode sama bulan lalu');
});

test('ringkasan tagihan periode memakai tarif siklus tanpa tunggakan dan tetap memasukkan invoice digabung', function () {
    $invoice = fn (StatusInvoice $status, int $total, int $tunggakan = 0, ?string $periode = null) => Invoice::factory()->create([
        'jumlah_setelah_promo' => $total,
        'jumlah_tunggakan' => $tunggakan,
        'status' => $status,
        'periode_tagihan' => $periode ?? now()->format('Y-m'),
    ]);

    $invoice(StatusInvoice::Lunas, 500000, 250000); // tarif siklus 250rb + tunggakan 250rb
    $invoice(StatusInvoice::MenungguPembayaran, 250000);
    $invoice(StatusInvoice::Digabung, 250000);
    $invoice(StatusInvoice::Dibatalkan, 900000);
    $invoice(StatusInvoice::Kadaluarsa, 200000, 0, now()->subMonth()->format('Y-m'));

    Livewire::actingAs($this->user)
        ->test(DashboardComponent::class)
        ->assertSee('Rp 750.000') // ditagih
        ->assertSee('33.3%')
        ->assertSee('Lunas: Rp 250.000')
        ->assertSee('Belum lunas: Rp 500.000')
        ->assertSee('semua periode: Rp 450.000'); // menunggu 250rb + kadaluarsa 200rb
});

test('tren harian memuat pendapatan dan transaksi tanggal 1 sampai hari ini saja', function () {
    $this->travelTo(now()->setDate(2026, 9, 19)->setTime(12, 0));

    ($this->bayar)('2026-09-03 09:00:00', 100000);
    ($this->bayar)('2026-09-03 15:00:00', 50000);
    ($this->bayar)('2026-09-05 09:00:00', 30000);

    $tren = Livewire::actingAs($this->user)->test(DashboardComponent::class)->instance()->tren;

    expect($tren['categories'])->toHaveCount(19)
        ->and($tren['revenue'][2])->toBe(150000.0)
        ->and($tren['transactions'][2])->toBe(2)
        ->and($tren['revenue'][4])->toBe(30000.0)
        ->and($tren['transactions'][3])->toBe(0);
});

test('transaksi terbaru menampilkan 5 pembayaran terakhir dan menaut ke invoice', function () {
    foreach (range(1, 6) as $i) {
        $pembayaran = ($this->bayar)(now()->subDays(10 - $i)->toDateTimeString(), 100000 + ($i * 1000));
    }

    Livewire::actingAs($this->user)
        ->test(DashboardComponent::class)
        ->assertSee('+Rp 106.000')
        ->assertSee('+Rp 102.000')
        ->assertDontSee('+Rp 101.000')
        ->assertSee(route('invoice.show', $pembayaran->invoice_id), false)
        ->assertSee(route('pembayaran.index'), false);
});

test('pelanggan expired memuat layanan dalam jendela dan mengurutkan yang paling lama lewat lebih dulu', function () {
    ($this->buatLayanan)('Akan', StatusLayanan::Aktif, 3, StatusInvoice::MenungguPembayaran, 150000);
    ($this->buatLayanan)('Lewat', StatusLayanan::Suspend, -5, StatusInvoice::Kadaluarsa, 350000);
    ($this->buatLayanan)('Lawas', StatusLayanan::Suspend, -45, StatusInvoice::Kadaluarsa);
    ($this->buatLayanan)('Berhenti', StatusLayanan::Berhenti, -3);
    ($this->buatLayanan)('Jauh', StatusLayanan::Aktif, 30);

    Livewire::actingAs($this->user)
        ->test(DashboardComponent::class)
        ->assertSeeInOrder(['Lewat', 'Akan'])
        ->assertSee('1 sudah lewat · 1 jatuh tempo ≤ H-'.PengaturanSiklusTagihan::ambil()->leadDays())
        ->assertSee('Lewat 5 hari')
        ->assertSee('H-3')
        ->assertSee('Rp 350.000')
        ->assertDontSee('Lawas')
        ->assertDontSee('Berhenti')
        ->assertDontSee('Jauh');
});

test('pelanggan expired dibatasi 8 baris dan menautkan ke daftar lengkap', function () {
    foreach (range(1, 9) as $i) {
        ($this->buatLayanan)("Pelanggan{$i}", StatusLayanan::Suspend, -$i);
    }

    Livewire::actingAs($this->user)
        ->test(DashboardComponent::class)
        ->assertSee('Lihat semua 9')
        ->assertSee('Pelanggan9')
        ->assertDontSee('Pelanggan1 ')
        ->assertSee(route('layanan-pelanggan.index', ['expiry' => 'all']), false);
});

test('menampilkan keadaan kosong saat tidak ada layanan yang perlu ditindaklanjuti', function () {
    Livewire::actingAs($this->user)
        ->test(DashboardComponent::class)
        ->assertSee('Tidak ada layanan yang perlu ditindaklanjuti')
        ->assertDontSee('Lihat semua 0');
});

test('peran tanpa izin keuangan hanya melihat total pelanggan dan pelanggan expired', function (string $peran) {
    $staf = User::factory()->create();
    $staf->assignRole($peran);

    Livewire::actingAs($staf)
        ->test(DashboardComponent::class)
        ->assertSee('Total Pelanggan')
        ->assertSee('Pelanggan Expired & Jatuh Tempo', false)
        ->assertDontSee('Pendapatan Hari Ini')
        ->assertDontSee('Pendapatan Bulan Ini')
        ->assertDontSee('Tagihan Periode')
        ->assertDontSee('Transaksi Terbaru')
        ->assertDontSee('Tren Pendapatan Harian');
})->with(['noc', 'teknisi', 'sales']);

test('menampilkan pesan kosong untuk peran tanpa izin apa pun', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(DashboardComponent::class)
        ->assertSee('Tidak ada ringkasan untuk peran Anda.')
        ->assertDontSee('Total Pelanggan');
});

test('baris menuju invoice terbuka terbaru, dan ke profil pelanggan bila tanpa izin invoice', function () {
    $layanan = ($this->buatLayanan)('Tautan', StatusLayanan::Suspend, -2, StatusInvoice::Kadaluarsa);
    $invoice = $layanan->invoices()->first();

    Livewire::actingAs($this->user)
        ->test(DashboardComponent::class)
        ->assertSee(route('invoice.show', $invoice), false);

    $sales = User::factory()->create();
    $sales->assignRole('sales');

    Livewire::actingAs($sales)
        ->test(DashboardComponent::class)
        ->assertSee(route('pelanggan.show', $layanan->pelanggan_id), false)
        ->assertDontSee(route('invoice.show', $invoice), false);
});
