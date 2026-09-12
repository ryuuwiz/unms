<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\HorizonUnhealthyNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class MonitorHorizonHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'horizon:monitor-health {--minutes=15 : Alert if no job has completed within this many minutes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Alert super_admin/noc if Horizon is inactive, paused, or has not completed any job recently (wedged worker detection).';

    /**
     * Execute the console command.
     */
    public function handle(MasterSupervisorRepository $masters, JobRepository $jobs): int
    {
        $thresholdMinutes = (int) $this->option('minutes');

        $problem = $this->detectProblem($masters, $jobs, $thresholdMinutes);

        if ($problem === null) {
            $this->components->info('Horizon is healthy.');

            return self::SUCCESS;
        }

        $this->components->error($problem);

        $this->alertOperators($problem);

        return self::FAILURE;
    }

    /**
     * Return a human-readable problem description, or null if Horizon looks healthy.
     */
    protected function detectProblem(MasterSupervisorRepository $masters, JobRepository $jobs, int $thresholdMinutes): ?string
    {
        $activeMasters = $masters->all();

        if (empty($activeMasters)) {
            return 'Horizon master supervisor is not running (no active masters registered in Redis).';
        }

        if (collect($activeMasters)->contains(fn ($master) => $master->status === 'paused')) {
            return 'Horizon is paused.';
        }

        $recentlyCompleted = collect($jobs->getCompleted())
            ->map(fn ($job) => (float) ($job->completed_at ?? 0))
            ->filter()
            ->max();

        if (! $recentlyCompleted) {
            // No completed jobs recorded at all yet is only a problem once the
            // container has been up long enough that we'd expect activity.
            return null;
        }

        $minutesSinceLastJob = Carbon::createFromTimestamp($recentlyCompleted)->diffInMinutes(now());

        if ($minutesSinceLastJob >= $thresholdMinutes) {
            return "Horizon has not completed a job in {$minutesSinceLastJob} minutes (threshold: {$thresholdMinutes}).";
        }

        return null;
    }

    /**
     * Notify super_admin and noc users so a wedged Horizon doesn't fail silently.
     */
    protected function alertOperators(string $problem): void
    {
        $recipients = User::role(['super_admin', 'noc'])->get();

        if ($recipients->isEmpty()) {
            return;
        }

        // Reuses the existing notification delivery pattern (mail + WhatsApp
        // where configured) rather than introducing a new channel.
        foreach ($recipients as $recipient) {
            $recipient->notify(new HorizonUnhealthyNotification($problem));
        }
    }
}
