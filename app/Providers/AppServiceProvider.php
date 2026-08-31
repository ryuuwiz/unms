<?php

namespace App\Providers;

use App\Events\InvoicePaidEvent;
use App\Events\LayananPelangganStatusChangedEvent;
use App\Listeners\CatatLogPembayaranListener;
use App\Listeners\HandleLayananStatusChangedListener;
use App\Listeners\LogImpersonationActivity;
use App\Listeners\RecordLastLoginAt;
use App\Listeners\TriggerMikrotikAktivasiStubListener;
use App\Listeners\TriggerWaNotifikasiStubListener;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\ProfilBandwidth;
use App\Observers\IpPoolObserver;
use App\Observers\LayananPelangganObserver;
use App\Observers\ProfilBandwidthObserver;
use App\Services\Whatsapp\WhatsappClient;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsappClient::class, function () {
            return WhatsappClient::forSysblas();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureLivewireAssets();
        $this->registerEventListeners();
        $this->registerModelObservers();
        $this->configureSuperAdminGate();
    }

    /**
     * Register Eloquent model observers.
     */
    protected function registerModelObservers(): void
    {
        IpPool::observe(IpPoolObserver::class);
        ProfilBandwidth::observe(ProfilBandwidthObserver::class);
        LayananPelanggan::observe(LayananPelangganObserver::class);
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
        Event::listen(LayananPelangganStatusChangedEvent::class, HandleLayananStatusChangedListener::class);
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
     * Serve Livewire JS from the published static file in public/vendor/livewire/
     * instead of the dynamic PHP route, bypassing nginx static-asset interception.
     */
    protected function configureLivewireAssets(): void
    {
        Livewire::setScriptRoute(function ($handle) {
            if (file_exists(public_path('vendor/livewire/livewire.min.js'))) {
                return Route::get(
                    'vendor/livewire/livewire.min.js',
                    fn () => response()->file(public_path('vendor/livewire/livewire.min.js'), [
                        'Content-Type' => 'application/javascript',
                        'Cache-Control' => 'public, max-age=31536000, immutable',
                    ])
                );
            }

            if (file_exists(public_path('vendor/livewire/livewire.js'))) {
                return Route::get(
                    'vendor/livewire/livewire.js',
                    fn () => response()->file(public_path('vendor/livewire/livewire.js'), [
                        'Content-Type' => 'application/javascript',
                        'Cache-Control' => 'public, max-age=31536000, immutable',
                    ])
                );
            }

            return Route::get('vendor/livewire/livewire.js', $handle);
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
