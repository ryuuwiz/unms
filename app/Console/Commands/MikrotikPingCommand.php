<?php

namespace App\Console\Commands;

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
