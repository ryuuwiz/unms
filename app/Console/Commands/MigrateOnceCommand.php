<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class MigrateOnceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-once {--timeout=120 : Seconds to wait for the lock before giving up}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run database migrations guarded by a distributed lock, safe for concurrent multi-replica container boots.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Multiple replicas of the app container can boot concurrently during a
        // rolling deploy; without this lock each one would race to run
        // `migrate --force` against the same database at the same time.
        $lock = Cache::lock('app:migrate-once', 300);

        $timeout = (int) $this->option('timeout');

        try {
            // Lock::block() acquires, runs the callback, and releases the lock
            // automatically on success -- no manual release needed here.
            $lock->block($timeout, function () {
                $this->call('migrate', ['--force' => true]);
            });
        } catch (LockTimeoutException) {
            $this->components->warn('Could not acquire migration lock within timeout; another replica is likely migrating already. Skipping.');
        }

        return self::SUCCESS;
    }
}
