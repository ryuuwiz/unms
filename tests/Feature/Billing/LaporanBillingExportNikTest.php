<?php

use App\Exports\LaporanBillingExport;
use App\Models\Invoice;
use App\Models\Pelanggan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('nik column is included in headings and mapped from pelanggan', function () {
    $pelanggan = Pelanggan::factory()->create(['nik' => '3201010101010001']);
    $invoice = Invoice::factory()->create(['pelanggan_id' => $pelanggan->id]);

    $export = new LaporanBillingExport;

    expect($export->headings())->toContain('NIK');

    $row = $export->map($invoice->fresh(['pelanggan']));

    $nikIndex = array_search('NIK', $export->headings(), true);

    expect($row[$nikIndex])->toBe('3201010101010001');
});

test('nik column falls back to dash when pelanggan has no nik', function () {
    $pelanggan = Pelanggan::factory()->create(['nik' => null]);
    $invoice = Invoice::factory()->create(['pelanggan_id' => $pelanggan->id]);

    $export = new LaporanBillingExport;
    $row = $export->map($invoice->fresh(['pelanggan']));

    $nikIndex = array_search('NIK', $export->headings(), true);

    expect($row[$nikIndex])->toBe('-');
});
