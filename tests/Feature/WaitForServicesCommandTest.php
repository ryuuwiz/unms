<?php

use App\Console\Commands\WaitForServicesCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

test('succeeds immediately when database and redis are reachable', function () {
    $this->artisan(WaitForServicesCommand::class)
        ->expectsOutputToContain('Database and Redis are reachable.')
        ->assertExitCode(0);
});

test('fails loudly after the timeout when redis is unreachable', function () {
    // Point at a port nothing listens on so the connection is refused
    // immediately instead of waiting out a slow network timeout.
    $originalPort = config('database.redis.default.port');
    config(['database.redis.default.port' => 1]);
    Redis::purge('default');

    try {
        $this->artisan(WaitForServicesCommand::class, ['--timeout' => 1, '--interval' => 1])
            ->expectsOutputToContain('FATAL: Redis did not become reachable within 1s.')
            ->assertExitCode(1);
    } finally {
        // RefreshDatabase's teardown needs a working default connection, and
        // the redis config change would otherwise leak into it via the same
        // request lifecycle -- restore it before the test finishes, not just
        // purge the cached connection.
        config(['database.redis.default.port' => $originalPort]);
        Redis::purge('default');
    }
});

test('fails loudly after the timeout when the database is unreachable', function () {
    $originalPort = config('database.connections.mysql.port');
    config(['database.connections.mysql.port' => 1]);
    DB::purge('mysql');

    try {
        $this->artisan(WaitForServicesCommand::class, ['--timeout' => 1, '--interval' => 1])
            ->expectsOutputToContain('FATAL: Database did not become reachable within 1s.')
            ->assertExitCode(1);
    } finally {
        // RefreshDatabase rolls back a transaction on this connection after
        // the test -- it must be pointed at a real port again before that.
        config(['database.connections.mysql.port' => $originalPort]);
        DB::purge('mysql');
    }
});
