<?php

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Livewire\Settings\Perusahaan;
use App\Livewire\Ticket\Create as TicketCreate;
use App\Models\Pelanggan;
use App\Models\Perusahaan as PerusahaanModel;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;

/*
 * Regression guard for the FileDoesNotExist incident on livewire-tmp uploads.
 *
 * Production sets LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=s3, but Livewire hardcodes
 * the 'tmp-for-tests' disk while app()->runningUnitTests() is true, and
 * Storage::fake() always installs a local adapter with a real root. The ordinary
 * media tests therefore pass on both the broken and the fixed code — they never
 * exercise the property that actually breaks production.
 *
 * Laravel builds a disk's PathPrefixer from $config['root'] regardless of driver
 * (FilesystemAdapter::__construct), and config/filesystems.php sets no 'root' on
 * the s3 disk. So there, path() returns a bare key such as "livewire-tmp/x.png"
 * which is not a real local file, while exists()/readStream() keep working. That
 * is why $file->getRealPath() fed into addMedia() blew up: addMedia() ends in an
 * is_file() check. addMediaFromDisk() reads through the disk instead.
 *
 * The disk below reproduces that split offline: empty prefixer root, real local
 * directory underneath. No network, no credentials.
 */
function bindS3LikeTemporaryUploadDisk(): void
{
    Storage::extend('s3like', function ($app, array $config) {
        $adapter = new LocalFilesystemAdapter($config['real_root']);

        // Deliberately no 'root' key — that is what keeps the prefixer empty.
        return new FilesystemAdapter(new Filesystem($adapter), $adapter, ['driver' => 's3like']);
    });

    config(['filesystems.disks.'.FileUploadConfiguration::disk() => [
        'driver' => 's3like',
        'real_root' => sys_get_temp_dir().'/livewire-s3like-'.uniqid(),
    ]]);
}

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, WaTemplateSeeder::class]);

    Storage::fake('public');
    Queue::fake([KirimWaBlastJob::class]);
    bindS3LikeTemporaryUploadDisk();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

test('the emulated disk reproduces the production s3 path() behaviour', function () {
    $disk = Storage::disk(FileUploadConfiguration::disk());

    $disk->put('livewire-tmp/probe.png', 'x');

    // The bug in one line: path() hands back a key that is not a real file,
    // which is exactly what addMedia()'s is_file() check chokes on.
    expect($disk->path('livewire-tmp/probe.png'))->toBe('livewire-tmp/probe.png')
        ->and(is_file($disk->path('livewire-tmp/probe.png')))->toBeFalse()
        ->and($disk->exists('livewire-tmp/probe.png'))->toBeTrue();
});

test('logo perusahaan tersimpan walau disk sementara livewire bukan disk lokal', function () {
    Livewire::actingAs($this->admin)
        ->test(Perusahaan::class)
        ->set('nama_perusahaan', 'PT Uji Coba')
        ->set('nama_brand', 'UJI')
        ->set('logo', UploadedFile::fake()->image('gobilling.png', 400, 150))
        ->call('save')
        ->assertHasNoErrors();

    $media = PerusahaanModel::default()->getFirstMedia('logo');

    expect($media)->not->toBeNull()
        ->and($media->file_name)->toBe('gobilling.png');
});

/*
 * This path swallows its exception (try/catch + Log::error), so the same bug
 * surfaced as a silent data loss in production: success toast, no photo.
 */
test('foto kendala tiket tersimpan walau disk sementara livewire bukan disk lokal', function () {
    $pelanggan = Pelanggan::factory()->create(['no_hp' => '088888888888']);

    Livewire::actingAs($this->admin)
        ->test(TicketCreate::class)
        ->set('jenis', JenisTicket::Gangguan->value)
        ->set('pelanggan_id', $pelanggan->id)
        ->set('prioritas', PrioritasTicket::Tinggi->value)
        ->set('divisis', [DivisiTicket::Teknisi->value])
        ->set('deskripsi', 'Kabel fiber optik putus tertabrak truk di depan rumah.')
        ->set('fotoKendala', UploadedFile::fake()->image('kendala.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::latest('id')->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->getFirstMedia('foto_kendala'))->not->toBeNull();
});
