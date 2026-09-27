<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusPelanggan;
use App\Enums\StatusRouter;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\StatusDivisiTicket;
use App\Enums\Ticket\StatusTicket;
use App\Livewire\Dashboard\AreaAdmin;
use App\Livewire\Dashboard\AreaNoc;
use App\Livewire\Dashboard\AreaTiket;
use App\Livewire\Dashboard as DashboardComponent;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\PengaturanSiklusTagihan;
use App\Models\Perusahaan;
use App\Models\Router;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\PerusahaanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::withoutLazyLoading();

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

test('super admin disapa dan melihat ketiga area beserta seluruh blok ringkasan admin', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Selamat datang kembali, '.$this->user->name)
        ->assertSeeInOrder(['Ringkasan Pelanggan &amp; Keuangan', 'NOC &amp; Infrastruktur', 'Ticketing &amp; Support'], false)
        ->assertSee('Tiket Hari Ini')
        ->assertSee('Tagihan Terbuka per Pelanggan')
        ->assertSee('Total Pelanggan')
        ->assertSee('Pendapatan Hari Ini')
        ->assertSee('Pendapatan Bulan Ini')
        ->assertSee('Tagihan Periode')
        ->assertSee('Pelanggan Expired & Jatuh Tempo')
        ->assertSee('Transaksi Terbaru')
        ->assertSee('Tren Pendapatan Harian');
});

test('dropdown akun menautkan ke halaman profile, security, dan appearance', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('profile.edit'), false)
        ->assertSee(route('security.edit'), false)
        ->assertSee(route('appearance.edit'), false);
});

test('total pelanggan memisahkan aktif dari tidak aktif dan mengabaikan pelanggan calon', function () {
    Pelanggan::factory()->count(2)->create(['status' => StatusPelanggan::Aktif]);
    Pelanggan::factory()->create(['status' => StatusPelanggan::Off]);
    Pelanggan::factory()->create(['status' => StatusPelanggan::Expired]);
    Pelanggan::factory()->create(['status' => StatusPelanggan::BelumTerpasang]);

    $pelanggan = Livewire::withoutLazyLoading()->actingAs($this->user)
        ->test(AreaAdmin::class)
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

    Livewire::withoutLazyLoading()->actingAs($this->user)
        ->test(AreaAdmin::class)
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

    Livewire::withoutLazyLoading()->actingAs($this->user)
        ->test(AreaAdmin::class)
        ->assertSee('Rp 750.000') // ditagih
        ->assertSee('33.3%')
        ->assertSee('Lunas: Rp 250.000')
        ->assertSee('Belum lunas: Rp 500.000')
        ->assertSee('semua periode: Rp 450.000'); // menunggu 250rb + kadaluarsa 200rb
});

test('tren harian memuat pendapatan dan transaksi 30 hari terakhir sampai hari ini', function () {
    $this->travelTo(now()->setDate(2026, 9, 19)->setTime(12, 0));

    ($this->bayar)('2026-08-20 09:00:00', 999000); // hari ke-31, di luar rentang
    ($this->bayar)('2026-08-21 09:00:00', 20000);
    ($this->bayar)('2026-09-03 09:00:00', 100000);
    ($this->bayar)('2026-09-03 15:00:00', 50000);
    ($this->bayar)('2026-09-19 08:00:00', 30000);

    $tren = Livewire::withoutLazyLoading()->actingAs($this->user)->test(AreaAdmin::class)->instance()->tren;

    expect($tren['categories'])->toHaveCount(30)
        ->and($tren['revenue'][0])->toBe(20000.0)
        ->and($tren['revenue'][13])->toBe(150000.0)
        ->and($tren['transactions'][13])->toBe(2)
        ->and($tren['transactions'][14])->toBe(0)
        ->and($tren['revenue'][29])->toBe(30000.0)
        ->and(array_sum($tren['revenue']))->toBe(200000.0);
});

test('transaksi terbaru menampilkan 5 pembayaran terakhir dan menaut ke invoice', function () {
    foreach (range(1, 6) as $i) {
        $pembayaran = ($this->bayar)(now()->subDays(10 - $i)->toDateTimeString(), 100000 + ($i * 1000));
    }

    Livewire::withoutLazyLoading()->actingAs($this->user)
        ->test(AreaAdmin::class)
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

    $perluPerhatian = Livewire::withoutLazyLoading()->actingAs($this->user)
        ->test(AreaAdmin::class)
        ->assertSeeInOrder(['Lewat', 'Akan'])
        ->assertSee('1 sudah lewat · 1 jatuh tempo ≤ H-'.PengaturanSiklusTagihan::ambil()->leadDays())
        ->assertSee('Lewat 5 hari')
        ->assertSee('H-3')
        ->assertSee('Rp 350.000')
        ->instance()->perluPerhatian;

    // Lawas tetap muncul di Tagihan Terbuka per Pelanggan (invoice kadaluarsa), jadi dicek lewat datanya.
    expect($perluPerhatian['daftar']->pluck('pelanggan.nama_depan')->all())->toBe(['Lewat', 'Akan']);
});

