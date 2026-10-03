<?php

namespace App\Console\Commands;

use App\Enums\StatusRouter;
use App\Jobs\Mikrotik\PingRouterJob;
use App\Models\Router;
use Illuminate\Console\Command;

class MikrotikPingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:ping {--router= : ID router tertentu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ping router MikroTik dan perbarui status konektivitas serta resource sistem';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $routerId = $this->option('router');

        $query = Router::query();
        if ($routerId) {
            $query->where('id', $routerId);
        } else {
            // CONTEXT.md "Ping Router": router Online cukup dicek tiap 2 jam; yang lain tiap putaran jadwal (5 menit).
            $query->where(fn ($q) => $q
                ->where('status_koneksi', '!=', StatusRouter::Online)
                ->orWhereNull('last_ping_at')
                ->orWhere('last_ping_at', '<=', now()->subHours(2)));
        }

        $routers = $query->get();

        if ($routers->isEmpty()) {
            $this->warn('Tidak ada router yang ditemukan.');

            return Command::SUCCESS;
        }

        $this->info("Mengirim job ping untuk {$routers->count()} router...");

        foreach ($routers as $router) {
            PingRouterJob::dispatch($router);
        }

        $this->info('Seluruh job ping router berhasil dimasukkan ke antrean.');

        return Command::SUCCESS;
    }
}
