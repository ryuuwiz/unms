<?php

use App\DTO\Storage\S3HealthCheckResult;
use App\Enums\UserStatus;
use App\Livewire\MediaLibrary\Index;
use App\Models\BerkasUmum;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Storage\S3HealthCheckService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function buatMediaDummy($model, string $collection): Media
{
    $path = storage_path('app/dummy-'.uniqid().'.png');
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

    return $model->addMedia($path)->preservingOriginal()->toMediaCollection($collection);
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');
});

test('super_admin can access media library page', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->assertOk();
});

test('admin without media_library.lihat permission cannot access media library page', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertForbidden();
});

test('listing shows non-restricted media but excludes pelanggan ktp and dokumen', function () {
    $ticket = Ticket::factory()->create();
    $ticketMedia = buatMediaDummy($ticket, 'foto_kendala');

    $pelanggan = Pelanggan::factory()->create();
    $ktp = buatMediaDummy($pelanggan, 'ktp');
    $dokumen = buatMediaDummy($pelanggan, 'dokumen');

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->assertSee($ticketMedia->file_name)
        ->assertDontSee($ktp->file_name)
        ->assertDontSee($dokumen->file_name);
});

test('collection filter narrows the listing', function () {
    $ticket = Ticket::factory()->create();
    $kendala = buatMediaDummy($ticket, 'foto_kendala');
    $pengerjaan = buatMediaDummy($ticket, 'foto_pemasangan');

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('collectionFilter', 'foto_kendala')
        ->assertSee($kendala->file_name)
        ->assertDontSee($pengerjaan->file_name);
});

test('user with media_library.hapus can delete a non-restricted media item', function () {
    $ticket = Ticket::factory()->create();
    $media = buatMediaDummy($ticket, 'foto_kendala');

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('deleteMedia', $media->id)
        ->assertHasNoErrors();

    expect(Media::find($media->id))->toBeNull();
});

test('user without media_library.hapus cannot delete media', function () {
    $mediaLihatOnlyRole = Role::firstOrCreate(['name' => 'media_viewer']);
    $mediaLihatOnlyRole->givePermissionTo('media_library.lihat');

    $viewer = User::factory()->create(['status' => UserStatus::Active]);
    $viewer->assignRole('media_viewer');

    $ticket = Ticket::factory()->create();
    $media = buatMediaDummy($ticket, 'foto_kendala');

    Livewire::actingAs($viewer)
        ->test(Index::class)
        ->call('deleteMedia', $media->id)
        ->assertForbidden();

    expect(Media::find($media->id))->not->toBeNull();
});

test('cannot delete a pelanggan ktp media even by guessing its id', function () {
    $pelanggan = Pelanggan::factory()->create();
    $ktp = buatMediaDummy($pelanggan, 'ktp');

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('deleteMedia', $ktp->id);

    expect(Media::find($ktp->id))->not->toBeNull();
});

test('page shows not-applicable state when default disk is not s3', function () {
    config(['filesystems.default' => 'local']);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->assertSee('Tidak berlaku');
});

test('runCheck refreshes the health result via the service', function () {
    $mock = Mockery::mock(S3HealthCheckService::class);
    $mock->shouldReceive('check')
        ->twice() // mount() + runCheck()
        ->andReturn(new S3HealthCheckResult(
            applicable: true,
            diskName: 's3',
            bucket: 'gobilling-media',
            bucketOk: true,
            publicUrlOk: true,
            checkedAt: now()->toDateTimeString(),
        ));
    $this->instance(S3HealthCheckService::class, $mock);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('runCheck')
        ->assertSee('gobilling-media');
});

test('user with media_library.unggah can upload a file into a new BerkasUmum', function () {
    $file = UploadedFile::fake()->create('laporan.xlsx', 100);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('uploads', [$file])
        ->call('uploadFiles')
        ->assertHasNoErrors();

    expect(BerkasUmum::count())->toBe(1)
        ->and(Media::where('file_name', 'laporan.xlsx')->exists())->toBeTrue();
});

test('upload rejects disallowed file types', function () {
    $file = UploadedFile::fake()->create('script.exe', 100);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('uploads', [$file])
        ->call('uploadFiles')
        ->assertHasErrors(['uploads.0']);

    expect(BerkasUmum::count())->toBe(0);
});

test('user without media_library.unggah cannot upload', function () {
    $mediaLihatOnlyRole = Role::firstOrCreate(['name' => 'media_viewer_upload_test']);
    $mediaLihatOnlyRole->givePermissionTo('media_library.lihat');

    $viewer = User::factory()->create(['status' => UserStatus::Active]);
    $viewer->assignRole('media_viewer_upload_test');

    $file = UploadedFile::fake()->image('foto.jpg');

    Livewire::actingAs($viewer)
        ->test(Index::class)
        ->set('uploads', [$file])
        ->call('uploadFiles')
        ->assertForbidden();

    expect(BerkasUmum::count())->toBe(0);
});
