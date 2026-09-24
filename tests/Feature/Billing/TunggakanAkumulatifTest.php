<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Livewire\Invoice\Index as InvoiceIndex;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\PengaturanSiklusTagihan;
use App\Models\TransaksiPaymentGateway;
use App\Models\User;
use App\Services\Billing\BillingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake();

    $this->billing = app(BillingService::class);
    $this->layanan = LayananPelanggan::factory()->create([
        'paket_layanan_id' => PaketLayanan::factory()->create([
            'harga' => 250000,
            'masa_aktif_nilai' => 1,
            'masa_aktif_satuan' => 'bulan',
        ]),
        'status' => StatusLayanan::Suspend,
        'tanggal_expired' => '2026-08-10',
    ]);

    $this->buatInvoice = fn (?string $periode, StatusInvoice $status, int $nominal = 250000): Invoice => Invoice::factory()->create([
        'pelanggan_id' => $this->layanan->pelanggan_id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'periode_tagihan' => $periode,
        'jumlah' => $nominal,
        'jumlah_setelah_promo' => $nominal,
        'status' => $status,
    ]);
});

test('generateInvoice menyerap invoice periodik terbuka lama sebagai tunggakan dan menandainya digabung', function () {
    $lama = ($this->buatInvoice)('2026-08', StatusInvoice::Kadaluarsa);

    $baru = $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-09');

    expect((float) $baru->jumlah_setelah_promo)->toBe(500000.0)
        ->and((float) $baru->jumlah_tunggakan)->toBe(250000.0)
        ->and((float) $baru->jumlah)->toBe(250000.0)
        ->and($lama->fresh()->status)->toBe(StatusInvoice::Digabung)
        ->and($lama->fresh()->digabung_ke_invoice_id)->toBe($baru->id);
});

test('generateInvoice berantai tidak menghitung dua kali invoice yang sudah diserap', function () {
    $pertama = ($this->buatInvoice)('2026-08', StatusInvoice::Kadaluarsa);
    $kedua = $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-09');
    $kedua->update(['status' => StatusInvoice::Kadaluarsa]);

    $ketiga = $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-10');

    expect((float) $ketiga->jumlah_setelah_promo)->toBe(750000.0)
        ->and((float) $ketiga->jumlah_tunggakan)->toBe(500000.0)
        ->and($pertama->fresh()->digabung_ke_invoice_id)->toBe($kedua->id)
        ->and($kedua->fresh()->digabung_ke_invoice_id)->toBe($ketiga->id);
});

test('generateInvoice tidak menyerap invoice lunas, ad-hoc, atau periode yang lebih baru', function () {
    $lunas = ($this->buatInvoice)('2026-07', StatusInvoice::Lunas);
    $adHoc = ($this->buatInvoice)(null, StatusInvoice::MenungguPembayaran, 100000);
    $lebihBaru = ($this->buatInvoice)('2026-11', StatusInvoice::Kadaluarsa);

    $baru = $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-09');

    expect((float) $baru->jumlah_setelah_promo)->toBe(250000.0)
        ->and((float) $baru->jumlah_tunggakan)->toBe(0.0)
        ->and($lunas->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($adHoc->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($lebihBaru->fresh()->status)->toBe(StatusInvoice::Kadaluarsa);
});

test('menyerap invoice mematikan sesi payment gateway lama', function () {
    $lama = ($this->buatInvoice)('2026-08', StatusInvoice::MenungguPembayaran);
    $sesi = TransaksiPaymentGateway::create([
        'invoice_id' => $lama->id,
        'external_id' => 'EXT-LAMA-1',
        'channel' => 'virtual_account',
        'total_tagihan' => 250000,
        'status' => StatusTransaksiGateway::Pending,
        'expired_at' => now()->addDay(),
    ]);

    $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-09');

    expect($sesi->fresh()->status)->toBe(StatusTransaksiGateway::Expired);
});

test('pembayaran manual menolak invoice yang sudah digabung', function () {
    $lama = ($this->buatInvoice)('2026-08', StatusInvoice::Kadaluarsa);
    $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-09');

    expect(fn () => $this->billing->prosesPembayaranManual($lama, ['metode' => 'transfer', 'jumlah_dibayar' => 250000]))
        ->toThrow(Exception::class, 'sudah digabung');

    expect($lama->fresh()->status)->toBe(StatusInvoice::Digabung);
});

test('melunasi invoice akumulatif memperpanjang sebanyak siklus dari expired lama, bukan dari tanggal bayar', function () {
    ($this->buatInvoice)('2026-08', StatusInvoice::Kadaluarsa);
    $baru = $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-09');

    $this->billing->prosesPembayaranManual($baru, [
        'metode' => 'transfer',
        'jumlah_dibayar' => 500000,
        'dibayar_pada' => '2026-09-20',
    ]);

    expect($this->layanan->fresh()->tanggal_expired->toDateString())->toBe('2026-10-10')
        ->and($this->layanan->fresh()->status)->toBe(StatusLayanan::Aktif);
});

test('perpanjangan menyesuaikan tanggal expired ke Hari Jatuh Tempo pada pengaturan', function () {
    PengaturanSiklusTagihan::ambil()->update(['hari_jatuh_tempo' => 15]);
    $invoice = ($this->buatInvoice)('2026-08', StatusInvoice::MenungguPembayaran);

    $this->billing->prosesPembayaranManual($invoice, ['metode' => 'transfer', 'jumlah_dibayar' => 250000]);

    expect($this->layanan->fresh()->tanggal_expired->toDateString())->toBe('2026-09-15');
});

test('membatalkan invoice penggabung mengembalikan invoice yang diserap menjadi kadaluarsa', function () {
    $lama = ($this->buatInvoice)('2026-08', StatusInvoice::Kadaluarsa);
    $baru = $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-09');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(InvoiceIndex::class)
        ->set('deletingId', $baru->id)
        ->call('deleteInvoice');

    expect($baru->fresh()->status)->toBe(StatusInvoice::Dibatalkan)
        ->and($lama->fresh()->status)->toBe(StatusInvoice::Kadaluarsa)
        ->and($lama->fresh()->digabung_ke_invoice_id)->toBeNull();
});

test('invoice yang sudah digabung tetap dapat dibatalkan langsung tanpa kondisi', function () {
    $lama = ($this->buatInvoice)('2026-08', StatusInvoice::Kadaluarsa);
    $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-09');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(InvoiceIndex::class)
        ->set('deletingId', $lama->id)
        ->call('deleteInvoice');

    expect($lama->fresh()->status)->toBe(StatusInvoice::Dibatalkan);
});
