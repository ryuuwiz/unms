<?php

namespace App\Services\Storage;

use App\DTO\Storage\S3HealthCheckResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class S3HealthCheckService
{
    private const PING_PATH = 'health-check/ping.txt';

    /**
     * Cek kesehatan disk S3/RustFS: bucket bisa diakses DAN URL publiknya benar-benar
     * bisa di-fetch (bukan cuma dicek lewat SDK) -- lihat ADR-0038: kebijakan public-read
     * bucket ini invisible dari sisi Laravel, baru ketahuan gagal saat browser/HTTP client
     * benar-benar fetch URL-nya dan dapat 403.
     */
    public function check(): S3HealthCheckResult
    {
        $diskName = (string) config('filesystems.default');

        if ($diskName !== 's3') {
            return S3HealthCheckResult::notApplicable($diskName);
        }

        $bucket = config('filesystems.disks.s3.bucket');

        if (empty($bucket)) {
            return new S3HealthCheckResult(
                applicable: true,
                diskName: $diskName,
                bucketOk: false,
                errorMessage: 'AWS_BUCKET belum dikonfigurasi.',
                checkedAt: Carbon::now()->toDateTimeString(),
            );
        }

        try {
            $bucketOk = Storage::disk('s3')->getClient()->doesBucketExist($bucket);
        } catch (Throwable $e) {
            return new S3HealthCheckResult(
                applicable: true,
                diskName: $diskName,
                bucket: $bucket,
                bucketOk: false,
                errorMessage: "Gagal menghubungi S3: {$e->getMessage()}",
                checkedAt: Carbon::now()->toDateTimeString(),
            );
        }

        if (! $bucketOk) {
            return new S3HealthCheckResult(
                applicable: true,
                diskName: $diskName,
                bucket: $bucket,
                bucketOk: false,
                errorMessage: "Bucket [{$bucket}] tidak ditemukan.",
                checkedAt: Carbon::now()->toDateTimeString(),
            );
        }

        [$publicUrlOk, $urlError] = $this->checkPublicUrlReachable();

        return new S3HealthCheckResult(
            applicable: true,
            diskName: $diskName,
            bucket: $bucket,
            bucketOk: true,
            publicUrlOk: $publicUrlOk,
            errorMessage: $urlError,
            checkedAt: Carbon::now()->toDateTimeString(),
        );
    }

    /**
     * Tulis file kecil ke path tetap, lalu benar-benar fetch URL publiknya via HTTP client
     * (bukan cuma tanya SDK) -- ini satu-satunya cara mendeteksi bucket policy public-read
     * yang salah/dicabut (ADR-0038).
     *
     * @return array{0: bool, 1: ?string}
     */
    private function checkPublicUrlReachable(): array
    {
        $marker = 'ping '.Carbon::now()->toIso8601String();

        try {
            Storage::disk('s3')->put(self::PING_PATH, $marker);
            $url = Storage::disk('s3')->url(self::PING_PATH);

            $response = Http::timeout(5)->get($url);

            if (! $response->successful()) {
                return [false, "URL publik mengembalikan HTTP {$response->status()} (cek kebijakan public-read bucket)."];
            }

            return [true, null];
        } catch (Throwable $e) {
            return [false, "Gagal mengakses URL publik: {$e->getMessage()}"];
        }
    }
}
