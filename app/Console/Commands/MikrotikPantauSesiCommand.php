<?php

namespace App\Console\Commands;

use App\Jobs\Mikrotik\PantauSesiPppRouterJob;
use App\Models\Router;
use Illuminate\Console\Command;

class MikrotikPantauSesiCommand extends Command
{
    protected $signature = 'mikrotik:pantau-sesi';

    protected $description = 'Pemantauan Sesi PPP: antrekan pembacaan sesi aktif tiap router dan siarkan perubahannya';

    public function handle(): int
    {
        Router::query()->each(fn (Router $router) => PantauSesiPppRouterJob::dispatch($router));

        return self::SUCCESS;
    }
}
