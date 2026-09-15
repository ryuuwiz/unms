<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EnsurePublicMediaBucketCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ensure-public-media-bucket';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply a public-read bucket policy on the S3/RustFS media bucket so browsers can load media URLs directly.';

    /**
     * Execute the console command.
     *
     * RustFS (like MinIO) denies anonymous GETs by default regardless of the
     * per-object ACL Media Library/Flysystem sets on upload — access is
     * controlled at the bucket-policy level instead. Without this, every
     * public media URL (company logo, ticket photos) 403s in the browser
     * even though FILESYSTEM_DISK=s3 and AWS_URL are correctly configured.
     */
    public function handle(): int
    {
        $disk = config('filesystems.default');

        if ($disk !== 's3') {
            $this->components->info("Default disk is [{$disk}], not [s3]. Skipping bucket policy setup.");

            return self::SUCCESS;
        }

        $bucket = config('filesystems.disks.s3.bucket');

        if (empty($bucket)) {
            $this->components->warn('AWS_BUCKET is not configured. Skipping bucket policy setup.');

            return self::SUCCESS;
        }

        $client = Storage::disk('s3')->getClient();

        if (! $client->doesBucketExist($bucket)) {
            $client->createBucket(['Bucket' => $bucket]);
            $this->components->info("Created missing bucket [{$bucket}].");
        }

        $policy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [[
                'Effect' => 'Allow',
                'Principal' => '*',
                'Action' => ['s3:GetObject'],
                'Resource' => ["arn:aws:s3:::{$bucket}/*"],
            ]],
        ]);

        try {
            $client->putBucketPolicy(['Bucket' => $bucket, 'Policy' => $policy]);
            $this->components->info("Public-read bucket policy applied to [{$bucket}].");
        } catch (\Throwable $e) {
            $this->components->warn("Could not apply bucket policy to [{$bucket}]: {$e->getMessage()}");
        }

        return self::SUCCESS;
    }
}
