<?php

/**
 * Regression guard for the "logo gagal diunggah" incident (docs/adr/0037):
 * when the default filesystem disk is 's3', Livewire's WithFileUploads sends
 * the browser directly to AWS_ENDPOINT via presigned PUT/GET URLs, bypassing
 * the Laravel backend entirely. An internal-only endpoint (e.g. a Docker
 * Compose service name) breaks every upload feature in the app client-side,
 * even though server-to-server S3 calls keep working fine.
 *
 * These tests only assert against the committed .env.docker.example template
 * (the actual deployed .env is not part of this repo) so they run the same
 * way for every developer and in CI.
 */
function parseEnvExample(string $path): array
{
    // Unit tests don't boot the framework (see tests/Pest.php), so base_path()
    // isn't available — resolve the project root relative to this file instead.
    $lines = file(dirname(__DIR__, 2).'/'.$path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $values = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value, " \t\"'");
    }

    return $values;
}

test('.env.docker.example keeps AWS_ENDPOINT browser-reachable, not an internal-only service name', function () {
    $env = parseEnvExample('.env.docker.example');

    expect($env)->toHaveKey('AWS_ENDPOINT')
        ->and($env)->toHaveKey('AWS_URL');

    $endpointHost = parse_url($env['AWS_ENDPOINT'], PHP_URL_HOST);
    $urlHost = parse_url($env['AWS_URL'], PHP_URL_HOST);

    // AWS_URL is only used for building plain read links (Storage::url()) and
    // is already required to be a real public domain. Presigned upload/preview
    // URLs are built from AWS_ENDPOINT instead, so both must share that same
    // publicly resolvable host or client-side uploads fail silently.
    expect($endpointHost)->toBe($urlHost)
        ->and($endpointHost)->not->toBe('rustfs'); // the Docker Compose internal service name
});

test('.env.docker.example keeps Livewire temporary uploads on the local disk explicitly', function () {
    $env = parseEnvExample('.env.docker.example');

    // Browser->S3 presigned PUT needs bucket CORS and failed in production
    // (ADR-0067). Temp uploads proxy through the app instead; final files
    // still go to s3. Valid for a single replica with a persisted storage/app.
    expect($env['FILESYSTEM_DISK'] ?? null)->toBe('s3')
        ->and($env['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'] ?? null)->toBe('local');
});
