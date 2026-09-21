<?php

use App\Models\WaTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('membuang baris {link_portal_tiket} dari template WA tersimpan tanpa menyentuh isi lain', function () {
    $ticket = WaTemplate::factory()->create([
        'konten' => "Update {nomor_tiket}\n\nDetail penanganan: {link_portal_tiket}\nSalam,\n{nama_brand}",
    ]);
    $lain = WaTemplate::factory()->create(['konten' => "Halo {nama_pelanggan}\n\nSalam"]);

    (require database_path('migrations/2026_09_21_153614_strip_link_portal_tiket_from_wa_template.php'))->up();

    expect($ticket->fresh()->konten)->toBe("Update {nomor_tiket}\n\nSalam,\n{nama_brand}")
        ->and($lain->fresh()->konten)->toBe("Halo {nama_pelanggan}\n\nSalam");
});