test('pelanggan expired dibatasi 8 baris dan menautkan ke daftar lengkap', function () {
    foreach (range(1, 9) as $i) {
        ($this->buatLayanan)("Pelanggan{$i}", StatusLayanan::Suspend, -$i);
    }

    Livewire::withoutLazyLoading()->actingAs($this->user)
        ->test(AreaAdmin::class)
        ->assertSee('Lihat semua 9')
        ->assertSee('Pelanggan9')
        ->assertDontSee('Pelanggan1 ')
        ->assertSee(route('layanan-pelanggan.index', ['expiry' => 'all']), false);
});

test('menampilkan keadaan kosong saat tidak ada layanan yang perlu ditindaklanjuti', function () {
    Livewire::withoutLazyLoading()->actingAs($this->user)
        ->test(AreaAdmin::class)
        ->assertSee('Tidak ada layanan yang perlu ditindaklanjuti')
        ->assertDontSee('Lihat semua 0');
});

test('peran tanpa izin keuangan hanya melihat total pelanggan dan pelanggan expired', function (string $peran) {
    $staf = User::factory()->create();
    $staf->assignRole($peran);

    Livewire::withoutLazyLoading()->actingAs($staf)
        ->test(AreaAdmin::class)
        ->assertSee('Total Pelanggan')
        ->assertSee('Pelanggan Expired & Jatuh Tempo')
        ->assertDontSee('Pendapatan Hari Ini')
        ->assertDontSee('Pendapatan Bulan Ini')
        ->assertDontSee('Tagihan Periode')
        ->assertDontSee('Transaksi Terbaru')
        ->assertDontSee('Tren Pendapatan Harian');
})->with(['noc', 'teknisi', 'sales']);

test('menampilkan pesan kosong untuk peran tanpa izin apa pun', function () {
    Livewire::withoutLazyLoading()->actingAs(User::factory()->create())
        ->test(DashboardComponent::class)
        ->assertSee('Tidak ada ringkasan untuk peran Anda.')
        ->assertDontSee('Total Pelanggan');
});

test('baris menuju invoice terbuka terbaru, dan ke profil pelanggan bila tanpa izin invoice', function () {
    $layanan = ($this->buatLayanan)('Tautan', StatusLayanan::Suspend, -2, StatusInvoice::Kadaluarsa);
    $invoice = $layanan->invoices()->first();

    Livewire::withoutLazyLoading()->actingAs($this->user)
        ->test(AreaAdmin::class)
        ->assertSee(route('invoice.show', $invoice), false);

    $sales = User::factory()->create();
    $sales->assignRole('sales');

    Livewire::withoutLazyLoading()->actingAs($sales)
        ->test(AreaAdmin::class)
        ->assertSee(route('pelanggan.show', $layanan->pelanggan_id), false)
        ->assertDontSee(route('invoice.show', $invoice), false);
});

test('tagihan terbuka per pelanggan menjumlahkan invoice terbuka lintas layanan dan mengurutkan terbesar dulu', function () {
    $invoice = fn (Pelanggan $pelanggan, StatusInvoice $status, int $nominal) => Invoice::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'jumlah_setelah_promo' => $nominal,
        'status' => $status,
    ]);

    $besar = Pelanggan::factory()->create(['nama_depan' => 'Besar']);
    $invoice($besar, StatusInvoice::MenungguPembayaran, 300000);
    $invoice($besar, StatusInvoice::Kadaluarsa, 200000);
    $invoice($besar, StatusInvoice::Digabung, 900000); // sudah masuk ke invoice terbaru, tidak dihitung dua kali

    $kecil = Pelanggan::factory()->create(['nama_depan' => 'Kecil']);
    $invoice($kecil, StatusInvoice::MenungguPembayaran, 100000);

    $lunas = Pelanggan::factory()->create(['nama_depan' => 'Lunas']);
    $invoice($lunas, StatusInvoice::Lunas, 700000);

    $daftar = Livewire::withoutLazyLoading()->actingAs($this->user)
        ->test(AreaAdmin::class)
        ->assertSeeInOrder(['Besar', 'Kecil'])
        ->assertSee('2 invoice terbuka')
        ->instance()->tagihanTerbuka;

    expect($daftar->pluck('id')->all())->toBe([$besar->id, $kecil->id])
        ->and((float) $daftar->first()->total_terbuka)->toBe(500000.0);
});

