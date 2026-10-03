<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Livewire\Portal\Invoice\Show;
use App\Livewire\Settings\MetodePembayaran\Form;
use App\Models\ChannelPembayaran;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->invoice = Invoice::factory()->create([
        'jumlah' => 300000,
        'jumlah_setelah_promo' => 300000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
    $this->akun = $this->invoice->pelanggan->akunPelanggan;
});

/**
 * @param  array<string, mixed>  $data
 */
function fakeDirectIpaymu(array $data): void
{
    Http::fake(['*/api/v2/payment/direct' => Http::sequence()
        ->push(['Status' => 200, 'Data' => $data + ['TransactionId' => 12345]])
        ->push(['Status' => 200, 'Data' => $data + ['TransactionId' => 12346]]),
    ]);
}

test('fee admin flat dan persen dibulatkan ke rupiah', function () {
    expect(ChannelPembayaran::factory()->make(['fee_admin' => 4100])->hitungFee(300000))->toBe(4100.0)
        ->and(ChannelPembayaran::factory()->qris(0.7)->make()->hitungFee(123456))->toBe(864.0);
});

test('pelanggan memilih BCA VA: Direct Payment dikirim dengan fee admin dan nomor VA tampil, pilihan ulang memakai transaksi yang sama', function () {
    fakeDirectIpaymu(['PaymentNo' => '8808123456', 'Url' => 'https://sandbox.ipaymu.com/payment/12345']);
    $va = ChannelPembayaran::factory()->create();

    $component = Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('pilihChannel', $va->id)
        ->call('pilihChannel', $va->id);

    $transaksi = $this->invoice->transaksiPaymentGateways()->sole();
    expect($transaksi->channel)->toBe(GatewayChannel::VirtualAccount)
        ->and((float) $transaksi->total_tagihan)->toBe(304000.0)
        ->and((float) $transaksi->fee_gateway)->toBe(4000.0)
        ->and($transaksi->nomor_pembayaran)->toBe('8808123456')
        ->and($transaksi->provider_reference_id)->toBe('12345');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['paymentMethod'] === 'va'
        && $request['paymentChannel'] === 'bca'
        && $request['amount'] === 304000
        && $request['feeDirection'] === 'MERCHANT');

    $component->assertSee('8808123456')->assertSee('304.000');
});

test('QRIS menyimpan QR dan membuat QR baru setelah kedaluwarsa 5 menit', function () {
    fakeDirectIpaymu(['QrString' => '00020101021226ipaymu', 'Expired' => now('Asia/Jakarta')->addMinutes(5)->format('Y-m-d H:i:s')]);
    $qris = ChannelPembayaran::factory()->qris(0.7)->create();

    $component = Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('pilihChannel', $qris->id);

    $pertama = $this->invoice->transaksiPaymentGateways()->sole();
    expect($pertama->qr_string)->toBe('00020101021226ipaymu')
        ->and((float) $pertama->total_tagihan)->toBe(302100.0)
        ->and($pertama->expired_at->diffInMinutes(now(), true))->toBeLessThan(6);
    $component->assertSeeHtml('<svg');

    $this->travel(6)->minutes();
    $component->call('pilihChannel', $qris->id);

    expect($this->invoice->transaksiPaymentGateways()->count())->toBe(2);
});

test('channel OFF tidak bisa dipilih dan portal kembali ke Hosted Invoice', function () {
    Http::fake();
    $off = ChannelPembayaran::factory()->create(['is_active' => false]);

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->assertSee('Bayar Sekarang')
        ->call('pilihChannel', $off->id);

    expect($this->invoice->transaksiPaymentGateways()->count())->toBe(0);
    Http::assertNothingSent();
});

test('admin mengambil channel dari iPaymu lalu menyimpannya; kode yang tidak dikenal ditolak', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');
    ChannelPembayaran::factory()->create(['kode' => 'bni']);

    Http::fake(['*/api/v2/payment-channels' => Http::response(['Status' => 200, 'Data' => [
        ['Code' => 'qris', 'Channels' => [[
            'Code' => 'mpm', 'Name' => 'QRIS', 'Logo' => 'https://storage.googleapis.com/ipaymu-docs/assets/qris.png',
            'FeatureStatus' => 'active', 'TransactionFee' => ['ActualFee' => 0.7, 'ActualFeeType' => 'PERCENT', 'AdditionalFee' => 0],
        ]]],
        ['Code' => 'paylater', 'Channels' => [['Code' => 'akulaku', 'Name' => 'Akulaku', 'FeatureStatus' => 'active']]],
    ]])]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('tipe', GatewayChannel::VirtualAccount->value)
        ->set('kode', 'bcaa')
        ->call('save')
        ->assertHasErrors(['kode' => 'in'])
        ->call('ambilDariGateway')
        ->assertCount('saranGateway', 1)
        ->call('pakaiSaran', 0)
        ->assertSet('fee_admin', '0.7%')
        ->call('save')
        ->assertHasNoErrors();

    $qris = ChannelPembayaran::where('kode', 'mpm')->sole();
    expect($qris->tipe)->toBe(GatewayChannel::Qris)
        ->and($qris->fee_persen)->toBeTrue()
        ->and($qris->icon_url)->toContain('qris.png')
        ->and($qris->keterangan)->toBe('QRIS');

    // Diverifikasi ke sandbox: body GET tetap di-hash SHA-256 dari "{}", bukan "{}" mentah seperti tertulis di dokumentasi.
    $bodyHash = hash('sha256', '{}');
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->header('signature')[0] === hash_hmac('sha256', "GET:1179000899:{$bodyHash}:SANDBOX-KEY", 'SANDBOX-KEY'));
});

test('iPaymu tidak merespons: admin diberi tahu dan tetap bisa mengisi form manual', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');
    ChannelPembayaran::factory()->create(['kode' => 'bni']);
    Http::fake(['*/api/v2/payment-channels' => Http::failedConnection('cURL error 28: Operation timed out')]);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('ambilDariGateway')
        ->assertDispatched('toast-show', fn (string $event, array $params) => str_contains($params['slots']['text'], 'tidak merespons')
            && ! str_contains($params['slots']['text'], 'kredensial'))
        ->set('tipe', GatewayChannel::VirtualAccount->value)
        ->set('kode', 'bca')
        ->call('save')
        ->assertHasNoErrors();

    expect(ChannelPembayaran::where('kode', 'bca')->exists())->toBeTrue();
});

test('tagihan yang layanannya sudah berhenti tidak menampilkan pilihan metode', function () {
    Http::fake();
    ChannelPembayaran::factory()->create(['keterangan' => 'BCA Virtual Account']);
    $this->invoice->layananPelanggan->update(['status' => StatusLayanan::Berhenti]);

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->assertDontSee('Pilih metode pembayaran')
        ->assertDontSee('BCA Virtual Account');
});

test('ganti metode dalam detik yang sama membuat transaksi baru tanpa bentrok external_id', function () {
    fakeDirectIpaymu(['PaymentNo' => '8808123456']);
    $va = ChannelPembayaran::factory()->create();
    $qris = ChannelPembayaran::factory()->qris()->create(['pengaturan_gateway_id' => $va->pengaturan_gateway_id]);
    $this->freezeSecond();

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('pilihChannel', $va->id)
        ->call('pilihChannel', $qris->id)
        ->assertNotDispatched('toast-show');

    expect($this->invoice->transaksiPaymentGateways()->count())->toBe(2);
});
