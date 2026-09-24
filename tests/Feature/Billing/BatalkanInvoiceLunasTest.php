<?php

use App\Actions\Invoice\BatalkanInvoiceLunasAction;
use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Livewire\Invoice\Index;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\PengaturanSiklusTagihan;
use App\Models\ProfilBandwidth;
use App\Models\User;
use App\Services\Billing\BillingService;
use Carbon\CarbonInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->billing = app(BillingService::class);
    $this->siklus = PengaturanSiklusTagihan::ambil();

    $this->pelanggan = Pelanggan::factory()->create();
    $this->paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => ProfilBandwidth::factory()->create()->id,
        'harga' => 150000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
});

function buatLayananBerexpired(CarbonInterface $expired): LayananPelanggan
{
    return LayananPelanggan::factory()->create([
        'pelanggan_id' => test()->pelanggan->id,
        'paket_layanan_id' => test()->paket->id,
        'status' => StatusLayanan::Aktif,
        'tanggal_expired' => $expired->toDateString(),
    ]);
}

function lunasiManual(Invoice $invoice, ?CarbonInterface $dibayarPada = null): void
{
    test()->billing->prosesPembayaranManual($invoice, [
        'metode' => MetodePembayaran::ManualAdmin,
        'jumlah_dibayar' => $invoice->jumlah_setelah_promo,
        'dibayar_pada' => $dibayarPada ?? now(),
    ], test()->admin);
}

test('void invoice lunas mengembalikan masa aktif, membuang pembayaran dari laporan, dan tercatat di audit trail', function () {
    $expired = $this->siklus->sesuaikanKeHariJatuhTempo(now()->addMonths(2));
    $layanan = buatLayananBerexpired($expired);
    $invoice = $this->billing->generateInvoice($layanan, periodeTagihan: $expired->format('Y-m'));
    lunasiManual($invoice);

    expect($layanan->fresh()->tanggal_expired->toDateString())->toBe($expired->copy()->addMonth()->toDateString());

    app(BatalkanInvoiceLunasAction::class)->execute($invoice->fresh(), $this->superAdmin, 'Salah catat pembayaran');

    expect($layanan->fresh()->tanggal_expired->toDateString())->toBe($expired->toDateString())
        ->and($layanan->fresh()->status)->toBe(StatusLayanan::Aktif)
        ->and(Invoice::find($invoice->id))->toBeNull()
        ->and(Invoice::withTrashed()->find($invoice->id)->status)->toBe(StatusInvoice::Dibatalkan)
        ->and(Invoice::withTrashed()->find($invoice->id)->keterangan_hapus)->toBe('Salah catat pembayaran')
        ->and(Pembayaran::count())->toBe(0)
        ->and(Pembayaran::withTrashed()->count())->toBe(1);

    $log = Activity::where('subject_type', Invoice::class)->where('subject_id', $invoice->id)
        ->where('causer_id', $this->superAdmin->id)->latest('id')->first();
    expect($log->getProperty('action'))->toBe('batalkan_invoice_lunas')
        ->and($log->getProperty('alasan'))->toBe('Salah catat pembayaran');
});

test('layanan yang masa aktifnya kembali lewat diisolir', function () {
    $expired = $this->siklus->sesuaikanKeHariJatuhTempo(now()->subMonths(3));
    $layanan = buatLayananBerexpired($expired);
    $invoice = $this->billing->generateInvoice($layanan, periodeTagihan: $expired->format('Y-m'));
    lunasiManual($invoice);

    expect($layanan->fresh()->status)->toBe(StatusLayanan::Aktif);

    app(BatalkanInvoiceLunasAction::class)->execute($invoice->fresh(), $this->superAdmin, 'Pembayaran fiktif');

    expect($layanan->fresh()->status)->toBe(StatusLayanan::Suspend);
});

test('invoice yang tadinya digabung dilepas menjadi Kadaluarsa saat invoice penggabung dibatalkan', function () {
    $expired = $this->siklus->sesuaikanKeHariJatuhTempo(now()->addMonth());
    $layanan = buatLayananBerexpired($expired);
    $lama = $this->billing->generateInvoice($layanan, periodeTagihan: $expired->copy()->subMonth()->format('Y-m'));
    $baru = $this->billing->generateInvoice($layanan, periodeTagihan: $expired->format('Y-m'));
    expect($lama->fresh()->status)->toBe(StatusInvoice::Digabung);

    lunasiManual($baru);
    expect($layanan->fresh()->tanggal_expired->toDateString())->toBe($expired->copy()->addMonths(2)->toDateString());

    app(BatalkanInvoiceLunasAction::class)->execute($baru->fresh(), $this->superAdmin, 'Koreksi tagihan');

    expect($lama->fresh()->status)->toBe(StatusInvoice::Kadaluarsa)
        ->and($lama->fresh()->digabung_ke_invoice_id)->toBeNull()
        ->and($layanan->fresh()->tanggal_expired->toDateString())->toBe($expired->toDateString());
});

test('hanya pembayaran terakhir layanan yang dapat dibatalkan', function () {
    $expired = $this->siklus->sesuaikanKeHariJatuhTempo(now()->addMonths(2));
    $layanan = buatLayananBerexpired($expired);
    $pertama = $this->billing->generateInvoice($layanan, periodeTagihan: $expired->format('Y-m'));
    lunasiManual($pertama, now()->subDay());
    $kedua = $this->billing->generateInvoice($layanan, periodeTagihan: $expired->copy()->addMonth()->format('Y-m'));
    lunasiManual($kedua, now());

    expect(fn () => app(BatalkanInvoiceLunasAction::class)->execute($pertama->fresh(), $this->superAdmin, 'Salah catat'))
        ->toThrow(Exception::class, 'terakhir');

    expect($pertama->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and(Pembayaran::count())->toBe(2);
});

test('invoice yang dilunasi lewat payment gateway tidak dapat dibatalkan', function () {
    $layanan = buatLayananBerexpired(now()->addMonth());
    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'status' => StatusInvoice::Lunas,
        'metode_pembayaran' => MetodePembayaran::PaymentGateway,
    ]);
    Pembayaran::factory()->create(['invoice_id' => $invoice->id]);

    expect(fn () => app(BatalkanInvoiceLunasAction::class)->execute($invoice, $this->superAdmin, 'Salah catat'))
        ->toThrow(Exception::class, 'payment gateway');

    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas);
});

test('UI: hanya pemegang izin void_lunas yang bisa, dan alasan wajib', function () {
    $layanan = buatLayananBerexpired($this->siklus->sesuaikanKeHariJatuhTempo(now()->addMonths(2)));
    $invoice = $this->billing->generateInvoice($layanan, periodeTagihan: now()->addMonths(2)->format('Y-m'));
    lunasiManual($invoice);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('deletingId', $invoice->id)
        ->set('keteranganHapus', 'Salah catat pembayaran')
        ->call('deleteInvoice')
        ->assertForbidden();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('deletingId', $invoice->id)
        ->set('keteranganHapus', '')
        ->call('deleteInvoice')
        ->assertHasErrors(['keteranganHapus' => 'required']);

    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('deletingId', $invoice->id)
        ->set('keteranganHapus', 'Salah catat pembayaran')
        ->call('deleteInvoice')
        ->assertHasNoErrors();

    expect(Invoice::withTrashed()->find($invoice->id)->status)->toBe(StatusInvoice::Dibatalkan);
});
