<?php

namespace App\Console\Commands;

use App\Support\MediaLibrary\PathGeneratorAcak;
use App\Support\MediaLibrary\PenyimpananMedia;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SinkronMediaS3Command extends Command
{
    /**
     * @var string
     */
    protected $signature = 'media:sinkron-s3';

    /**
     * @var string
     */
    protected $description = 'Memindahkan media hasil Failover Penyimpanan dari disk lokal kembali ke S3 (ADR-0068)';

    /**
     * Berhenti di kegagalan pertama: S3 masih mati, sisanya dicoba lagi di jadwal berikutnya.
     */
    public function handle(): int
    {
        $dipindah = 0;

        foreach (PenyimpananMedia::menungguSinkron()->lazyById() as $media) {
            /** @var Media $media */
            $tujuan = (string) $media->getCustomProperty(PenyimpananMedia::PROPERTI_FAILOVER);
            $direktori = (new PathGeneratorAcak)->getPath($media);

            if (! PenyimpananMedia::pindahkanDirektori($media->disk, $direktori, $tujuan, $direktori)) {
                PenyimpananMedia::tandaiS3Mati();
                $this->components->warn("S3 masih tidak terjangkau; {$dipindah} media dipindahkan.");

                return self::SUCCESS;
            }

            $media->disk = $tujuan;
            $media->conversions_disk = $tujuan;
            $media->forgetCustomProperty(PenyimpananMedia::PROPERTI_FAILOVER);
            $media->save();
            $dipindah++;
        }

        if ($dipindah > 0) {
            PenyimpananMedia::tandaiS3Pulih();
        }

        $this->components->info("{$dipindah} media dipindahkan ke S3.");

        return self::SUCCESS;
    }
}
