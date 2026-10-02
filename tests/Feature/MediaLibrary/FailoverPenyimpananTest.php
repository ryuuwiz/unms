<?php

use App\Models\Perusahaan;
use App\Models\User;
use App\Support\MediaLibrary\PenyimpananMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

/** Disk s3 sungguhan yang menunjuk ke port tertutup: koneksi langsung ditolak. */
function s3TakTerjangkau(): void
{
    config([
        'filesystems.disks.s3' => array_merge(config('filesystems.disks.s3'), [
            'key' => 'uji', 'secret' => 'uji', 'region' => 'us-east-1', 'bucket' => 'uji',
            'endpoint' => 'http://127.0.0.1:1', 'use_path_style_endpoint' => true, 'retries' => 0,
        ]),
        'media-library.disk_name' => 's3',
    ]);
    Storage::forgetDisk('s3');
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

test('S3 tidak terjangkau: Media Privat jatuh ke disk local, Media Publik ke public, dan ditandai menunggu sinkron', function () {
    s3TakTerjangkau();

    $foto = User::factory()->create()->addMedia(UploadedFile::fake()->image('rumah.jpg'))->toMediaCollection('foto_profil');
    $logo = Perusahaan::create(['nama_perusahaan' => 'PT Uji', 'nama_brand' => 'UJI', 'is_default' => true])
        ->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('logo');

    expect($foto->disk)->toBe('local')
        ->and($foto->getCustomProperty(PenyimpananMedia::PROPERTI_FAILOVER))->toBe('s3')
        ->and($logo->disk)->toBe('public')
        ->and(PenyimpananMedia::s3Mati())->toBeTrue()
        ->and(PenyimpananMedia::menungguSinkron()->count())->toBe(2)
        ->and(Media::count())->toBe(2);

    Storage::disk('local')->assertExists($foto->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($logo->getPathRelativeToRoot());
});

test('sinkron memindahkan media failover ke S3 dan menghapus salinan lokal', function () {
    s3TakTerjangkau();
    $foto = User::factory()->create()->addMedia(UploadedFile::fake()->image('rumah.jpg'))->toMediaCollection('foto_profil');
    $path = $foto->getPathRelativeToRoot();

    Storage::fake('s3');
    $this->artisan('media:sinkron-s3')->assertSuccessful();

    $foto->refresh();
    expect($foto->disk)->toBe('s3')
        ->and($foto->conversions_disk)->toBe('s3')
        ->and($foto->hasCustomProperty(PenyimpananMedia::PROPERTI_FAILOVER))->toBeFalse()
        ->and(PenyimpananMedia::s3Mati())->toBeFalse();
    Storage::disk('s3')->assertExists($path);
    Storage::disk('local')->assertMissing($path);
});

test('path media acak tanpa ID, Media Privat bertanda tangan sedangkan Media Publik permanen', function () {
    s3TakTerjangkau();
    $user = User::factory()->create();

    $privat = $user->media()->create([
        'collection_name' => 'foto_profil', 'name' => 'a', 'file_name' => 'a.jpg', 'disk' => 's3', 'conversions_disk' => 's3',
        'size' => 1, 'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => [], 'responsive_images' => [],
    ]);
    $publik = $privat->replicate()->fill(['collection_name' => 'logo', 'uuid' => (string) str()->uuid()]);
    $publik->save();

    expect($privat->getPathRelativeToRoot())->toBe("private/{$privat->uuid}/a.jpg")
        ->and($privat->getUrl())->toContain('X-Amz-Signature')
        ->and($publik->getPathRelativeToRoot())->toBe("public/{$publik->uuid}/a.jpg")
        ->and($publik->getUrl())->not->toContain('X-Amz-Signature');
});

test('pindah path memindahkan berkas lama {id}/ ke path acak dan melewati KTP', function () {
    $user = User::factory()->create();
    $foto = $user->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('foto_profil');
    $ktp = $user->addMedia(UploadedFile::fake()->image('ktp.jpg'))->toMediaCollection('ktp');

    $baru = $foto->getPathRelativeToRoot();
    Storage::disk('public')->move($baru, "{$foto->id}/a.jpg");

    $this->artisan('media:pindah-path-acak')->assertSuccessful();

    Storage::disk('public')->assertExists($baru);
    Storage::disk('public')->assertMissing("{$foto->id}/a.jpg");
    Storage::disk('local')->assertExists("{$ktp->id}/ktp.jpg");
});
