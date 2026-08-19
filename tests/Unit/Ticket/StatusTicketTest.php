<?php

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Ticket\SumberTicket;

test('status ticket transisi valid returns expected valid transitions according to state machine', function () {
    expect(StatusTicket::Baru->transisiValid())
        ->toBe([StatusTicket::Diproses, StatusTicket::Batal]);

    expect(StatusTicket::Diproses->transisiValid())
        ->toBe([StatusTicket::MenungguKonfirmasi, StatusTicket::Batal]);

    expect(StatusTicket::MenungguKonfirmasi->transisiValid())
        ->toBe([StatusTicket::Selesai, StatusTicket::Diproses]);

    expect(StatusTicket::Selesai->transisiValid())
        ->toBe([]);

    expect(StatusTicket::Batal->transisiValid())
        ->toBe([]);
});

test('status ticket is terminal detects terminal states', function () {
    expect(StatusTicket::Selesai->isTerminal())->toBeTrue();
    expect(StatusTicket::Batal->isTerminal())->toBeTrue();
    expect(StatusTicket::Baru->isTerminal())->toBeFalse();
    expect(StatusTicket::Diproses->isTerminal())->toBeFalse();
    expect(StatusTicket::MenungguKonfirmasi->isTerminal())->toBeFalse();
});

test('prioritas ticket calculates correct SLA duration hours', function () {
    expect(PrioritasTicket::Darurat->durasiSlaHours())->toBe(4);
    expect(PrioritasTicket::Tinggi->durasiSlaHours())->toBe(24);
    expect(PrioritasTicket::Sedang->durasiSlaHours())->toBe(72);
    expect(PrioritasTicket::Rendah->durasiSlaHours())->toBe(168);
});

test('all ticket enums provide labels and visual colors', function () {
    foreach (StatusTicket::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
        expect($case->color())->toBeString()->not->toBeEmpty();
    }

    foreach (JenisTicket::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
        expect($case->color())->toBeString()->not->toBeEmpty();
        expect($case->icon())->toBeString()->not->toBeEmpty();
    }

    foreach (PrioritasTicket::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
        expect($case->color())->toBeString()->not->toBeEmpty();
        expect($case->slaLabel())->toBeString()->not->toBeEmpty();
    }

    foreach (DivisiTicket::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
        expect($case->color())->toBeString()->not->toBeEmpty();
    }

    foreach (SumberTicket::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});
