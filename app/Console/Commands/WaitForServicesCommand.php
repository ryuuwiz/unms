<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class WaitForServicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:wait-for-services
                            {--timeout=60 : Seconds to wait before giving up}
                            {--interval=2 : Seconds to sleep between connection attempts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Block until the database and Redis are reachable, or fail loudly after a timeout.';

    /**
     * Execute the console command.
     *
     * Database and Redis run as separate services from the app container in
     * production. Nothing guarantees they finish starting before this one does,
     * and Redis in particular can also restart independently later while the
     * app container keeps running -- without this check, the first thing to
     * touch either connection (migrate-once's Cache::lock, or Horizon) fails
     * with a raw PDO/Redis exception instead of a clear, actionable message.
     */
    public function handle(): int
    {
        $timeout = (int) $this->option('timeout');
        $interval = (int) $this->option('interval');
        $deadline = microtime(true) + $timeout;

        while (true) {
            $unreachable = $this->firstUnreachableService();

            if ($unreachable === null) {
                $this->components->info('Database and Redis are reachable.');

                return self::SUCCESS;
            }

            if (microtime(true) >= $deadline) {
                $this->components->error("FATAL: {$unreachable} did not become reachable within {$timeout}s. Refusing to start.");

                return self::FAILURE;
            }

            sleep($interval);
        }
    }

    /**
     * Return the name of the first service that failed to respond, or null if
     * both are reachable.
     */
    protected function firstUnreachableService(): ?string
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            DB::purge();

            return 'Database';
        }

        try {
            Redis::connection()->ping();
        } catch (Throwable) {
            Redis::purge();

            return 'Redis';
        }

        return null;
    }
}
