<?php

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('ticket model auto-generates unique standardized ticket number on creation', function () {
    $pelanggan = Pelanggan::factory()->create();
    $year = Carbon::now()->format('Y');

    $ticket1 = Ticket::create([
        'jenis' => JenisTicket::Gangguan,
        'pelanggan_id' => $pelanggan->id,
        'prioritas' => PrioritasTicket::Sedang,
        'deskripsi' => 'Koneksi internet loss merah pada modem.',
    ]);
    $ticket1->divisis()->create(['divisi' => DivisiTicket::Noc]);

    $ticket2 = Ticket::create([
        'jenis' => JenisTicket::Pemasangan,
        'pelanggan_id' => $pelanggan->id,
        'prioritas' => PrioritasTicket::Tinggi,
        'deskripsi' => 'Pemasangan baru pelanggan cluster arsyila.',
    ]);
    $ticket2->divisis()->create(['divisi' => DivisiTicket::Teknisi]);

    expect($ticket1->nomor_ticket)->toBe("TCK-{$year}-000001")
        ->and($ticket2->nomor_ticket)->toBe("TCK-{$year}-000002");
});

test('ticket model auto-computes sla_target_selesai based on prioritas on creation', function () {
    Carbon::setTestNow(Carbon::create(2026, 8, 20, 10, 0, 0));
    $pelanggan = Pelanggan::factory()->create();

    $ticketDarurat = Ticket::create([
        'jenis' => JenisTicket::Gangguan,
        'pelanggan_id' => $pelanggan->id,
        'prioritas' => PrioritasTicket::Darurat,
        'deskripsi' => 'Kabel FO putus tertabrak truk.',
    ]);
    $ticketDarurat->divisis()->create(['divisi' => DivisiTicket::Noc]);

    expect($ticketDarurat->sla_target_selesai->toDateTimeString())
        ->toBe(Carbon::now()->addHours(4)->toDateTimeString());

    Carbon::setTestNow();
});

test('ticket model detects overdue status and human readable remaining sla', function () {
    $pelanggan = Pelanggan::factory()->create();

    $ticketActive = Ticket::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'status' => StatusTicket::Diproses,
        'sla_target_selesai' => Carbon::now()->addHours(5),
    ]);

    expect($ticketActive->isOverdue())->toBeFalse();

    $ticketOverdue = Ticket::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'status' => StatusTicket::Diproses,
        'sla_target_selesai' => Carbon::now()->subHours(2),
    ]);

    expect($ticketOverdue->isOverdue())->toBeTrue()
        ->and($ticketOverdue->sisaWaktuSla())->toContain('Lewat');
});

test('ticket scopes work as expected', function () {
    $pelanggan = Pelanggan::factory()->create([
        'nama_depan' => 'Budi',
        'nama_belakang' => 'Santoso',
    ]);
    $techUser = User::factory()->create();

    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'pic_id' => $techUser->id,
        'jenis' => JenisTicket::Pemasangan,
        'status' => StatusTicket::Diproses,
    ]);
    $ticket->divisis()->create(['divisi' => DivisiTicket::Teknisi]);

    expect(Ticket::assignedTo($techUser->id)->count())->toBe(1);
    expect(Ticket::jenis(JenisTicket::Pemasangan)->count())->toBe(1);
    expect(Ticket::status(StatusTicket::Diproses)->count())->toBe(1);
    expect(Ticket::divisi(DivisiTicket::Teknisi)->count())->toBe(1);
    expect(Ticket::search('Budi')->count())->toBe(1);
});
