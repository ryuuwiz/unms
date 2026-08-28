<?php

use App\Enums\Wa\KategoriTemplateWa;
use App\Livewire\Settings\WhatsappSettings;
use App\Models\User;
use App\Models\WaTemplate;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        WaTemplateSeeder::class,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

test('halaman pengaturan whatsapp dapat diakses super admin', function () {
    Http::fake([
        '*/api/device/info' => Http::response([
            'status' => true,
            'data' => ['connected' => true, 'phone' => '628970919525', 'quota' => 2000],
        ], 200),
    ]);

    $this->actingAs($this->admin)
        ->get(route('settings.whatsapp'))
        ->assertOk()
        ->assertSee('WhatsApp Gateway');
});

test('super admin dapat mengirim pesan uji coba dari halaman settings', function () {
    Http::fake([
        '*/api/v2/send-message' => Http::response([
            'status' => true,
            'message' => 'Pesan berhasil',
        ], 200),
    ]);

    Livewire::actingAs($this->admin)
        ->test(WhatsappSettings::class)
        ->set('testPhone', '081234567890')
        ->set('testMessage', 'Pesan Uji Coba Integrasi')
        ->call('kirimPesanUjiCoba')
        ->assertHasNoErrors();
});

test('super admin dapat membuat template pesan baru', function () {
    Livewire::actingAs($this->admin)
        ->test(WhatsappSettings::class)
        ->set('template_kode', 'tiket_survey_lapangan')
        ->set('template_nama', 'Notifikasi Survey Lapangan')
        ->set('template_kategori', KategoriTemplateWa::Tiket->value)
        ->set('template_konten', 'Halo {nama_pelanggan}, tim kami akan survey ke lokasi Anda.')
        ->set('template_is_aktif', true)
        ->call('simpanTemplate')
        ->assertHasNoErrors();

    expect(WaTemplate::where('kode', 'tiket_survey_lapangan')->exists())->toBeTrue();
});

test('super admin dapat toggle status aktif template', function () {
    $template = WaTemplate::first();
    $initialStatus = $template->is_aktif;

    Livewire::actingAs($this->admin)
        ->test(WhatsappSettings::class)
        ->call('toggleTemplateStatus', $template->id);

    expect($template->fresh()->is_aktif)->toBe(! $initialStatus);
});
