<?php

use App\Console\Commands\EnsurePublicMediaBucketCommand;

test('skips when default filesystem disk is not s3', function () {
    config(['filesystems.default' => 'local']);

    $this->artisan(EnsurePublicMediaBucketCommand::class)
        ->expectsOutputToContain('Skipping bucket policy setup')
        ->assertExitCode(0);
});

test('skips when the s3 bucket is not configured', function () {
    config(['filesystems.default' => 's3']);
    config(['filesystems.disks.s3.bucket' => null]);

    $this->artisan(EnsurePublicMediaBucketCommand::class)
        ->expectsOutputToContain('AWS_BUCKET is not configured')
        ->assertExitCode(0);
});
