<?php

namespace App\Listeners;

use App\Models\AkunPelanggan;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;

class LogImpersonationActivity
{
    /**
     * Handle take impersonation event.
     */
    public function handleTake(TakeImpersonation $event): void
    {
        $impersonator = $event->impersonator;
        $impersonated = $event->impersonated;

        Log::warning('Sesi impersonasi dimulai oleh Super Admin.', [
            'event' => 'impersonate.take',
            'impersonator_id' => $impersonator->getAuthIdentifier(),
            'impersonator_email' => $this->emailDari($impersonator),
            'impersonated_id' => $impersonated->getAuthIdentifier(),
            'impersonated_email' => $this->emailDari($impersonated),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Handle leave impersonation event.
     */
    public function handleLeave(LeaveImpersonation $event): void
    {
        $impersonator = $event->impersonator;
        $impersonated = $event->impersonated;

        Log::info('Sesi impersonasi diakhiri oleh Super Admin.', [
            'event' => 'impersonate.leave',
            'impersonator_id' => $impersonator->getAuthIdentifier(),
            'impersonator_email' => $this->emailDari($impersonator),
            'impersonated_id' => $impersonated->getAuthIdentifier(),
            'impersonated_email' => $this->emailDari($impersonated),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            TakeImpersonation::class => 'handleTake',
            LeaveImpersonation::class => 'handleLeave',
        ];
    }

    private function emailDari(Authenticatable $pengguna): string
    {
        return $pengguna instanceof User || $pengguna instanceof AkunPelanggan ? $pengguna->email : 'unknown';
    }
}
