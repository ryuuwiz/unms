<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Enums\UserStatus;
use App\Jobs\Mikrotik\EnablePppoeAccountJob;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Livewire\Pembayaran\TransaksiGateway\Show;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\TransaksiPaymentGateway;
use App\Models\User;
use App\Services\Xendit\XenditPaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    config([
        'services.xendit.secret_key' => 'xnd_development_test_key_12345',
        'services.xendit.callback_token' => 'test_callback_token_valid_123',
    ]);

    Queue::fake([
        ProvisionPppoeAccountJob::class,
        EnablePppoeAccountJob::class,
    ]);
});

test('command xendit:ping dapat dijalankan dan memvalidasi konfigurasi', function () {
    $this->artisan('xendit:ping')
        ->assertExitCode(0)
        ->expectsOutputToContain('XENDIT GATEWAY DIAGNOSTIC & HEALTHCHECK')
        ->expectsOutputToContain('Webhook Token Verifier: VALID');
});

test('command xendit:simulate --local berhasil memproses pembayaran dan melunasi invoice', function () {
    $pelanggan = Pelanggan::factory()->create([
        'nama_depan' => 'Budi',
        'nama_belakang' => 'Santoso',
    ]);

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'status' => StatusLayanan::Suspend,
        'tanggal_expired' => now()->subDay()->toDateString(),
    ]);

    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 200000,
        'jumlah_setelah_promo' => 200000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    $transaksi = TransaksiPaymentGateway::create([
        'invoice_id' => $invoice->id,
        'gateway' => 'xendit',
        'external_id' => 'INV-'.$invoice->no_invoice.'-VA-BCA-12345',
        'xendit_reference_id' => 'pr_test_123',
        'channel' => GatewayChannel::VirtualAccount,
        'channel_detail' => 'bca',
        'nomor_pembayaran' => '880812345678',
        'total_tagihan' => 204500,
        'fee_gateway' => 4500,
        'status' => StatusTransaksiGateway::Pending,
        'expired_at' => now()->addDays(3),
    ]);

    $this->artisan("xendit:simulate {$transaksi->external_id} --local")
        ->expectsConfirmation('Lanjutkan proses simulasi pembayaran ini?', 'yes')
        ->assertExitCode(0)
        ->expectsOutputToContain('Webhook lokal berhasil dieksekusi.');

    $transaksi->refresh();
    $invoice->refresh();
    $layanan->refresh();

    expect($transaksi->status)->toBe(StatusTransaksiGateway::Paid)
        ->and($invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($layanan->status)->toBe(StatusLayanan::Aktif)
        ->and($layanan->tanggal_expired)->not->toBeNull();
});

test('tombol simulasi pada livewire component detail transaksi gateway berhasil melunasi transaksi', function () {
    $pelanggan = Pelanggan::factory()->create();
    $layanan = LayananPelanggan::factory()->create(['pelanggan_id' => $pelanggan->id]);
    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    $transaksi = TransaksiPaymentGateway::create([
        'invoice_id' => $invoice->id,
        'gateway' => 'xendit',
        'external_id' => 'INV-'.$invoice->no_invoice.'-QRIS-999',
        'xendit_reference_id' => 'pr_qris_test',
        'channel' => GatewayChannel::Qris,
        'channel_detail' => 'qris',
        'nomor_pembayaran' => 'QRIS Dinamis',
        'qr_string' => '00020101021226590014ID.LINKAJA.WWW01189360091100223120150215TEST',
        'total_tagihan' => 201500,
        'fee_gateway' => 1500,
        'status' => StatusTransaksiGateway::Pending,
        'expired_at' => now()->addDays(3),
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['transaksi' => $transaksi])
        ->assertOk()
        ->call('simulasikanPembayaran')
        ->assertHasNoErrors();

    $transaksi->refresh();
    $invoice->refresh();

    expect($transaksi->status)->toBe(StatusTransaksiGateway::Paid)
        ->and($invoice->status)->toBe(StatusInvoice::Lunas);
});

test('command xendit:simulate otomatis menerbitkan transaksi gateway jika invoice belum memiliki VA/QRIS', function () {
    $pelanggan = Pelanggan::factory()->create();
    $layanan = LayananPelanggan::factory()->create(['pelanggan_id' => $pelanggan->id]);
    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 150000,
        'jumlah_setelah_promo' => 150000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    $this->artisan("xendit:simulate {$invoice->no_invoice} --local")
        ->expectsConfirmation('Lanjutkan proses simulasi pembayaran ini?', 'yes')
        ->assertExitCode(0)
        ->expectsOutputToContain('Webhook lokal berhasil dieksekusi.');

    $invoice->refresh();
    $transaksi = TransaksiPaymentGateway::where('invoice_id', $invoice->id)->first();

    expect($transaksi)->not->toBeNull()
        ->and($transaksi->status)->toBe(StatusTransaksiGateway::Paid)
        ->and($invoice->status)->toBe(StatusInvoice::Lunas);
});

test('simulasi webhook lokal gagal dengan pesan jelas bila invoice transaksi sudah di-soft-delete', function () {
    $invoice = Invoice::factory()->create(['status' => StatusInvoice::MenungguPembayaran]);
    $transaksi = TransaksiPaymentGateway::create([
        'invoice_id' => $invoice->id,
        'gateway' => 'xendit',
        'external_id' => 'INV-'.$invoice->no_invoice.'-VA-BCA-99999',
        'channel' => GatewayChannel::VirtualAccount,
        'channel_detail' => 'bca',
        'nomor_pembayaran' => '880812345678',
        'total_tagihan' => 200000,
        'fee_gateway' => 0,
        'status' => StatusTransaksiGateway::Pending,
        'expired_at' => now()->addDays(3),
    ]);

    $invoice->delete();

    $hasil = app(XenditPaymentService::class)->simulasikanWebhookLokal($transaksi->fresh());

    expect($hasil['success'])->toBeFalse()
        ->and($hasil['status_code'])->toBe(404)
        ->and($hasil['message'])->toContain('Invoice transaksi ini sudah dihapus')
        ->and($transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Pending);
});
