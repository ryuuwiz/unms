<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class RecordLastLoginAt
{
    /**
     * Handle the Login event to record the user's last login timestamp (FR-1.7).
     */
    public function handle(Login $event): void
    {
        $event->user->update(['last_login_at' => now()]);
    }
}