test('tiket hari ini menghitung tiket masuk hari ini dan tiket yang masih terbuka', function () {
    Ticket::factory()->create(['status' => StatusTicket::Baru]);
    Ticket::factory()->create(['status' => StatusTicket::Selesai]);
    Ticket::factory()->create(['status' => StatusTicket::Diproses, 'created_at' => now()->subDays(3)]);

    expect(Livewire::withoutLazyLoading()->actingAs($this->user)->test(AreaAdmin::class)->instance()->tiketHariIni)
        ->toBe(['masuk' => 2, 'terbuka' => 2]);
});

test('antrian tiket: teknisi melihat tiket PIC-nya, divisi lain tiket divisinya yang belum selesai, super admin semua', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole('teknisi');
    $noc = User::factory()->create();
    $noc->assignRole('noc');

    $tiket = function (DivisiTicket $divisi, StatusDivisiTicket $statusDivisi, StatusTicket $status = StatusTicket::Baru, ?User $pic = null): Ticket {
        $ticket = Ticket::factory()->create(['status' => $status, 'pic_id' => $pic?->id]);
        $ticket->divisis()->create(['divisi' => $divisi, 'status' => $statusDivisi]);

        return $ticket;
    };

    $milikTeknisi = $tiket(DivisiTicket::Teknisi, StatusDivisiTicket::Belum, StatusTicket::Diproses, $teknisi);
    $nocBelum = $tiket(DivisiTicket::Noc, StatusDivisiTicket::Progress);
    $nocSudah = $tiket(DivisiTicket::Noc, StatusDivisiTicket::Selesai);
    $nocTiketSelesai = $tiket(DivisiTicket::Noc, StatusDivisiTicket::Belum, StatusTicket::Selesai);

    $antrian = fn (User $user) => Livewire::withoutLazyLoading()->actingAs($user)->test(AreaTiket::class)->instance()->antrian->pluck('id')->sort()->values()->all();

    expect($antrian($teknisi))->toBe([$milikTeknisi->id])
        ->and($antrian($noc))->toBe([$nocBelum->id])
        ->and($antrian($this->user))->toBe([$milikTeknisi->id, $nocBelum->id, $nocSudah->id])
        ->and($nocTiketSelesai->id)->not->toBeIn($antrian($this->user));
});

test('tren tiket harian menghitung tiket masuk 30 hari terakhir yang boleh dilihat user', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole('teknisi');

    Ticket::factory()->create(['pic_id' => $teknisi->id]);
    Ticket::factory()->create(['pic_id' => $teknisi->id, 'created_at' => now()->subDays(40)]);
    Ticket::factory()->create(); // bukan PIC teknisi

    $tren = Livewire::withoutLazyLoading()->actingAs($teknisi)->test(AreaTiket::class)->instance()->tren;

    expect($tren['tiket'])->toHaveCount(30)
        ->and(array_sum($tren['tiket']))->toBe(1)
        ->and($tren['tiket'][29])->toBe(1);
});

test('ringkasan router menghitung online dan offline serta mendaftar router yang offline', function () {
    Router::factory()->create(['nama_router' => 'RTR-HIDUP', 'status_koneksi' => StatusRouter::Online]);
    Router::factory()->create(['nama_router' => 'RTR-MATI', 'status_koneksi' => StatusRouter::Offline]);
    Router::factory()->create(['nama_router' => 'RTR-BARU', 'status_koneksi' => StatusRouter::Unknown]);

    $router = Livewire::withoutLazyLoading()->actingAs($this->user)
        ->test(AreaNoc::class)
        ->assertSee('RTR-MATI')
        ->assertSee('RTR-BARU')
        ->assertDontSee('RTR-HIDUP')
        ->instance()->router;

    expect([$router['total'], $router['online'], $router['offline']])->toBe([3, 1, 2]);
});

test('area tampil sesuai izin: teknisi tanpa izin jaringan tidak melihat area NOC', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole('teknisi');

    Livewire::withoutLazyLoading()->actingAs($teknisi)
        ->test(DashboardComponent::class)
        ->assertSee('Ringkasan Pelanggan & Keuangan')
        ->assertSee('Ticketing & Support')
        ->assertDontSee('NOC & Infrastruktur');
});

test('footer halaman staf menampilkan nama brand perusahaan', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertSee('Made by MyArsyila')
        ->assertSee(Perusahaan::default()->nama_brand);
});
