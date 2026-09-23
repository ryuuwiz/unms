<?php

use App\Services\Storage\S3HealthCheckService;

test('not applicable when default filesystem disk is not s3', function () {
    config(['filesystems.default' => 'local']);

    $result = app(S3HealthCheckService::class)->check();

    expect($result->applicable)->toBeFalse()
        ->and($result->diskName)->toBe('local')
        ->and($result->bucketOk)->toBeNull()
        ->and($result->publicUrlOk)->toBeNull();
});

test('reports bucket not configured when AWS_BUCKET is empty', function () {
    config(['filesystems.default' => 's3']);
    config(['filesystems.disks.s3.bucket' => null]);

    $result = app(S3HealthCheckService::class)->check();

    expect($result->applicable)->toBeTrue()
        ->and($result->bucketOk)->toBeFalse()
        ->and($result->errorMessage)->toContain('belum dikonfigurasi');
});
