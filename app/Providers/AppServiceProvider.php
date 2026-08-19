<?php

namespace App\Providers;

use App\Events\InvoicePaidEvent;
use App\Listeners\CatatLogPembayaranListener;
use App\Listeners\LogImpersonationActivity;
use App\Listeners\RecordLastLoginAt;
use App\Listeners\TriggerMikrotikAktivasiStubListener;
use App\Listeners\TriggerWaNotifikasiStubListener;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerEventListeners();
        $this->configureSuperAdminGate();
    }

    /**
     * Register application event listeners.
     */
    protected function registerEventListeners(): void
    {
        Event::listen(Login::class, RecordLastLoginAt::class);
        Event::listen(InvoicePaidEvent::class, CatatLogPembayaranListener::class);
        Event::listen(InvoicePaidEvent::class, TriggerMikrotikAktivasiStubListener::class);
        Event::listen(InvoicePaidEvent::class, TriggerWaNotifikasiStubListener::class);
        Event::subscribe(LogImpersonationActivity::class);
    }

    /**
     * Grant super_admin role access to all gates/permissions without explicit assignment.
     */
    protected function configureSuperAdminGate(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
