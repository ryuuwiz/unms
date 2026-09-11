<?php

use App\Events\InvoicePaidEvent;
use App\Listeners\TriggerEmailNotifikasiListener;
use App\Models\Invoice;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Notifications\InvoicePaymentConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// Listener diuji langsung (bukan lewat event() dispatch penuh) agar terisolasi dari listener
// InvoicePaidEvent lain (mis. TriggerMikrotikAktivasiStubListener) yang butuh setup RBAC/router sendiri.
test('invoice paid event mengirim email konfirmasi pembayaran ke pelanggan yang memiliki email', function () {
    Notification::fake();

    $pelanggan = Pelanggan::factory()->create(['email' => 'pelanggan@example.com']);
    $invoice = Invoice::factory()->create(['pelanggan_id' => $pelanggan->id]);
    $pembayaran = Pembayaran::factory()->create(['invoice_id' => $invoice->id]);

    (new TriggerEmailNotifikasiListener)->handle(new InvoicePaidEvent($invoice, $pembayaran));

    Notification::assertSentTo(
        $pelanggan,
        InvoicePaymentConfirmedNotification::class,
        fn (InvoicePaymentConfirmedNotification $notification) => $notification->invoice->id === $invoice->id
    );
});

test('invoice paid event tidak mengirim email jika pelanggan tidak memiliki email', function () {
    Notification::fake();

    $pelanggan = Pelanggan::factory()->create(['email' => null]);
    $invoice = Invoice::factory()->create(['pelanggan_id' => $pelanggan->id]);
    $pembayaran = Pembayaran::factory()->create(['invoice_id' => $invoice->id]);

    (new TriggerEmailNotifikasiListener)->handle(new InvoicePaidEvent($invoice, $pembayaran));

    Notification::assertNothingSentTo($pelanggan);
});
