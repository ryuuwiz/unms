<?php

namespace App\Console\Commands;

use App\Support\MediaLibrary\PathGeneratorAcak;
use App\Support\MediaLibrary\PenyimpananMedia;
use App\Support\MediaLibraryVisibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

class PindahPathMediaCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'media:pindah-path-acak';

    /**
     * @var string
     */
    protected $description = 'Sekali jalan: memindahkan berkas media dari path {id}/ ke path acak public|private/{uuid}/ (ADR-0068)';

    /**
     * Aman diulang: media yang path lamanya sudah kosong dilewati. KTP dan dokumen tidak disentuh.
     */
    public function handle(): int
    {
        $lama = new DefaultPathGenerator;
        $baru = new PathGeneratorAcak;
        $dipindah = 0;
        $gagal = 0;

        foreach (Media::query()->lazyById() as $media) {
            /** @var Media $media */
            if (MediaLibraryVisibility::dokumenPribadi($media)) {
                continue;
            }

            foreach (array_unique([$media->disk, $media->conversions_disk]) as $disk) {
                if (Storage::disk($disk)->allFiles($lama->getPath($media)) === []) {
                    continue;
                }

                if (PenyimpananMedia::pindahkanDirektori($disk, $lama->getPath($media), $disk, $baru->getPath($media))) {
                    $dipindah++;
                } else {
                    $gagal++;
                    $this->components->error("Gagal memindahkan media #{$media->id} di disk [{$disk}].");
                }
            }
        }

        $this->components->info("{$dipindah} direktori media dipindahkan, {$gagal} gagal.");

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }
}
