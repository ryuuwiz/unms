<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Livewire\Invoice\Show as AdminInvoiceShow;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Router;
use App\Models\User;
use App\Services\Whatsapp\WhatsappService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $pelanggan = Pelanggan::factory()->create();
    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => PaketLayanan::factory()->create()->id,
        'router_id' => Router::factory()->create()->id,
        'status' => StatusLayanan::Aktif,
    ]);

    $this->invoice = Invoice::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 300000,
        'jumlah_setelah_promo' => 300000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
});

test('tamu dapat membuka Halaman Tagihan Mandiri lewat Tautan Tagihan tanpa login', function () {
    $this->get($this->invoice->tautanTagihan())
        ->assertOk()
        ->assertSee($this->invoice->no_invoice)
        ->assertSee('Virtual Account')
        ->assertSee('QRIS');
});

test('tamu lewat Tautan Tagihan tidak melihat tautan yang membutuhkan login', function () {
    $this->get($this->invoice->tautanTagihan())
        ->assertOk()
        ->assertDontSee('Kembali ke Daftar Tagihan');
});

test('tautan tagihan memakai token pendek yang stabil', function () {
    $tautan = $this->invoice->tautanTagihan();

    expect($tautan)->toEndWith('/t/'.$this->invoice->fresh()->token_tautan)
        ->and(strlen($this->invoice->fresh()->token_tautan))->toBe(16)
        ->and($this->invoice->fresh()->tautanTagihan())->toBe($tautan);
});

test('token tautan yang salah tidak membuka tagihan', function () {
    $this->invoice->tautanTagihan();

    $this->get(route('portal.tagihan.tautan', ['invoice' => 'TokenYangSalah12']))->assertNotFound();
});

test('mengganti tautan tagihan mencabut tautan lama', function () {
    $tautanLama = $this->invoice->tautanTagihan();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(AdminInvoiceShow::class, ['invoice' => $this->invoice])
        ->call('gantiTautanTagihan')
        ->assertOk();

    $this->get($tautanLama)->assertNotFound();
    $this->get($this->invoice->fresh()->tautanTagihan())->assertOk();

    $log = Activity::forSubject($this->invoice)->latest('id')->first();
    expect($log->description)->toContain('Tautan Tagihan diganti')
        ->and($log->causer_id)->toBe($admin->id)
        ->and(json_encode($log->toArray()))->not->toContain($this->invoice->fresh()->token_tautan);
});

test('signed URL lama yang sudah kedaluwarsa tetap dapat dibuka', function () {
    $signedUrl = URL::signedRoute('portal.invoice.show', ['invoice' => $this->invoice->id], now()->subDays(10));

    $this->get($signedUrl)
        ->assertOk()
        ->assertSee($this->invoice->no_invoice);
});

test('signed URL dengan tanda tangan palsu tetap ditolak', function () {
    $signedUrl = URL::signedRoute('portal.invoice.show', ['invoice' => $this->invoice->id], now()->addDay());

    $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature='.str_repeat('0', 64), $signedUrl))
        ->assertForbidden();
});

test('signed URL milik invoice lain tidak membuka invoice ini', function () {
    $lain = Invoice::factory()->create(['pelanggan_id' => $this->invoice->pelanggan_id]);
    $signedUrl = URL::signedRoute('portal.invoice.show', ['invoice' => $lain->id]);

    $this->get(str_replace("/tagihan/{$lain->id}", "/tagihan/{$this->invoice->id}", $signedUrl))
        ->assertForbidden();
});

test('notifikasi WhatsApp memakai Tautan Tagihan', function () {
    $params = app(WhatsappService::class)->buildInvoiceParams($this->invoice);

    expect($params['link_pembayaran'])->toBe($this->invoice->fresh()->tautanTagihan());
});
