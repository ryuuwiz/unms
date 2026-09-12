<?php

use App\Console\Commands\MigrateOnceCommand;
use Illuminate\Support\Facades\Cache;

test('menjalankan migrate saat lock berhasil diperoleh', function () {
    $this->artisan(MigrateOnceCommand::class)
        ->assertExitCode(0);

    // Lock dilepas kembali setelah selesai, tidak menyisakan lock menggantung.
    expect(Cache::lock('app:migrate-once', 300)->get())->toBeTrue();
});

test('melewati migrate tanpa gagal jika lock sedang dipegang replica lain', function () {
    $lock = Cache::lock('app:migrate-once', 300);
    $lock->get();

    try {
        $this->artisan(MigrateOnceCommand::class, ['--timeout' => 1])
            ->expectsOutputToContain('Could not acquire migration lock')
            ->assertExitCode(0);
    } finally {
        $lock->release();
    }
});
