<?php

use App\Actions\LayananPelanggan\UbahStatusLayananAction;
use App\Enums\ProvisioningStatus;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Events\InvoicePaidEvent;
use App\Events\LayananPelangganStatusChangedEvent;
use App\Jobs\Mikrotik\DisablePppoeAccountJob;
use App\Jobs\Mikrotik\EnablePppoeAccountJob;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->router = Router::factory()->online()->create();
    $this->pelanggan = Pelanggan::factory()->create();
    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);
    $this->layanan = LayananPelanggan::factory()->create([
        'router_id' => $this->router->id,
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'status' => StatusLayanan::Aktif,
        'provisioning_status' => ProvisioningStatus::Success,
        'terprovisi_pada' => Carbon::now()->subDays(10),
    ]);
});

test('UbahStatusLayananAction updates status and dispatches event', function () {
    Event::fake([LayananPelangganStatusChangedEvent::class]);

    $action = new UbahStatusLayananAction;
    $updated = $action->execute(
        layanan: $this->layanan,
        statusBaru: StatusLayanan::Suspend,
        actor: null,
        catatan: 'Isolir keterlambatan pembayaran'
    );

    expect($updated->status)->toBe(StatusLayanan::Suspend);

    Event::assertDispatched(LayananPelangganStatusChangedEvent::class, function ($event) {
        return $event->layanan->id === $this->layanan->id
            && $event->statusLama === StatusLayanan::Aktif
            && $event->statusBaru === StatusLayanan::Suspend;
    });
});

test('suspending service dispatches DisablePppoeAccountJob', function () {
    Queue::fake([DisablePppoeAccountJob::class]);

    $action = new UbahStatusLayananAction;
    $action->execute(
        layanan: $this->layanan,
        statusBaru: StatusLayanan::Suspend
    );

    Queue::assertPushed(DisablePppoeAccountJob::class, function ($job) {
        return $job->layanan->id === $this->layanan->id && $job->queue === 'mikrotik-high';
    });
});

test('activating suspended service dispatches EnablePppoeAccountJob', function () {
    Queue::fake([EnablePppoeAccountJob::class]);

    $this->layanan->update(['status' => StatusLayanan::Suspend]);

    $action = new UbahStatusLayananAction;
    $action->execute(
        layanan: $this->layanan,
        statusBaru: StatusLayanan::Aktif
    );

    Queue::assertPushed(EnablePppoeAccountJob::class, function ($job) {
        return $job->layanan->id === $this->layanan->id && $job->queue === 'mikrotik-high';
    });
});

test('paying invoice triggers Mikrotik activation listener', function () {
    Queue::fake([EnablePppoeAccountJob::class]);

    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'status' => StatusInvoice::Lunas,
    ]);

    $pembayaran = Pembayaran::factory()->create([
        'invoice_id' => $invoice->id,
    ]);

    event(new InvoicePaidEvent($invoice, $pembayaran));

    Queue::assertPushed(EnablePppoeAccountJob::class, function ($job) {
        return $job->layanan->id === $this->layanan->id;
    });
});
