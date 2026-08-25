<?php

namespace App\Livewire\Portal;

use App\Models\AkunPelanggan;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class NotificationBell extends Component
{
    public function tandaiSemuaDibaca(): void
    {
        /** @var AkunPelanggan|null $akun */
        $akun = Auth::guard('pelanggan')->user();

        if ($akun) {
            $akun->unreadNotifications->markAsRead();
        }
    }

    public function render(): View
    {
        /** @var AkunPelanggan|null $akun */
        $akun = Auth::guard('pelanggan')->user();

        $unreadCount = $akun ? $akun->unreadNotifications()->count() : 0;
        $notifications = $akun ? $akun->notifications()->take(7)->get() : collect();

        return view('livewire.portal.notification-bell', [
            'unreadCount' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }
}
