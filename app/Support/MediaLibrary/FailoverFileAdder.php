<?php

namespace App\Support\MediaLibrary;

use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use League\Flysystem\FilesystemException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\DiskCannotBeAccessed;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Upload tetap berhasil saat S3 tidak terjangkau: coba S3, bila gagal simpan ke disk cadangan lokal
 * dan tandai media untuk disinkronkan kembali (Failover Penyimpanan, ADR-0068).
 */
class FailoverFileAdder extends FileAdder
{
    public function toMediaCollection(string $collectionName = 'default', string $diskName = ''): Media
    {
        $disk = $this->determineDiskName($diskName, $collectionName);

        if (! PenyimpananMedia::diskS3($disk)) {
            return parent::toMediaCollection($collectionName, $diskName);
        }

        if (! PenyimpananMedia::s3Mati()) {
            try {
                return parent::toMediaCollection($collectionName, $diskName);
            } catch (DiskCannotBeAccessed|FilesystemException|AwsException $e) {
                PenyimpananMedia::tandaiS3Mati();
                Log::warning('S3 tidak terjangkau, media disimpan ke disk lokal.', [
                    'koleksi' => $collectionName,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->customProperties[PenyimpananMedia::PROPERTI_FAILOVER] = $disk;

        return parent::toMediaCollection($collectionName, PenyimpananMedia::diskCadangan($collectionName));
    }

    /**
     * Penulisan stream ke S3 melempar exception setelah record media tersimpan; hapus record itu
     * tanpa event (yang akan mencoba menghapus berkas di S3 yang sedang mati) sebelum mencoba disk lain.
     */
    protected function processMediaItem(HasMedia $model, Media $media, FileAdder $fileAdder): void
    {
        try {
            parent::processMediaItem($model, $media, $fileAdder);
        } catch (Throwable $e) {
            if ($media->exists) {
                $media->newQuery()->whereKey($media->getKey())->delete();
            }

            throw $e;
        }
    }
}
