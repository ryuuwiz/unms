<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Exports\LaporanBillingExport;
use App\Exports\LaporanLayananExport;
use App\Livewire\Laporan\Billing;
use App\Livewire\Laporan\Layanan;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->adminUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->adminUser->assignRole('admin');

    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id, 'harga' => 200000]);
    $this->router = Router::factory()->create();
    $this->pelanggan = Pelanggan::factory()->create();
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
    ]);
});

test('user with laporan.lihat can view billing report summary', function () {
    Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'jumlah' => 200000,
        'jumlah_setelah_promo' => 200000,
        'status' => StatusInvoice::Lunas,
        'tanggal_terbit' => Carbon::today(),
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Billing::class)
        ->assertOk()
        ->assertViewHas('totalLunas', fn ($val) => $val === 200000.0);
});

test('scheduled command invoice:generate creates invoices for services expiring within 7 days', function () {
    // Tanggal dipatok (bukan Carbon::today()->addDays()) supaya hasil tidak bergantung
    // pada hari suite ini dijalankan -- lihat pola yang sama di InvoiceTest.php.
    $this->travelTo(Carbon::create(2026, 9, 24));
    $this->layanan->update([
        'status' => StatusLayanan::Aktif,
        'tanggal_expired' => '2026-10-10',
    ]);

    $this->artisan('invoice:generate')
        ->assertSuccessful();

    $invoice = Invoice::where('layanan_pelanggan_id', $this->layanan->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and((float) $invoice->jumlah)->toBe(200000.0);
});

test('scheduled command invoice:cek-kadaluarsa marks overdue unpaid invoices as kadaluarsa', function () {
    $overdueInvoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_terbit' => Carbon::today()->subDays(10),
        'tanggal_jatuh_tempo' => Carbon::today()->subDays(3), // overdue
    ]);

    $this->artisan('invoice:cek-kadaluarsa')
        ->assertSuccessful();

    expect($overdueInvoice->fresh()->status)->toBe(StatusInvoice::Kadaluarsa);
});

test('ekspor laporan billing mewajibkan izin laporan.ekspor', function () {
    $cs = User::factory()->create(['status' => UserStatus::Active]);
    $cs->assignRole('customer_service');
    $cs->givePermissionTo('laporan.lihat');

    Livewire::actingAs($cs)->test(Billing::class)->call('exportExcel')->assertForbidden();
});

test('NIK hanya disertakan untuk pemegang pelanggan.lihat_ktp dan tercatat di audit trail', function () {
    Excel::fake();
    $this->travelTo(Carbon::create(2026, 9, 24, 10, 0, 0));
    $fileName = 'Laporan-Billing-20260924100000.xlsx';
    $this->pelanggan->update(['nik' => '3273010101900001']);
    Invoice::factory()->create(['pelanggan_id' => $this->pelanggan->id, 'layanan_pelanggan_id' => $this->layanan->id]);

    $tanpaKtp = User::factory()->create(['status' => UserStatus::Active]);
    $tanpaKtp->givePermissionTo(['laporan.lihat', 'laporan.ekspor', 'invoice.lihat']);

    Livewire::actingAs($tanpaKtp)->test(Billing::class)->call('exportExcel');
    Excel::assertDownloaded($fileName, fn (LaporanBillingExport $export) => $export->sertakanNik === false
        && $export->map($export->collection()->first())[3] === '-');
    expect(Activity::where('causer_id', $tanpaKtp->id)->where('log_name', 'laporan')->exists())->toBeFalse();

    Livewire::actingAs($this->adminUser)->test(Billing::class)->call('exportExcel');
    Excel::assertDownloaded($fileName, fn (LaporanBillingExport $export) => $export->sertakanNik === true
        && $export->map($export->collection()->first())[3] === '3273010101900001');
    expect(Activity::where('causer_id', $this->adminUser->id)->where('log_name', 'laporan')->first()?->getProperty('action'))
        ->toBe('ekspor_laporan_billing_dengan_nik');
});

test('NIK ditulis ke sel Excel sebagai teks agar 16 digit tidak berubah', function () {
    $sheet = new Spreadsheet;
    $cell = $sheet->getActiveSheet()->getCell('D2');

    (new LaporanBillingExport(sertakanNik: true))->bindValue($cell, '3273010101900001');

    expect($cell->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($cell->getValue())->toBe('3273010101900001');
});

test('laporan layanan memfilter per paket, status, dan client expired', function () {
    $this->travelTo(Carbon::create(2026, 9, 24));
    $paketLain = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);

    $this->layanan->update(['status' => StatusLayanan::Aktif, 'tanggal_expired' => '2026-10-10']);
    $expired = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id, 'paket_layanan_id' => $this->paket->id,
        'status' => StatusLayanan::Suspend, 'tanggal_expired' => '2026-09-10',
    ]);
    $paketBeda = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id, 'paket_layanan_id' => $paketLain->id,
        'status' => StatusLayanan::Aktif, 'tanggal_expired' => '2026-10-10',
    ]);

    $ids = fn (LaporanLayananExport $e) => $e->query()->pluck('id')->sort()->values()->all();

    expect($ids(new LaporanLayananExport(paketLayananId: $this->paket->id)))->toBe(collect([$this->layanan->id, $expired->id])->sort()->values()->all())
        ->and($ids(new LaporanLayananExport(hanyaExpired: true)))->toBe([$expired->id])
        ->and($ids(new LaporanLayananExport(status: StatusLayanan::Aktif->value)))->toBe(collect([$this->layanan->id, $paketBeda->id])->sort()->values()->all());

    $baris = (new LaporanLayananExport(hanyaExpired: true))->map($expired->fresh(['pelanggan', 'paketLayanan', 'router', 'ipPubliks']));
    expect($baris[3])->toBe($expired->site_id)
        ->and($baris[9])->toBe('10/09/2026')
        ->and($baris[10])->toBe(14);

    Livewire::actingAs($this->adminUser)
        ->test(Layanan::class)
        ->set('hanyaExpired', true)
        ->assertViewHas('total', 1)
        ->assertSee($expired->site_id)
        ->assertDontSee($paketBeda->site_id);
});

test('ekspor laporan layanan mewajibkan laporan.ekspor dan membawa filter layar', function () {
    Excel::fake();
    $this->travelTo(Carbon::create(2026, 9, 24, 10, 0, 0));

    Livewire::actingAs($this->adminUser)
        ->test(Layanan::class)
        ->set('paketLayananId', $this->paket->id)
        ->set('hanyaExpired', true)
        ->call('exportExcel');

    Excel::assertDownloaded('Laporan-Client-Expired-20260924100000.xlsx', fn (LaporanLayananExport $e) => $e->hanyaExpired && $e->paketLayananId === $this->paket->id);

    $tanpaEkspor = User::factory()->create(['status' => UserStatus::Active]);
    $tanpaEkspor->givePermissionTo(['laporan.lihat', 'layanan_pelanggan.lihat']);
    Livewire::actingAs($tanpaEkspor)->test(Layanan::class)->call('exportExcel')->assertForbidden();
});
