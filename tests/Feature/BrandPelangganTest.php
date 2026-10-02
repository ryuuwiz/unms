<?php

use App\Enums\StatusInvoice;
use App\Models\AntrianWaBlast;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Perusahaan;
use App\Models\Router;
use App\Models\Ticket;
use App\Models\WaTemplate;
use App\Services\Billing\InvoiceCetak;
use App\Services\Whatsapp\WhatsappService;
use Database\Seeders\PengaturanPrefixRegistrasiSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PengaturanPrefixRegistrasiSeeder::class, WaTemplateSeeder::class]);
    $this->wa = app(WhatsappService::class);
});

test('pesan WA ke pelanggan dan teknisi memakai brand prefix, no_reg tak dikenal jatuh ke brand perusahaan', function () {
    $invoice = Invoice::factory()->create(['pelanggan_id' => Pelanggan::factory()->create(['no_reg' => 'BF2309202603'])->id]);
    $invoiceLain = Invoice::factory()->create(['pelanggan_id' => Pelanggan::factory()->create(['no_reg' => 'CUSTOM-001'])->id]);
    $ticket = Ticket::factory()->create(['pelanggan_id' => $invoice->pelanggan_id]);

    $render = fn (string $kode, array $params): string => WaTemplate::where('kode', $kode)->first()->render($this->wa->mergeCompanyParams($params));

    expect($render('invoice_terbit', $this->wa->buildInvoiceParams($invoice)))->toContain('Hormat kami,'."\n".'BESTFIBER')
        ->and($render('invoice_terbit', $this->wa->buildInvoiceParams($invoiceLain)))->toContain('Hormat kami,'."\n".Perusahaan::default()->nama_brand)
        ->and($render('tiket_penugasan_teknisi', $this->wa->buildTicketParams($ticket)))->toContain(' - BESTFIBER (');
});

test('inbound WhatsApp tanpa tanda tangan GOWA ditolak tanpa membuat auto-reply', function () {
    Pelanggan::factory()->create(['no_reg' => 'ARS2309202601', 'no_hp' => '081277776666']);

    $this->postJson(route('webhook.whatsapp'), [
        'id' => 'inbound_brand',
        'phone' => '081277776666',
        'sender' => '081277776666',
        'message' => 'TAGIHAN',
    ])->assertUnauthorized();

    expect(AntrianWaBlast::where('jenis', 'webhook_autoreply')->exists())->toBeFalse();
});

test('invoice PDF dan halaman invoice portal tidak memuat PPP username maupun router', function () {
    $router = Router::factory()->create(['nama_router' => 'RTR-RAHASIA-01']);
    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => Pelanggan::factory()->create(['no_reg' => 'BF2309202604'])->id,
        'router_id' => $router->id,
        'ppp_username' => 'BF2309202604_55555',
    ]);
    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
    $invoice->load(['pelanggan', 'layananPelanggan.paketLayanan', 'promo', 'pembayarans']);

    $this->view('pdf.invoice', [
        'invoice' => $invoice,
        'perusahaan' => Perusahaan::default(),
        'namaBrand' => 'BESTFIBER',
        'logoBase64' => null,
        'cetak' => InvoiceCetak::dari($invoice),
    ])->assertSee('BESTFIBER')->assertSee($invoice->pelanggan->namaLengkap())->assertDontSee('BF2309202604_55555')->assertDontSee('RTR-RAHASIA-01');

    $this->get(URL::signedRoute('portal.invoice.show', ['invoice' => $invoice->id]))
        ->assertOk()
        ->assertDontSee('BF2309202604_55555')
        ->assertDontSee('RTR-RAHASIA-01');
});
